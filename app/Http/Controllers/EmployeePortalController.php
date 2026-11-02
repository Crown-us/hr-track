<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmployeePortalController
{
    private function own(User $u): Employee
    {
        abort_unless(in_array($u->role, ['employee', 'supervisor'], true), 403);
        $e = Employee::whereKey($u->employee_id)->where('employment_status', '!=', 'inactive')->with(['department', 'position', 'manager'])->first();
        abort_unless($e, 403, 'Data pegawai belum tersedia. Hubungi Admin.');

        return $e;
    }

    private function basic(Employee $e): array
    {
        return [...$e->only('id', 'employee_number', 'full_name'), 'department' => $e->department->name, 'position' => $e->position->name, 'employment_status' => $e->employment_status->label(), 'join_date' => $e->join_date->format('Y-m-d')];
    }

    public function me(Request $r)
    {
        $e = $this->own($r->user());

        return Inertia::render('employee-portal', ['mode' => 'me', 'employee' => [...$this->basic($e), 'work_email' => $e->work_email, 'phone' => $e->phone, 'manager_name' => $e->manager?->full_name]]);
    }

    public function team(Request $r)
    {
        $e = $this->own($r->user());
        abort_unless($r->user()->role === 'supervisor', 403);
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = Employee::with(['department', 'position'])->where('manager_employee_id', $e->id)->where('employment_status', '!=', 'inactive');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }

        return Inertia::render('employee-portal', ['mode' => 'team', 'filters' => $f, 'team' => $q->orderBy('full_name')->paginate(15)->withQueryString()->through(fn ($p) => $this->basic($p))]);
    }

    public function member(Request $r, Employee $employee)
    {
        $e = $this->own($r->user());
        abort_unless($r->user()->role === 'supervisor', 403);
        abort_unless($employee->manager_employee_id === $e->id && $employee->employment_status->value !== 'inactive', 404);

        return Inertia::render('employee-portal', ['mode' => 'member', 'employee' => $this->basic($employee->load(['department', 'position']))]);
    }
}
