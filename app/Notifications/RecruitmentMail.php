<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class RecruitmentMail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $applicationId, public string $emailHash)
    {
        // Same database connection as the surrounding transaction; jobs roll back with the application.
        $this->onConnection('recruitment')->onQueue('recruitment-mail')->beforeCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    protected function application(): ?Application
    {
        return Application::with('applicant', 'vacancy')->find($this->applicationId);
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $a = $this->application();

        return $a && $a->source === 'public' && $a->applicant
            && hash_equals($this->emailHash, hash('sha256', $a->applicant->email))
            && ($notifiable->routes['mail'] ?? null) === $a->applicant->email
            && ($this instanceof ApplicationReceived ? $a->email_verified_at !== null : $a->email_verified_at === null);
    }
}
