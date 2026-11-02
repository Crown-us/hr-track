<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ApplicationReceived extends RecruitmentMail
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Lamaran diterima · HRIS Lite')
            ->greeting('Email lamaran telah diverifikasi.')
            ->line('Lamaran Anda untuk posisi '.$this->application()?->vacancy?->job_title.' telah diterima sistem untuk diperiksa tim HR.')
            ->line('Bukti ini bukan pernyataan lolos seleksi atau penawaran kerja. Tim HR akan menghubungi Anda untuk tindak lanjut jika diperlukan.')
            ->line('Anda tidak perlu membuat akun atau mengirim ulang CV untuk lamaran yang sama.');
    }
}
