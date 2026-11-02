<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EmployeeMasterSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder dilewati: data contoh hanya dijalankan pada environment local/testing.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
