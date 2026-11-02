<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountAdministration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InviteAccount extends Command
{
    protected $signature = 'hr:invite {email} {--role=hr_staff : admin, hr_staff, atau viewer}';

    protected $description = 'Terbitkan undangan akun sekali pakai, berlaku 48 jam; salurkan tautan secara privat';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $role = $this->option('role');
        $validator = Validator::make(['email' => $email, 'role' => $role], [
            'email' => ['required', 'email', 'max:255'], 'role' => [Rule::in(['admin', 'hr_staff', 'viewer'])],
        ]);
        if ($validator->fails() || User::where('email', $email)->exists()) {
            $this->error('Email/role tidak valid, atau akun sudah tersedia.');

            return self::FAILURE;
        }

        $url = app(AccountAdministration::class)->invite(['email' => $email, 'role' => $role]);
        $this->warn('Tautan rahasia, berlaku 48 jam. Undangan lama untuk email ini dibatalkan. Tidak ada email yang dikirim.');
        $this->line($url);

        return self::SUCCESS;
    }
}
