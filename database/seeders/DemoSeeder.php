<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder dilewati: data contoh hanya untuk local/testing.');

            return;
        }
        $this->call([
            EmployeeSeeder::class,
            VacancySeeder::class,
            ApplicantSeeder::class,
            DemoAccountSeeder::class,
            ApplicationSeeder::class,
            InterviewSeeder::class,
            OfferSeeder::class,
            ContractSeeder::class,
            TrainingSeeder::class,
            DisciplineSeeder::class,
        ]);
    }
}
