<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoAccountSeeder dilewati: akun contoh hanya untuk local/testing.');

            return;
        }
        $password = (string) env('DEMO_PASSWORD', 'DemoHris123!');

        foreach ([
            ['Ayu Prameswari', 'admin@example.test', 'admin'],
            ['Bima Saputra', 'hr@example.test', 'hr_staff'],
            ['Citra Lestari', 'viewer@example.test', 'viewer'],
        ] as [$name, $email, $role]) {
            $this->account($name, $email, $role, null, $password);
        }

        $linked = User::whereNotNull('employee_id')->pluck('employee_id')->all();
        $available = Employee::where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at')
            ->whereNotIn('id', $linked)
            ->orderBy('id')
            ->get();

        $supervisor = $this->preferred('EMP1000', $available);
        $member = $this->preferred('EMP1001', $available, $supervisor?->id);

        if (! $supervisor || ! $member) {
            $this->command?->warn('Akun Pegawai/Atasan dilewati: butuh minimal dua pegawai aktif yang belum terhubung ke akun.');

            return;
        }

        $this->account($supervisor->full_name, 'atasan@example.test', 'supervisor', $supervisor->id, $password);
        $this->account($member->full_name, 'pegawai@example.test', 'employee', $member->id, $password);

        Employee::whereKey($member->id)->update(['manager_employee_id' => $supervisor->id]);

        $team = Employee::where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at')
            ->whereNotIn('id', [$supervisor->id, $member->id])
            ->orderBy('id')
            ->take(4)
            ->get();

        foreach ($team as $person) {
            Employee::whereKey($person->id)->update(['manager_employee_id' => $supervisor->id]);
        }

        $this->command?->info('Akun demo lokal: admin@example.test, hr@example.test, viewer@example.test, atasan@example.test, pegawai@example.test.');
        $this->command?->info('Kata sandi akun demo disetel ulang saat seeding.');
        $this->command?->warn('Akun demo hanya untuk local/testing. Jangan gunakan kredensial ini di production.');
    }

    private function preferred(string $number, $available, ?int $exclude = null): ?Employee
    {
        $query = Employee::where('employee_number', $number)
            ->where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at');

        if ($exclude !== null) {
            $query->whereKeyNot($exclude);
        }

        $employee = $query->first();

        if ($employee && ! User::where('employee_id', $employee->id)->whereNotIn('email', ['atasan@example.test', 'pegawai@example.test'])->exists()) {
            return $employee;
        }

        return $available->first(fn (Employee $candidate) => $candidate->id !== $exclude);
    }

    private function account(string $name, string $email, string $role, ?int $employeeId, string $password): User
    {
        $user = User::firstOrNew(['email' => $email]);

        // Akun demo selalu memakai kata sandi yang didokumentasikan agar presentasi
        // tidak gagal ketika akun sudah pernah dibuat pada database development.
        $user->forceFill([
            'name' => $name,
            'role' => $role,
            'is_active' => true,
            'employee_id' => $employeeId,
            'version' => $user->version ?: 1,
            'auth_version' => $user->auth_version ?: 1,
            'password' => $password,
        ])->save();

        return $user;
    }
}
