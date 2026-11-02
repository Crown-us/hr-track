<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAccountActive
{
    public function handle(Request $r, Closure $next)
    {
        if ($u = $r->user()) {
            $version = $r->session()->get('account_auth_version');
            if (! $u->is_active || ($version === null ? $u->auth_version !== 1 : (int) $version !== $u->auth_version)) {
                Auth::logout();
                $r->session()->invalidate();
                $r->session()->regenerateToken();

                return redirect('/login')->withErrors(['email' => 'Akses akun berubah. Masuk kembali atau hubungi Admin.']);
            }
            if ($version === null) {
                $r->session()->put('account_auth_version', $u->auth_version);
            }
        }

        return $next($r);
    }
}
