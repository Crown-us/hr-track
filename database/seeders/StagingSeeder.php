<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

class StagingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('staging') || config('database.connections.mysql.database') !== 'hris_lite_staging' || config('database.connections.mysql.username') !== 'hris_staging') {
            throw new \RuntimeException('StagingSeeder hanya untuk database staging terpisah.');
        }
        $credentials = json_decode(file_get_contents(base_path('.local/staging-secrets.json')), true, 512, JSON_THROW_ON_ERROR);
        if (($credentials['profile'] ?? '') !== 'hris-tunnel-staging-v1') {
            throw new \RuntimeException('Profil staging tidak sesuai.');
        }
        $this->call([EmployeeMasterSeeder::class, EmployeeSeeder::class]);
        $available = Employee::where('employment_status', '!=', 'inactive')->orderBy('id')->get();
        $manager = $available[0];
        $worker = $available[1];
        foreach ($credentials['accounts'] as $role => $account) {
            if (! User::where('email', $account['email'])->exists()) {
                User::forceCreate(['name' => ['admin' => 'Admin HRIS', 'hr_staff' => 'Tim HR', 'viewer' => 'Peninjau', 'supervisor' => 'Atasan Tim', 'employee' => 'Anggota Tim'][$role], 'email' => $account['email'], 'role' => $role, 'password' => $account['password'], 'employee_id' => match ($role) {
                    'supervisor' => $manager->id,'employee' => $worker->id,default => null
                }, 'is_active' => true, 'auth_version' => 1, 'version' => 1]);
            }
        }
        $worker->forceFill(['manager_employee_id' => $manager->id])->save();
        $this->call([VacancySeeder::class, ApplicantSeeder::class, ApplicationSeeder::class, InterviewSeeder::class, OfferSeeder::class, ContractSeeder::class, TrainingSeeder::class, DisciplineSeeder::class, LeaveAttendanceSeeder::class]);
    }
}
