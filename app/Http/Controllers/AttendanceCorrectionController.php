<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Services\AttendanceCorrections;
use App\Services\AttendancePunches;
use App\Services\LeaveRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceCorrectionController
{
    public function index(Request $r, LeaveRecords $leave)
    {
        $hr = $leave->isHr($r->user());
        $e = $hr ? null : $leave->own($r->user());
        $f = $r->validate(['scope' => ['nullable', Rule::in(['own', 'team'])], 'status' => ['nullable', Rule::in(array_keys(AttendanceCorrection::STATUSES))], 'page' => ['nullable', 'integer', 'min:1']]);
        $team = ! $hr && ($f['scope'] ?? 'own') === 'team';
        $q = AttendanceCorrection::with(['employee', 'day']);
        if ($team) {
            abort_unless($r->user()->role === 'supervisor', 403);
            $q->whereHas('employee', fn ($q) => $q->where('manager_employee_id', $e->id)->where('employment_status', '!=', 'inactive')->whereNull('deleted_at'));
        } elseif (! $hr) {
            $q->where('employee_id', $e->id);
        }
        if ($f['status'] ?? null) {
            $q->where('status', $f['status']);
        }

        return Inertia::render('attendance-corrections', ['mode' => 'index', 'isHr' => $hr, 'isTeam' => $team, 'statuses' => AttendanceCorrection::STATUSES, 'filters' => $f, 'requests' => $q->latest('id')->paginate(15)->withQueryString()->through(fn ($c) => $c->forDisplay())]);
    }

    public function create(Request $r, AttendanceCorrections $s, AttendancePunches $p)
    {
        $e = $p->employee($r->user());
        $d = $r->validate(['day_id' => ['required', 'integer', 'min:1']]);
        $day = AttendanceDay::where('employee_id', $e->id)->whereKey($d['day_id'])->firstOrFail();
        $eligible = true;
        $message = null;
        try {
            $s->checkDay($e, $day);
        } catch (ValidationException $error) {
            $eligible = false;
            $message = $error->validator->errors()->first();
        }

        return Inertia::render('attendance-corrections', ['mode' => 'create', 'isHr' => false, 'eligible' => $eligible, 'message' => $message, 'day' => [...$day->forDisplay(), 'date' => $day->date->format('Y-m-d')], 'pending' => AttendanceCorrection::where('attendance_day_id', $day->id)->whereIn('status', AttendanceCorrection::PENDING)->value('id')]);
    }

    public function store(Request $r, AttendanceCorrections $s, AttendancePunches $p)
    {
        $p->employee($r->user());
        $id = filter_var($r->input('day_id'), FILTER_VALIDATE_INT);
        $url = $id && $id > 0 ? route('attendance.corrections.create', ['day_id' => $id]) : route('attendance.corrections.index');
        try {
            $data = $r->validate(['day_id' => ['required', 'integer', 'min:1'], 'day_version' => ['required', 'integer', 'min:1'], 'clock_in' => ['required', 'date_format:Y-m-d\TH:i'], 'clock_out' => ['required', 'date_format:Y-m-d\TH:i'], 'reason' => ['required', 'string', 'min:10', 'max:2000'], 'confirm' => ['accepted']]);
            $c = $s->submit($r->user(), $data);
        } catch (ValidationException $e) {
            throw $e->redirectTo($url);
        }

        return to_route('attendance.corrections.show', $c)->with('success', 'Pengajuan koreksi tersimpan. Absensi menunggu keputusan akhir HR.');
    }

    public function show(Request $r, AttendanceCorrection $correction, AttendanceCorrections $s, LeaveRecords $leave)
    {
        $s->visible($r->user(), $correction);
        $events = DB::table('attendance_correction_events')->join('users', 'users.id', '=', 'actor_id')->where('attendance_correction_id', $correction->id)->orderBy('attendance_correction_events.id')->get(['attendance_correction_events.id', 'action', 'changes', 'users.name as actor', 'attendance_correction_events.created_at'])->map(fn ($v) => ['id' => $v->id, 'action' => $v->action, 'after' => json_decode($v->changes, true)['after'], 'actor' => $v->actor, 'reason' => json_decode($v->changes, true)['reason'], 'created_at' => CarbonImmutable::parse($v->created_at, 'UTC')->toIso8601String()]);

        return Inertia::render('attendance-corrections', ['mode' => 'show', 'isHr' => $leave->isHr($r->user()), 'correction' => $correction->forDisplay(), 'current' => $correction->day->forDisplay(), 'actions' => $s->actions($r->user(), $correction), 'events' => $events]);
    }

    public function decision(Request $r, AttendanceCorrection $correction, AttendanceCorrections $s)
    {
        $s->visible($r->user(), $correction);
        try {
            $data = $r->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['approve', 'reject', 'withdraw'])], 'reason' => ['required', 'string', 'min:10', 'max:2000'], 'confirm' => ['accepted']]);
            $s->decide($r->user(), $correction, $data);
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('attendance.corrections.show', $correction));
        }

        return to_route('attendance.corrections.show', $correction)->with('success', 'Keputusan dan riwayat koreksi tersimpan.');
    }
}
