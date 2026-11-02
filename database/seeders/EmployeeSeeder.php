<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::all()->keyBy('id');
        $positions = Position::all()->keyBy('id');

        if ($departments->isEmpty() || $positions->isEmpty()) {
            $this->command->warn('Master departemen/jabatan belum ada. Jalankan EmployeeMasterSeeder terlebih dahulu.');

            return;
        }

        $firstNames = ['Andi', 'Budi', 'Citra', 'Dedi', 'Eka', 'Farhan', 'Gita', 'Hari', 'Indah', 'Joko', 'Kirana', 'Luki', 'Maya', 'Nadia', 'Oka', 'Putri', 'Raka', 'Sari', 'Tio', 'Usman', 'Vera', 'Wulan', 'Xavier', 'Yoga', 'Zara'];
        $lastNames = ['Pratama', 'Saputra', 'Wijaya', 'Kusuma', 'Lestari', 'Hidayat', 'Setiawan', 'Astuti', 'Prabowo', 'Anggraini'];
        $statuses = [EmploymentStatus::Permanent, EmploymentStatus::Contract, EmploymentStatus::Probation, EmploymentStatus::Intern, EmploymentStatus::Inactive];

        $departmentIds = $departments->keys()->toArray();
        $positionByDept = [];
        foreach ($positions as $position) {
            $positionByDept[$position->department_id][] = $position;
        }

        $employeeNumber = 1000;
        foreach ($firstNames as $index => $firstName) {
            $deptId = $departmentIds[$index % count($departmentIds)];
            $deptPositions = $positionByDept[$deptId] ?? $positions->values()->toArray();
            $position = $deptPositions[array_rand($deptPositions)];
            $status = $statuses[$index % count($statuses)];

            Employee::firstOrCreate(
                ['employee_number' => 'EMP'.str_pad($employeeNumber++, 4, '0', STR_PAD_LEFT)],
                [
                    'full_name' => $firstName.' '.$lastNames[$index % count($lastNames)],
                    'work_email' => strtolower(str_replace(' ', '.', $firstName.$lastNames[$index % count($lastNames)]).'@example.test'),
                    'phone' => '081'.random_int(1000000, 9999999),
                    'department_id' => $deptId,
                    'position_id' => $position->id,
                    'employment_status' => $status,
                    'join_date' => Carbon::now()->subDays(random_int(30, 1095))->toDateString(),
                ]
            );
        }
    }
}
