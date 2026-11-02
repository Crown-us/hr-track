<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class ApplicationEmailVerification extends RecruitmentMail
{
    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('careers.verify', now()->addHours(24), [
            'application' => $this->applicationId, 'hash' => $this->emailHash,
        ]);

        return (new MailMessage)->subject('Verifikasi email lamaran · HRIS Lite')
            ->greeting('Konfirmasikan pengajuan Anda.')
            ->line('Alamat email ini digunakan untuk mengirim lamaran melalui HRIS Lite.')
            ->line('Hanya lanjutkan jika Anda sendiri mengirim lamaran tersebut. Tautan berlaku 24 jam sejak email ini dibuat.')
            ->action('Periksa dan verifikasi email', $url)
            ->line('Jika Anda tidak mengirim lamaran, abaikan email ini. Tidak ada akun yang dibuat.');
    }
}
