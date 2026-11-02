<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff', 'viewer'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'hr_staff'], true);
    }

    public function view(User $user, Application $application): bool
    {
        return $this->viewAny($user) && ! $application->trashed() && $application->applicant !== null;
    }

    public function update(User $user, Application $application): bool
    {
        return $this->create($user) && $this->view($user, $application);
    }
}
