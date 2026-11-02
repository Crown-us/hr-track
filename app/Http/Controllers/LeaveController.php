<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveFormRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeaveController
{
    private function run(callable $fn, string $url): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e->redirectTo($url);
        }
    }

    public function index(Request $r, LeaveRecords $s)
    {
        $hr = $s->isHr($r->user());
        $e = $hr ? null : $s->own($r->user());
        $f = $r->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'status' => ['nullable', Rule::in(array_keys(LeaveRequest::STATUSES))], 'scope' => ['nullable', Rule::in(['own', 'team'])], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $year = (int) ($f['year'] ?? now('Asia/Jakarta')->year);
        $team = ! $hr && ($f['scope'] ?? 'own') === 'team';
        $q = LeaveRequest::with('employee')->where('year', $year);
        if ($team) {
            abort_unless($r->user()->role === 'supervisor', 403);
            $q->whereHas('employee', fn ($q) => $q->where('manager_employee_id', $e->id)->whereNull('deleted_at')->where('employment_status', '!=', 'inactive'));
        } elseif (! $hr) {
            $q->where('employee_id', $e->id);
        }
        if (! empty($f['status'])) {
            $q->where('status', $f['status']);
        }
        if ($term = trim($f['q'] ?? '')) {
            $q->whereHas('employee', fn ($q) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%')));
        }

        return Inertia::render('leaves', ['mode' => 'index', 'isHr' => $hr, 'isTeam' => $team, 'filters' => [...$f, 'year' => $year], 'types' => LeaveRequest::TYPES, 'statuses' => LeaveRequest::STATUSES, 'balance' => $e && ! $team ? $s->balance($e->id, $year) : null,
            'requests' => $q->orderByDesc('created_at')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($l) => $l->forDisplay())]);
    }

    public function create(Request $r, LeaveRecords $s)
    {
        $e = $s->own($r->user());
        $year = now('Asia/Jakarta')->year;

        return Inertia::render('leaves', ['mode' => 'create', 'isHr' => false, 'types' => LeaveRequest::TYPES, 'balance' => $s->balance($e->id, $year), 'nextBalance' => $s->balance($e->id, $year + 1), 'holidays' => DB::table('leave_holidays')->whereYear('date', '>=', $year)->orderBy('date')->get(['date', 'name'])]);
    }

    public function store(LeaveFormRequest $r, LeaveRecords $s)
    {
        $l = $this->run(fn () => $s->submit($r->user(), $r->validated(), $r->file('document')), route('leave.create'));

        return to_route('leave.show', $l)->with('success', 'Pengajuan tersimpan. Tanggal kerja dicatat; saldo cuti tahunan ditahan bila digunakan.');
    }

    public function show(Request $r, LeaveRequest $leave, LeaveRecords $s)
    {
        $s->visible($r->user(), $leave);
        $private = $s->isHr($r->user()) || $r->user()->employee_id === $leave->employee_id;

        return Inertia::render('leaves', ['mode' => 'show', 'isHr' => $s->isHr($r->user()), 'isTeam' => $r->user()->role === 'supervisor' && $r->user()->employee_id !== $leave->employee_id, 'leave' => [...$leave->forDisplay(), 'reason' => $leave->reason, 'work_dates' => $leave->work_dates, 'has_document' => $private && (bool) $leave->document_path], 'actions' => $s->actions($r->user(), $leave),
            'events' => DB::table('leave_events')->join('users', 'users.id', '=', 'leave_events.actor_id')->where('leave_request_id', $leave->id)->orderByDesc('leave_events.id')->limit(100)->get(['leave_events.id', 'action', 'changes', 'users.name as actor', 'leave_events.created_at'])->map(fn ($event) => ['id' => $event->id, 'action' => $event->action, 'actor' => $event->actor, 'changes' => json_decode($event->changes, true), 'created_at' => CarbonImmutable::parse($event->created_at, 'UTC')->toIso8601String()])]);
    }

    public function action(LeaveFormRequest $r, LeaveRequest $leave, LeaveRecords $s)
    {
        $this->run(fn () => $s->decide($r->user(), $leave, $r->validated()), route('leave.show', $leave));

        return to_route('leave.show', $leave)->with('success', 'Keputusan tersimpan beserta riwayatnya.');
    }

    public function document(Request $r, LeaveRequest $leave, LeaveRecords $s)
    {
        $s->visible($r->user(), $leave);
        abort_unless($s->isHr($r->user()) || $r->user()->employee_id === $leave->employee_id, 403);
        abort_unless($leave->document_path && Storage::disk('leave_documents')->exists($leave->document_path), 404);

        return Storage::disk('leave_documents')->download($leave->document_path, 'pengajuan-'.$leave->id.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function settings(Request $r, LeaveRecords $s)
    {
        abort_unless($s->isHr($r->user()), 403);
        $f = $r->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $year = (int) ($f['year'] ?? now('Asia/Jakarta')->year);
        $q = Employee::where('employment_status', '!=', 'inactive');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }
        $employees = $q->orderBy('full_name')->paginate(15)->withQueryString();
        $ids = $employees->getCollection()->pluck('id');
        $allocations = DB::table('leave_balances')->whereIn('employee_id', $ids)->where('year', $year)->get()->keyBy('employee_id');
        $usage = LeaveRequest::whereIn('employee_id', $ids)->where('year', $year)->where('type', 'annual')->whereIn('status', LeaveRequest::HELD)->selectRaw("employee_id, SUM(CASE WHEN status = 'approved' THEN days ELSE 0 END) AS used, SUM(CASE WHEN status <> 'approved' THEN days ELSE 0 END) AS reserved")->groupBy('employee_id')->get()->keyBy('employee_id');

        return Inertia::render('leaves', ['mode' => 'settings', 'isHr' => true, 'filters' => [...$f, 'year' => $year], 'employees' => $employees->through(fn ($e) => [...$e->only('id', 'full_name', 'employee_number'), 'balance' => ['year' => $year, 'allocated' => (int) ($allocations[$e->id]->days ?? 0), 'version' => (int) ($allocations[$e->id]->version ?? 0), 'used' => (int) ($usage[$e->id]->used ?? 0), 'reserved' => (int) ($usage[$e->id]->reserved ?? 0)]]),
            'holidays' => DB::table('leave_holidays')->whereYear('date', $year)->orderBy('date')->get(['id', 'date', 'name']),
            'settingEvents' => DB::table('leave_events')->join('users', 'users.id', '=', 'leave_events.actor_id')->leftJoin('employees', 'employees.id', '=', 'leave_events.employee_id')->whereNull('leave_request_id')->orderByDesc('leave_events.id')->limit(20)->get(['leave_events.id', 'action', 'changes', 'users.name as actor', 'employees.full_name as employee', 'leave_events.created_at'])->map(fn ($e) => [...(array) $e, 'changes' => json_decode($e->changes, true), 'created_at' => CarbonImmutable::parse($e->created_at, 'UTC')->toIso8601String()])]);
    }

    public function balance(LeaveFormRequest $r, LeaveRecords $s)
    {
        $url = route('leave.settings', ['year' => $r->integer('year'), 'q' => $r->input('q')]);
        $this->run(fn () => $s->allocate($r->user(), $r->validated()), $url);

        return redirect($url)->with('success', 'Alokasi cuti diperbarui beserta alasannya.');
    }

    public function holiday(LeaveFormRequest $r, LeaveRecords $s)
    {
        $url = route('leave.settings', ['year' => substr($r->input('date'), 0, 4)]);
        $this->run(fn () => $s->holiday($r->user(), $r->validated()), $url);

        return redirect($url)->with('success', 'Hari libur ditambahkan. Pengajuan lama tetap memakai perhitungan sebelumnya.');
    }

    public function removeHoliday(LeaveFormRequest $r, int $holiday, LeaveRecords $s)
    {
        $url = route('leave.settings');
        $this->run(fn () => $s->holiday($r->user(), $r->validated(), $holiday), $url);

        return redirect($url)->with('success', 'Hari libur dihapus dari kalender. Riwayat pengajuan tetap tersimpan.');
    }
}
