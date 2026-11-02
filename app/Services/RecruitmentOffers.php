<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Employee;
use App\Models\RecruitmentOffer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecruitmentOffers
{
    private function application(Application $application, int $version): Application
    {
        $a = Application::whereKey($application->id)->lockForUpdate()->firstOrFail();
        if (! $a->applicant || ($a->source === 'public' && ! $a->email_verified_at)) {
            throw ValidationException::withMessages(['status' => 'Profil harus aktif dan email lamaran publik harus terverifikasi.']);
        }
        if ($a->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Data sudah berubah. Muat ulang halaman.']);
        }

        return $a;
    }

    private function audit(RecruitmentOffer $offer, User $actor, string $action, array $before, array $extra = []): void
    {
        DB::table('recruitment_offer_events')->insert([
            'recruitment_offer_id' => $offer->id, 'actor_id' => $actor->id, 'action' => $action,
            'changes' => json_encode(['before' => $before, 'after' => $offer->only(['department_id', 'position_id', 'employment_status', 'join_date', 'response_deadline', 'terms', 'status', 'response_date', 'response_note']), ...$extra], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public function save(Application $application, array $data, User $actor): void
    {
        DB::transaction(function () use ($application, $data, $actor) {
            $a = $this->application($application, (int) $data['version']);
            $offer = RecruitmentOffer::where('application_id', $a->id)->lockForUpdate()->first();
            if ($a->status !== ApplicationStatus::Offering || ($offer && $offer->status !== 'pending')) {
                throw ValidationException::withMessages(['status' => 'Penawaran hanya dapat disimpan pada tahap Offering sebelum ada respons.']);
            }
            $before = $offer?->toArray() ?? [];
            unset($data['version']);
            $created = ! $offer;
            $offer ??= new RecruitmentOffer(['application_id' => $a->id]);
            $offer->fill($data);
            $offer->updated_at = now();
            $offer->save();
            $this->audit($offer, $actor, $created ? 'created' : 'revised', $before);
            $a->increment('version');
        });
    }

    public function respond(Application $application, array $data, UploadedFile $file, User $actor): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($application, $data, $file, $actor, &$path) {
                $a = $this->application($application, (int) $data['version']);
                $offer = RecruitmentOffer::where('application_id', $a->id)->lockForUpdate()->first();
                if ($a->status !== ApplicationStatus::Offering || ! $offer || $offer->status !== 'pending') {
                    throw ValidationException::withMessages(['response' => 'Penawaran tidak tersedia atau respons sudah dicatat.']);
                }
                if ($data['response_date'] < $offer->updated_at->timezone('Asia/Jakarta')->toDateString()) {
                    throw ValidationException::withMessages(['response_date' => 'Tanggal respons tidak boleh sebelum penawaran terakhir dibuat atau direvisi.']);
                }
                if ($data['response'] === 'accepted' && $data['response_date'] > $offer->response_deadline->toDateString()) {
                    throw ValidationException::withMessages(['response_date' => 'Respons melewati batas penawaran. Revisi penawaran sebelum menerima respons baru.']);
                }
                $before = $offer->toArray();
                $path = $file->store('offer-responses/'.$a->id, 'employee_records');
                $offer->fill(['status' => $data['response'], 'response_date' => $data['response_date'], 'response_note' => $data['response_note'], 'evidence_path' => $path, 'evidence_size' => $file->getSize()])->save();
                app(ApplicationWorkflow::class)->transition($a, ['version' => $a->version, 'status' => $data['response'] === 'accepted' ? 'accepted' : 'rejected', 'note' => 'Respons penawaran: '.$data['response'].'. '.$data['response_note']], $actor);
                $this->audit($offer, $actor, 'responded', $before);
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('employee_records')->delete($path);
            }
            throw $e;
        }
    }

    public function convert(Application $application, array $data, User $actor): Employee
    {
        return DB::transaction(function () use ($application, $data, $actor) {
            $a = $this->application($application, (int) $data['version']);
            $offer = RecruitmentOffer::where('application_id', $a->id)->lockForUpdate()->first();
            if ($a->status !== ApplicationStatus::Accepted || ! $offer || $offer->status !== 'accepted') {
                throw ValidationException::withMessages(['status' => 'Konversi memerlukan lamaran dan penawaran yang diterima.']);
            }
            Applicant::whereKey($a->applicant_id)->lockForUpdate()->firstOrFail();
            if (Employee::withTrashed()->where('applicant_id', $a->applicant_id)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['employee_number' => 'Profil ini sudah terhubung ke pegawai, termasuk yang diarsipkan. Tidak membuat pegawai baru.']);
            }
            $employee = app(EmployeeRecords::class)->save(null, [
                ...array_intersect_key($data, array_flip(['employee_number', 'full_name', 'work_email', 'phone'])),
                ...$offer->only(['department_id', 'position_id', 'employment_status']),
                'join_date' => $offer->join_date->toDateString(),
            ], $actor);
            $employee->applicant_id = $a->applicant_id;
            $employee->source_application_id = $a->id;
            $employee->save();
            $this->audit($offer, $actor, 'converted', [], ['employee_id' => $employee->id]);

            return $employee;
        });
    }
}
