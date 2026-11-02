<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff', 'viewer'], true);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->viewAny($user) && (! $employee->trashed() || $user->role === 'admin');
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff'], true);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->create($user) && ! $employee->trashed();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->role === 'admin' && ! $employee->trashed();
    }

    public function restore(User $user, Employee $employee): bool
    {
        return $user->role === 'admin' && $employee->trashed();
    }

    public function viewEvidence(User $user): bool
    {
        return $user->role === 'admin';
    }
}
