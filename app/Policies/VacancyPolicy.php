<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vacancy;

class VacancyPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff', 'viewer'], true);
    }

    public function view(User $user, Vacancy $vacancy): bool
    {
        return $this->viewAny($user) && ! $vacancy->trashed();
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff'], true);
    }

    public function update(User $user, Vacancy $vacancy): bool
    {
        return $this->create($user) && ! $vacancy->trashed();
    }
}
