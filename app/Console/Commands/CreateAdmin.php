<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\PasswordBytes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'hr:create-admin';

    protected $description = 'Buat admin pertama secara interaktif tanpa menampilkan kata sandi';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->error('Admin sudah tersedia. Gunakan hr:invite untuk akun berikutnya.');

            return self::FAILURE;
        }

        $data = ['name' => $this->ask('Nama admin'), 'email' => strtolower(trim((string) $this->ask('Email admin'))),
            'password' => $this->secret('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)'),
            'password_confirmation' => $this->secret('Ulangi kata sandi')];
        $validator = Validator::make($data, ['name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', new PasswordBytes, 'confirmed', Password::min(12)->mixedCase()->numbers()]]);
        if ($validator->fails()) {
            $this->error('Data tidak valid. Periksa nama, email unik dan ketentuan kata sandi.');

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->role = 'admin';
        $user->save();
        $this->info('Admin berhasil dibuat. Silakan masuk melalui /login.');

        return self::SUCCESS;
    }
}
