<?php

namespace Database\Seeders;

use App\Models\Applicant;
use Illuminate\Database\Seeder;

class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        $firstNames = ['Nadia', 'Raka', 'Sari', 'Tio', 'Usman', 'Vera', 'Wulan', 'Xavier', 'Yoga', 'Zara', 'Adit', 'Bella', 'Cahyo', 'Dewi', 'Eko', 'Fani', 'Gina', 'Hadi', 'Intan', 'Joko'];
        $lastNames = ['Putri', 'Ramadhan', 'Saputra', 'Wijaya', 'Kusuma', 'Lestari', 'Hidayat', 'Setiawan', 'Astuti', 'Prabowo'];

        foreach ($firstNames as $index => $firstName) {
            Applicant::create([
                'full_name' => $firstName.' '.$lastNames[$index % count($lastNames)],
                'email' => strtolower(str_replace(' ', '.', $firstName.$lastNames[$index % count($lastNames)]).'@example.test'),
                'phone' => '081'.random_int(1000000, 9999999),
            ]);
        }
    }
}
