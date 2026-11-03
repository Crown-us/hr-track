<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InterviewSeeder extends Seeder
{
    public function run(): void
    {
        $applications = Application::where('status', 'interview')->orderBy('id')->get();
        $interviewers = User::whereIn('role', ['admin', 'hr_staff'])->orderBy('id')->get();

        if ($applications->isEmpty() || $interviewers->isEmpty()) {
            $this->command?->warn('Tidak ada aplikasi tahap Interview atau pengawancara.');

            return;
        }

        foreach ($applications as $index => $application) {
            if (Interview::where('application_id', $application->id)->exists()) {
                continue;
            }

            $interviewer = $interviewers[$index % $interviewers->count()];
            $startsAt = Carbon::now('UTC')->addDays(1 + ($index % 10))->setTime(2 + ($index % 6), 0);
            $endsAt = $startsAt->copy()->addMinutes(45);

            Interview::create([
                'application_id' => $application->id,
                'interviewer_id' => $interviewer->id,
                'created_by' => $interviewer->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'location' => $index % 2
                    ? 'Ruang Rapat Lantai 3'
                    : 'https://meet.example.test/hr-interview-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'notes' => 'Wawancara untuk posisi '.$application->vacancy->job_title.'.',
            ]);
        }
    }
}
