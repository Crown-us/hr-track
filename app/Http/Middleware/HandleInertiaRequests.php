<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request), 'flash' => ['success' => fn () => $request->session()->get('success')], 'auth' => [
            'user' => fn () => ($user = $request->user()) ? [
                ...$user->only('id', 'name', 'email', 'role'),
                'role_label' => User::ROLES[$user->role] ?? $user->role,
                'home' => $user->home(),
            ] : null,
        ]];
    }
}
