<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $applicants = Applicant::all();
        $vacancies = Vacancy::where('status', 'published')->get();
        $hr = User::where('role', 'hr_staff')->first() ?? User::first();

        if ($applicants->isEmpty() || $vacancies->isEmpty() || ! $hr) {
            $this->command->warn('Data belum lengkap. Jalankan EmployeeMasterSeeder, VacancySeeder, ApplicantSeeder, dan pastikan ada user HR.');

            return;
        }

        $statuses = [
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Offering,
            ApplicationStatus::Accepted,
            ApplicationStatus::Rejected,
        ];

        $used = [];
        $count = 0;

        foreach ($applicants as $applicant) {
            $shuffled = $vacancies->shuffle();
            foreach ($shuffled as $vacancy) {
                $key = $applicant->id.'|'.$vacancy->id;
                if (isset($used[$key])) {
                    continue;
                }
                $used[$key] = true;

                $status = $statuses[array_rand($statuses)];
                $applicationDate = Carbon::now()->subDays(random_int(1, 60))->toDateString();

                $application = Application::create([
                    'applicant_id' => $applicant->id,
                    'vacancy_id' => $vacancy->id,
                    'application_date' => $applicationDate,
                    'notes' => fake('id_ID')->sentence(),
                    'status' => $status,
                    'version' => 1,
                ]);

                ApplicationStatusHistory::create([
                    'application_id' => $application->id,
                    'actor_id' => $hr->id,
                    'old_status' => null,
                    'new_status' => $status->value,
                    'note' => 'Lamaran diterima secara awal.',
                    'created_at' => Carbon::parse($applicationDate)->addDay(),
                ]);

                if (++$count >= 30) {
                    return;
                }
            }
        }
    }
}
