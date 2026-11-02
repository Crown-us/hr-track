<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\Application;
use App\Notifications\ApplicationReceived;
use App\Services\ApplicantEmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class ApplicationEmailVerificationController
{
    private function valid(Application $a, string $hash): void
    {
        abort_unless($a->source === 'public' && $a->applicant && hash_equals(hash('sha256', $a->applicant->email), $hash), 404);
    }

    public function show(Request $request, Application $application, string $hash)
    {
        $this->valid($application, $hash);

        return Inertia::render('careers', ['mode' => 'verify', 'confirmationUrl' => $request->fullUrl()]);
    }

    public function store(Application $application, string $hash)
    {
        DB::transaction(function () use ($application, $hash) {
            $a = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $a->setRelation('applicant', Applicant::whereKey($a->applicant_id)->lockForUpdate()->first());
            $this->valid($a, $hash);
            if (! $a->email_verified_at) {
                $a->email_verified_at = now();
                $a->save();
                Notification::route('mail', $a->applicant->email)->notify(new ApplicationReceived($a->id, $hash));
            }
        });

        return redirect('/karier/email-terverifikasi');
    }

    public function resend(Request $request, ApplicantEmailVerification $verification)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $verification->resend($data['email']);

        return redirect('/karier/terima-kasih');
    }
}
