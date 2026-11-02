<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplicationWorkflow
{
    public function create(array $data, ?UploadedFile $file, User $actor): Application
    {
        $path = null;
        try {
            return DB::transaction(function () use ($data, $file, $actor, &$path) {
                $vacancy = Vacancy::open()->whereKey($data['vacancy_id'])->lockForUpdate()->first();
                if (! $vacancy) {
                    throw ValidationException::withMessages(['vacancy_id' => 'Lowongan belum dibuka, sudah ditutup atau melewati periode penerimaan.']);
                }
                if ($data['application_date'] < $vacancy->opening_date->format('Y-m-d') || $data['application_date'] > $vacancy->closing_date->format('Y-m-d')) {
                    throw ValidationException::withMessages(['application_date' => 'Tanggal lamaran harus berada dalam periode lowongan.']);
                }
                $applicant = $data['mode'] === 'existing' ? Applicant::whereKey($data['applicant_id'])->lockForUpdate()->firstOrFail() : Applicant::create(['full_name' => $data['full_name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
                if (Application::withTrashed()->where('applicant_id', $applicant->id)->where('vacancy_id', $vacancy->id)->exists()) {
                    throw ValidationException::withMessages(['vacancy_id' => 'Pelamar sudah memiliki lamaran untuk lowongan ini.']);
                }
                $application = Application::create(['applicant_id' => $applicant->id, 'vacancy_id' => $vacancy->id, 'application_date' => $data['application_date'], 'notes' => $data['notes'] ?? null]);
                $this->history($application, $actor, null, ApplicationStatus::Screening, null);
                if ($file) {
                    $this->storeDocument($applicant->id, $file, $actor, $path);
                }

                return $application;
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('applicant_documents')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages([$data['mode'] === 'new' ? 'email' : 'vacancy_id' => 'Profil atau lamaran sudah tercatat. Muat ulang dan pilih profil terdaftar.']);
            }
            throw $exception;
        }
    }

    public function transition(Application $application, array $data, User $actor): void
    {
        DB::transaction(function () use ($application, $data, $actor) {
            $record = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if ($record->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Tahap sudah berubah. Muat ulang detail sebelum melanjutkan.']);
            }
            $next = ApplicationStatus::from($data['status']);
            if (! in_array($next, $record->status->next(), true)) {
                throw ValidationException::withMessages(['status' => 'Perpindahan tahap tidak diizinkan. Diterima/Ditolak merupakan tahap akhir.']);
            }
            $this->history($record, $actor, $record->status, $next, $data['note'] ?? null);
            $record->status = $next;
            $record->version++;
            $record->save();
        });
    }

    public function upload(Application $application, UploadedFile $file, User $actor): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($application, $file, $actor, &$path) {
                $applicant = Applicant::whereKey($application->applicant_id)->lockForUpdate()->firstOrFail();
                $this->storeDocument($applicant->id, $file, $actor, $path);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('applicant_documents')->delete($path);
            } throw $exception;
        }
    }

    private function storeDocument(int $applicantId, UploadedFile $file, User $actor, ?string &$path): void
    {
        $path = $file->store('applicants/'.$applicantId, 'applicant_documents');
        ApplicantDocument::create(['applicant_id' => $applicantId, 'uploaded_by' => $actor->id, 'path' => $path, 'size_bytes' => $file->getSize()]);
    }

    private function history(Application $application, User $actor, ?ApplicationStatus $old, ApplicationStatus $next, ?string $note): void
    {
        ApplicationStatusHistory::create(['application_id' => $application->id, 'actor_id' => $actor->id, 'old_status' => $old?->value, 'new_status' => $next->value, 'note' => $note, 'created_at' => now()]);
    }

    public function summary(): array
    {
        $query = Application::whereHas('applicant');
        $counts = array_fill_keys(array_column(ApplicationStatus::cases(), 'value'), 0);
        foreach ((clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get() as $group) {
            $counts[$group->status->value] = (int) $group->total;
        }

        return ['total' => (clone $query)->count(), 'applicants' => (clone $query)->distinct()->count('applicant_id'), 'counts' => $counts,
            'recent' => (clone $query)->with(['applicant', 'vacancy'])->latest('id')->limit(5)->get()->map(fn ($application) => $application->forDisplay())];
    }
}
