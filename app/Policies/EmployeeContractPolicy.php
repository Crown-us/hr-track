<?php

namespace App\Policies;

use App\Models\EmployeeContract;
use App\Models\User;

class EmployeeContractPolicy
{
    public function viewAny(User $u): bool
    {
        return in_array($u->role, ['admin', 'hr_staff', 'viewer'], true);
    }

    public function view(User $u, EmployeeContract $c): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return in_array($u->role, ['admin', 'hr_staff'], true);
    }

    public function update(User $u, EmployeeContract $c): bool
    {
        return $this->create($u);
    }
}
