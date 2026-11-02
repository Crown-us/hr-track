<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Database\Seeder;

class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        $applications = Application::where('status', 'interview')->get();
        $interviewers = User::whereIn('role', ['admin', 'hr_staff'])->get();

        if ($applications->isEmpty() || $interviewers->isEmpty()) {
            $this->command->warn('Tidak ada aplikasi tahap Interview atau pengawancara.');

            return;
        }

        foreach ($applications as $index => $application) {
            $interviewer = $interviewers->random();
            $startsAt = now('UTC')->addDays(random_int(1, 14))->addHours(random_int(8, 16));
            $endsAt = (clone $startsAt)->addMinutes(random_int(30, 120));

            Interview::create([
                'application_id' => $application->id,
                'interviewer_id' => $interviewer->id,
                'created_by' => $interviewer->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'location' => random_int(0, 1) ? 'Ruang Rapat Lantai 3' : 'https://meet.example.test/hr-interview-'.random_int(1000, 9999),
                'notes' => fake('id_ID')->sentence(),
                'version' => 1,
            ]);
        }
    }
}
