<?php

namespace App\Policies;

use App\Models\DisciplinaryCase;
use App\Models\User;

class DisciplinaryCasePolicy
{
    public function viewAny(User $u): bool
    {
        return in_array($u->role, ['admin', 'hr_staff'], true);
    }

    public function view(User $u, DisciplinaryCase $case): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, DisciplinaryCase $case): bool
    {
        return $this->viewAny($u);
    }
}
