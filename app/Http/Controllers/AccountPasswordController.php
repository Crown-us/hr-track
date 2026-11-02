<?php

namespace App\Http\Controllers;

use App\Rules\PasswordBytes;
use App\Services\AccountAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountPasswordController
{
    public function show(Request $r, string $token, AccountAdministration $records)
    {
        $email = is_string($r->query('email')) ? $r->query('email') : '';
        $valid = strlen($email) <= 255 && $records->resetValid($email, $token);

        return Inertia::render('auth', ['mode' => 'reset', 'invitation' => $valid ? ['email' => $email, 'token' => $token] : null, 'invalidInvitation' => ! $valid]);
    }

    public function store(Request $r, AccountAdministration $records)
    {
        $token = $r->input('token');
        $email = $r->input('email');
        $redirect = is_string($token) && strlen($token) === 64 && is_string($email) && strlen($email) <= 255 ? url('/reset-password/'.rawurlencode($token)).'?'.http_build_query(['email' => $email]) : url('/login');
        try {
            $d = Validator::make($r->all(), ['email' => ['required', 'email', 'max:255'], 'token' => ['required', 'string', 'size:64'], 'password' => ['required', 'string', 'max:72', new PasswordBytes, 'confirmed', Password::min(12)->mixedCase()->numbers()]], ['password.confirmed' => 'Konfirmasi kata sandi belum cocok.', 'password.mixed' => 'Sertakan huruf besar dan huruf kecil.', 'password.numbers' => 'Sertakan minimal satu angka.'])->validate();
            $records->resetPassword($d);
        } catch (ValidationException $e) {
            throw $e->redirectTo($redirect);
        }

        return redirect('/login')->with('success', 'Kata sandi diperbarui. Silakan masuk kembali.');
    }
}
