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
        $applicants = Applicant::orderBy('id')->get();
        $vacancies = Vacancy::where('status', 'published')->orderBy('id')->get();
        $hr = User::where('role', 'hr_staff')->orderBy('id')->first() ?? User::where('role', 'admin')->orderBy('id')->first();

        if ($applicants->isEmpty() || $vacancies->isEmpty() || ! $hr) {
            $this->command?->warn('Data belum lengkap. Jalankan EmployeeMasterSeeder, VacancySeeder, ApplicantSeeder, dan pastikan ada user HR.');

            return;
        }

        $remaining = 30 - Application::count();

        if ($remaining <= 0) {
            $this->command?->info('Lamaran demo sudah tersedia; tidak ada duplikasi.');

            return;
        }

        $statuses = [
            ApplicationStatus::Screening,
            ApplicationStatus::Interview,
            ApplicationStatus::Offering,
            ApplicationStatus::Accepted,
            ApplicationStatus::Rejected,
        ];

        $created = 0;
        $index = 0;

        foreach ($applicants as $applicant) {
            foreach ($vacancies as $vacancy) {
                if (Application::withTrashed()->where('applicant_id', $applicant->id)->where('vacancy_id', $vacancy->id)->exists()) {
                    $index++;

                    continue;
                }

                $status = $statuses[$index % count($statuses)];
                $applicationDate = Carbon::now('Asia/Jakarta')->subDays(20 + $index)->toDateString();

                $application = Application::create([
                    'applicant_id' => $applicant->id,
                    'vacancy_id' => $vacancy->id,
                    'application_date' => $applicationDate,
                    'notes' => 'Lamaran demo untuk posisi '.$vacancy->job_title.'.',
                ]);

                $application->forceFill(['status' => $status])->save();
                $this->history($application, $hr, $status, $applicationDate);

                $created++;

                if ($created >= $remaining) {
                    return;
                }

                $index++;
            }
        }
    }

    private function history(Application $application, User $actor, ApplicationStatus $status, string $date): void
    {
        $steps = match ($status) {
            ApplicationStatus::Screening => [[null, ApplicationStatus::Screening]],
            ApplicationStatus::Interview => [[null, ApplicationStatus::Screening], [ApplicationStatus::Screening, ApplicationStatus::Interview]],
            ApplicationStatus::Offering => [[null, ApplicationStatus::Screening], [ApplicationStatus::Screening, ApplicationStatus::Interview], [ApplicationStatus::Interview, ApplicationStatus::Offering]],
            ApplicationStatus::Accepted => [[null, ApplicationStatus::Screening], [ApplicationStatus::Screening, ApplicationStatus::Interview], [ApplicationStatus::Interview, ApplicationStatus::Offering], [ApplicationStatus::Offering, ApplicationStatus::Accepted]],
            ApplicationStatus::Rejected => [[null, ApplicationStatus::Screening], [ApplicationStatus::Screening, ApplicationStatus::Rejected]],
        };

        foreach ($steps as $offset => [$old, $new]) {
            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'actor_id' => $actor->id,
                'old_status' => $old?->value,
                'new_status' => $new->value,
                'note' => 'Riwayat demo: '.$new->label().'.',
                'created_at' => Carbon::parse($date)->addDays($offset)->setTime(3, 0),
            ]);
        }
    }
}
