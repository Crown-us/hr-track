<?php

namespace App\Services;

use App\Models\Application;
use App\Notifications\ApplicationEmailVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ApplicantEmailVerification
{
    // Caller holds the application lock (or has just created the row).
    public function send(Application $application): void
    {
        if ($application->source !== 'public' || $application->email_verified_at || ! $application->applicant
            || ($application->verification_sent_at && $application->verification_sent_at->gt(now()->subMinutes(10)))) {
            return;
        }
        $email = $application->applicant->email;
        $application->verification_sent_at = now();
        $application->save();
        Notification::route('mail', $email)->notify(new ApplicationEmailVerification($application->id, hash('sha256', $email)));
    }

    public function resend(string $email): void
    {
        DB::transaction(function () use ($email) {
            $applications = Application::where('source', 'public')->whereNull('email_verified_at')
                ->whereHas('applicant', fn ($q) => $q->where('email', mb_strtolower(trim($email))))
                ->orderBy('verification_sent_at')->orderBy('id')->limit(5)->lockForUpdate()->get();
            foreach ($applications as $a) {
                $this->send($a);
            }
        });
    }
}
