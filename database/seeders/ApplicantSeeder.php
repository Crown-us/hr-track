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
            $lastName = $lastNames[$index % count($lastNames)];
            $email = strtolower($firstName.$lastName).'@example.test';

            Applicant::firstOrCreate(
                ['email' => $email],
                [
                    'full_name' => $firstName.' '.$lastName,
                    'phone' => '081'.str_pad((string) (1000000 + $index * 7919), 7, '0', STR_PAD_LEFT),
                ]
            );
        }
    }
}
