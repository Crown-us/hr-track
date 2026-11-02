<?php

namespace App\Providers;

use App\Models\User;
use App\Rules\PasswordBytes;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('email-resend', fn (Request $request) => Limit::perHour(3)->by('email-resend:'.$request->ip()));
        Fortify::loginView(fn () => Inertia::render('auth', ['mode' => 'login', 'invitation' => null]));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth', ['mode' => 'confirm', 'invitation' => null]));

        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'max:72', new PasswordBytes],
                'remember' => ['sometimes', 'boolean'],
            ]);
            $user = User::where('email', Str::lower(trim((string) $request->input('email'))))->first();

            if ($user && Hash::check((string) $request->input('password'), $user->password)) {
                return $user;
            }

            // Keep password hashing work for unknown accounts to reduce account enumeration.
            if (! $user) {
                Hash::check((string) $request->input('password'), '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            }

            return null;
        });

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()))
                ->response(fn () => back()->withErrors(['email' => 'Terlalu banyak percobaan masuk. Tunggu satu menit lalu coba lagi.'])),
            Limit::perMinute(20)->by('login-ip:'.$request->ip())
                ->response(fn () => back()->withErrors(['email' => 'Terlalu banyak percobaan masuk. Tunggu satu menit lalu coba lagi.'])),
        ]);
        RateLimiter::for('signup', fn (Request $request) => Limit::perMinute(10)->by('signup:'.$request->ip())
            ->response(fn () => back()->withErrors(['token' => 'Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.'])));
        RateLimiter::for('public-application', fn (Request $request) => Limit::perHour(3)->by('public-application:'.$request->ip())->response(fn () => back()->withErrors(['submission' => 'Batas pengiriman tercapai. Coba kembali dalam satu jam atau hubungi tim HR melalui kanal resmi perusahaan.'])));
        Gate::define('access-hr', fn (User $user) => in_array($user->role, ['admin', 'hr_staff', 'viewer'], true));
    }
}
