<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;

class VacancySeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->command->warn('Master departemen belum ada. Jalankan EmployeeMasterSeeder terlebih dahulu.');

            return;
        }

        $vacancies = [
            ['Software Engineer', 'full_time', 'Jakarta', 'published'],
            ['HR Generalist', 'full_time', 'Jakarta', 'published'],
            ['Finance Staff', 'contract', 'Bandung', 'draft'],
            ['Marketing Lead', 'full_time', 'Surabaya', 'closed'],
            ['Operations Staff', 'part_time', 'Medan', 'published'],
            ['Engineering Lead', 'full_time', 'Jakarta', 'draft'],
        ];

        $today = now('Asia/Jakarta')->toDateString();

        foreach ($vacancies as $index => [$title, $type, $location, $status]) {
            $department = $departments->random();
            $opening = now('Asia/Jakarta')->subDays(random_int(0, 10))->toDateString();
            $closing = now('Asia/Jakarta')->addDays(random_int(10, 60))->toDateString();

            Vacancy::create([
                'job_title' => $title,
                'department_id' => $department->id,
                'employment_type' => $type,
                'location' => $location,
                'description' => fake('id_ID')->paragraphs(3, true),
                'requirements' => fake('id_ID')->paragraphs(3, true),
                'opening_date' => $opening,
                'closing_date' => $closing,
                'status' => $status,
            ]);
        }
    }
}
