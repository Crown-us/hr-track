<?php

namespace App\Http\Controllers;

use App\Rules\PasswordBytes;
use App\Services\AccountAdministration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationRegistrationController
{
    public function create(Request $request): Response
    {
        $token = $request->string('token')->toString();
        $invitation = strlen($token) === 64
            ? DB::table('account_invitations')->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')->where('expires_at', '>', now())->first()
            : null;

        return Inertia::render('auth', [
            'mode' => 'register',
            'invitation' => $invitation ? ['email' => $invitation->email, 'token' => $token] : null,
            'invalidInvitation' => $token !== '' && ! $invitation,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = Validator::make($request->all(), [
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:72', new PasswordBytes, 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ], [
            'token.required' => 'Gunakan tautan undangan dari pengelola untuk membuat akun.',
            'token.size' => 'Undangan tidak valid atau sudah kedaluwarsa.',
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama maksimal 120 karakter.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.confirmed' => 'Konfirmasi kata sandi belum cocok.',
            'password.min' => 'Gunakan minimal 12 karakter.',
            'password.max' => 'Kata sandi maksimal 72 karakter.',
            'password.mixed' => 'Sertakan huruf besar dan huruf kecil.',
            'password.numbers' => 'Sertakan minimal satu angka.',
        ])->validate();

        $user = app(AccountAdministration::class)->activate($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->home());
    }
}
