<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Vacancy;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicApplications
{
    public function submit(Vacancy $vacancy, array $data, UploadedFile $file): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $path = null;
            try {
                DB::transaction(function () use ($vacancy, $data, $file, &$path) {
                    $job = Vacancy::open()->whereKey($vacancy->id)->lockForUpdate()->first();
                    if (! $job) {
                        throw ValidationException::withMessages(['submission' => 'Lowongan ini tidak lagi menerima lamaran.']);
                    }$email = mb_strtolower(trim($data['email']));
                    $person = Applicant::withTrashed()->where('email', $email)->lockForUpdate()->first();
                    if ($person?->trashed()) {
                        return;
                    }if (! $person) {
                        $person = Applicant::create(['full_name' => $data['full_name'], 'email' => $email, 'phone' => $data['phone'] ?? null]);
                    }if (Application::withTrashed()->where('applicant_id', $person->id)->where('vacancy_id', $job->id)->exists()) {
                        return;
                    }$a = new Application;
                    $a->applicant_id = $person->id;
                    $a->vacancy_id = $job->id;
                    $a->application_date = now('Asia/Jakarta')->toDateString();
                    $a->source = 'public';
                    $a->submitted_name = $data['full_name'];
                    $a->submitted_phone = $data['phone'] ?? null;
                    $a->save();
                    ApplicationStatusHistory::create(['application_id' => $a->id, 'actor_id' => null, 'old_status' => null, 'new_status' => 'screening', 'note' => 'Pengajuan dari halaman karier. Kepemilikan email belum diverifikasi.', 'created_at' => now()]);
                    $path = $file->store('applicants/'.$person->id, 'applicant_documents');
                    ApplicantDocument::create(['applicant_id' => $person->id, 'application_id' => $a->id, 'uploaded_by' => null, 'path' => $path, 'size_bytes' => $file->getSize()]);
                    app(ApplicantEmailVerification::class)->send($a);
                });

                return;
            } catch (Throwable $e) {
                if ($path) {
                    Storage::disk('applicant_documents')->delete($path);
                }
                $retryable = $e instanceof UniqueConstraintViolationException || $e instanceof DeadlockException || ($e instanceof QueryException && in_array($e->errorInfo[1] ?? null, [1205, 1213], true));
                if ($retryable && $attempt < 2) {
                    continue;
                }
                if ($retryable) {
                    throw ValidationException::withMessages(['submission' => 'Pengajuan belum dapat diproses saat ini. Silakan coba kembali.']);
                }
                throw $e;
            }
        }
    }
}
