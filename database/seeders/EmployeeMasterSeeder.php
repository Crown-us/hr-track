<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class EmployeeMasterSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['HR', 'Human Resources', ['HR Generalist', 'HR Staff']], ['ENG', 'Engineering', ['Software Engineer', 'Engineering Lead']],
            ['FIN', 'Finance', ['Finance Staff', 'Accounting Staff']], ['OPS', 'Operations', ['Operations Staff', 'Operations Lead']],
            ['MKT', 'Marketing', ['Marketing Staff', 'Marketing Lead']]] as [$code, $name, $positions]) {
            $department = Department::firstOrCreate(['code' => $code], ['name' => $name]);
            foreach ($positions as $position) {
                Position::firstOrCreate(['department_id' => $department->id, 'name' => $position]);
            }
        }
    }
}
