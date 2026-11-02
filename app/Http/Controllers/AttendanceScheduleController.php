<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceScheduleRequest;
use App\Models\Employee;
use App\Services\AttendanceRecords;
use App\Services\AttendanceSchedules;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AttendanceScheduleController
{
    public function create(Request $r, AttendanceRecords $records)
    {
        abort_unless($records->isHr($r->user()), 403);
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = Employee::with('department')->where('employment_status', '!=', 'inactive');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }

        return Inertia::render('attendance-schedules', ['filters' => $f, 'employees' => $q->orderBy('full_name')->orderBy('id')->limit(20)->get()->map(fn ($e) => [...$e->only('id', 'full_name', 'employee_number'), 'department' => $e->department->name]), 'employeeCount' => (clone $q)->count(), 'preview' => $r->session()->get('schedule_preview'), 'today' => now('Asia/Jakarta')->toDateString()]);
    }

    public function preview(AttendanceScheduleRequest $r, AttendanceSchedules $s)
    {
        try {
            $p = $s->preview($r->user(), $r->validated());
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('attendance.schedules'));
        }

        return to_route('attendance.schedules')->with('schedule_preview', $p);
    }

    public function store(AttendanceScheduleRequest $r, AttendanceSchedules $s)
    {
        try {
            $summary = $s->store($r->user(), $r->validated('token'));
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('attendance.schedules'));
        }

        return to_route('attendance.index')->with('success', $summary['create'].' jadwal baru tersimpan; '.$summary['skip'].' tanggal dilewati.');
    }
}
