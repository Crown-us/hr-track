<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceDayRequest;
use App\Models\Employee;
use App\Services\AttendanceCalendar;
use App\Services\AttendancePunches;
use App\Services\AttendanceRecords;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceController
{
    public function index(Request $r, AttendanceRecords $records, AttendanceCalendar $calendar)
    {
        if (! $records->isHr($r->user())) {
            abort_unless(in_array($r->user()->role, ['employee', 'supervisor'], true), 403);
            $e = Employee::whereKey($r->user()->employee_id)->first();
            abort_unless($e, 403);

            return $this->show($r, $e, $records, $calendar, true);
        }
        $filters = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $q = Employee::with(['department', 'position']);
        if ($search = trim($filters['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$search.'%')->orWhere('employee_number', 'like', '%'.$search.'%'));
        }

        return Inertia::render('attendance', ['mode' => 'index', 'isHr' => true, 'filters' => $filters, 'employees' => $q->orderBy('full_name')->paginate(15)->withQueryString()->through(fn ($e) => [...$e->only('id', 'full_name', 'employee_number'), 'department' => $e->department->name, 'position' => $e->position->name])]);
    }

    public function show(Request $r, Employee $employee, AttendanceRecords $records, AttendanceCalendar $calendar, bool $own = false)
    {
        $records->authorize($r->user(), $employee);
        $f = $r->validate(['year' => ['nullable', 'integer', 'between:2000,2100'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $year = (int) ($f['year'] ?? now('Asia/Jakarta')->year);
        $date = $f['date'] ?? ($year === now('Asia/Jakarta')->year ? now('Asia/Jakarta')->toDateString() : sprintf('%04d-01-01', $year));
        if ((int) substr($date, 0, 4) !== $year) {
            throw ValidationException::withMessages(['date' => 'Tanggal harus berada pada tahun yang dipilih.']);
        }
        $isHr = $records->isHr($r->user());

        return Inertia::render('attendance', ['mode' => 'show', 'isHr' => $isHr, 'isTeam' => ! $isHr && $employee->id !== $r->user()->employee_id, 'baseUrl' => $own ? route('attendance.index', absolute: false) : route('attendance.employee', $employee, absolute: false), 'employee' => [...$employee->only('id', 'full_name', 'employee_number'), 'join_date' => $employee->join_date->format('Y-m-d'), 'active' => ! $employee->trashed() && $employee->employment_status->value !== 'inactive'], 'punch' => ! $isHr && $employee->id === $r->user()->employee_id ? app(AttendancePunches::class)->panel($r->user()) : null, 'calendar' => $calendar->build($employee, $year, $date, $isHr || $employee->id === $r->user()->employee_id)]);
    }

    public function store(AttendanceDayRequest $r, Employee $employee, AttendanceRecords $records)
    {
        $d = $r->validated();
        $url = route('attendance.employee', ['employee' => $employee, 'year' => (int) substr($d['date'], 0, 4), 'date' => $d['date']]);
        try {
            $records->save($r->user(), $employee, $d);
        } catch (ValidationException $e) {
            throw $e->redirectTo($url);
        }

        return redirect($url)->with('success', 'Catatan kehadiran dan riwayatnya tersimpan.');
    }
}
