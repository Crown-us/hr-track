<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\User;

class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff', 'viewer'], true);
    }

    public function view(User $user, Training $training): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff'], true);
    }

    public function update(User $user, Training $training): bool
    {
        return $this->create($user);
    }
}
