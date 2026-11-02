<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VacancySeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::all()->keyBy('code');

        if ($departments->isEmpty()) {
            $this->command?->warn('Master departemen belum ada. Jalankan EmployeeMasterSeeder terlebih dahulu.');

            return;
        }

        $vacancies = [
            ['Software Engineer', 'ENG', 'full_time', 'Jakarta', 'published', -5, 45],
            ['HR Generalist', 'HR', 'full_time', 'Jakarta', 'published', -3, 30],
            ['Finance Staff', 'FIN', 'contract', 'Bandung', 'draft', 7, 60],
            ['Marketing Lead', 'MKT', 'full_time', 'Surabaya', 'closed', -40, -5],
            ['Operations Staff', 'OPS', 'part_time', 'Medan', 'published', -2, 21],
            ['Engineering Lead', 'ENG', 'full_time', 'Jakarta', 'draft', 14, 75],
        ];

        $today = Carbon::now('Asia/Jakarta');

        foreach ($vacancies as [$title, $departmentCode, $type, $location, $status, $openOffset, $closeOffset]) {
            $department = $departments->get($departmentCode);

            if (! $department) {
                continue;
            }

            Vacancy::firstOrCreate(
                ['job_title' => $title],
                [
                    'department_id' => $department->id,
                    'employment_type' => $type,
                    'location' => $location,
                    'description' => 'Lowongan demo untuk posisi '.$title.'. Deskripsi ini hanya digunakan untuk presentasi lokal.',
                    'requirements' => 'Pengalaman relevan, kemampuan komunikasi, dan kesediaan bekerja di '.$location.'.',
                    'opening_date' => $today->copy()->addDays($openOffset)->toDateString(),
                    'closing_date' => $today->copy()->addDays($closeOffset)->toDateString(),
                    'status' => $status,
                ]
            );
        }
    }
}
