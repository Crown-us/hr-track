<?php

namespace App\Services;

use App\Models\DisciplinaryCase;
use App\Models\DisciplineDocument;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DisciplineRecords
{
    private function audit(DisciplinaryCase $c, User $u, string $action, array $changes): void
    {
        DB::table('discipline_events')->insert(['disciplinary_case_id' => $c->id, 'actor_id' => $u->id, 'action' => $action, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function locked(DisciplinaryCase $case, ?int $version = null): DisciplinaryCase
    {
        Employee::withTrashed()->whereKey($case->employee_id)->lockForUpdate()->firstOrFail();
        $c = DisciplinaryCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
        if ($version !== null && $version !== $c->version) {
            throw ValidationException::withMessages(['version' => 'Data sudah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }

        return $c;
    }

    private function open(DisciplinaryCase $c): void
    {
        if ($c->status !== 'open') {
            throw ValidationException::withMessages(['case' => 'Catatan selesai atau dibatalkan tidak dapat diubah.']);
        }
    }

    private function attach(DisciplinaryCase $c, UploadedFile $file, User $u, ?string &$path): void
    {
        $version = (int) DisciplineDocument::where('disciplinary_case_id', $c->id)->max('version') + 1;
        $path = $file->store('cases/'.$c->id, 'discipline_documents');
        $d = DisciplineDocument::create(['disciplinary_case_id' => $c->id, 'uploaded_by' => $u->id, 'version' => $version, 'path' => $path, 'size_bytes' => $file->getSize(), 'created_at' => now()]);
        $this->audit($c, $u, 'document_uploaded', ['document_id' => $d->id, 'version' => $version]);
    }

    public function save(?DisciplinaryCase $case, array $data, ?UploadedFile $file, User $u): DisciplinaryCase
    {
        $path = null;
        try {
            return DB::transaction(function () use ($case, $data, $file, $u, &$path) {
                if ($case) {
                    $c = $this->locked($case, (int) $data['version']);
                    $this->open($c);
                    foreach (['employee_id', 'kind'] as $field) {
                        if ((string) $c->$field !== (string) $data[$field]) {
                            throw ValidationException::withMessages([$field => 'Pegawai dan jenis catatan tidak dapat diganti.']);
                        }
                    }
                } else {
                    $e = Employee::whereKey($data['employee_id'])->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
                    if (! $e) {
                        throw ValidationException::withMessages(['employee_id' => 'Pilih pegawai aktif yang belum diarsipkan.']);
                    }
                    if (in_array($data['kind'], ['sp1', 'sp2', 'sp3'], true) && ! $file) {
                        throw ValidationException::withMessages(['document' => 'Lampirkan PDF surat peringatan.']);
                    }
                    $c = new DisciplinaryCase(['employee_id' => $e->id, 'kind' => $data['kind'], 'created_by' => $u->id]);
                }
                $fields = ['reference_number', 'incident_on', 'subject', 'description', 'follow_up_on'];
                $before = $case ? $c->only($fields) : [];
                $c->fill([...array_intersect_key($data, array_flip($fields)), 'follow_up_on' => $data['follow_up_on'] ?? null]);
                if ($case) {
                    $c->version++;
                }
                $c->save();
                if ($file) {
                    $this->attach($c, $file, $u, $path);
                }
                $this->audit($c, $u, $case ? 'updated' : 'created', ['before' => $before, 'after' => $c->only($fields), 'reason' => $data['reason'] ?? null]);

                return $c;
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('discipline_documents')->delete($path);
            }
            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['reference_number' => 'Nomor catatan sudah digunakan.']);
            }
            throw $e;
        }
    }

    public function status(DisciplinaryCase $case, array $data, User $u): void
    {
        DB::transaction(function () use ($case, $data, $u) {
            $c = $this->locked($case, (int) $data['version']);
            if ($c->status === 'voided' || ($c->status === 'resolved' && $data['status'] !== 'voided')) {
                throw ValidationException::withMessages(['case' => 'Status akhir catatan tidak dapat dibuka kembali.']);
            }
            $before = $c->status;
            $c->status = $data['status'];
            $c->version++;
            $c->save();
            $this->audit($c, $u, $c->status, ['before' => $before, 'after' => $c->status, 'reason' => $data['reason']]);
        });
    }

    public function upload(DisciplinaryCase $case, UploadedFile $file, User $u): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($case, $file, $u, &$path) {
                $c = $this->locked($case);
                $this->open($c);
                $this->attach($c, $file, $u, $path);
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('discipline_documents')->delete($path);
            }
            throw $e;
        }
    }
}
