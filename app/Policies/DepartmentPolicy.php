<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff', 'viewer'], true);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff'], true);
    }

    public function update(User $user, Department $department): bool
    {
        return $this->create($user);
    }
}
