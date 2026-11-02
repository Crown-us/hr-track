<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingMaterial;
use App\Models\TrainingParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrainingRecords
{
    private function record(Training $training, ?int $version = null): Training
    {
        $t = Training::whereKey($training->id)->lockForUpdate()->firstOrFail();
        if ($version !== null && $t->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Data sudah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }
        if ($t->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Pelatihan yang dibatalkan tidak dapat diubah.']);
        }

        return $t;
    }

    private function audit(Training $t, User $actor, string $action, array $changes): void
    {
        DB::table('training_events')->insert(['training_id' => $t->id, 'actor_id' => $actor->id, 'action' => $action, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function save(?Training $training, array $data, User $actor): Training
    {
        return DB::transaction(function () use ($training, $data, $actor) {
            $t = $training ? $this->record($training, (int) $data['version']) : new Training(['created_by' => $actor->id]);
            if ($training && $t->status !== 'scheduled') {
                throw ValidationException::withMessages(['status' => 'Jadwal pelatihan yang selesai tidak dapat diubah.']);
            }
            $before = $training ? $t->only('title', 'trainer', 'location', 'notes', 'starts_at', 'ends_at') : [];
            $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['starts_at'], 'Asia/Jakarta')->utc();
            $end = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['ends_at'], 'Asia/Jakarta')->utc();
            if ($training && (! $t->starts_at->equalTo($start) || ! $t->ends_at->equalTo($end))) {
                $hasScores = $t->participants()->where(fn ($q) => $q->whereNotNull('pre_test')->orWhereNotNull('post_test'))->exists();
                $hadScores = DB::table('training_events')->where('training_id', $t->id)->where('action', 'evaluated')
                    ->whereRaw("(JSON_TYPE(JSON_EXTRACT(changes, '$.after.pre_test')) IN ('INTEGER', 'DOUBLE') OR JSON_TYPE(JSON_EXTRACT(changes, '$.after.post_test')) IN ('INTEGER', 'DOUBLE'))")->exists();
                if ($hasScores || $hadScores) {
                    throw ValidationException::withMessages(['starts_at' => 'Waktu sesi tidak dapat diubah setelah nilai pernah dicatat.']);
                }
            }
            $t->fill(array_intersect_key($data, array_flip(['title', 'trainer', 'location', 'notes'])));
            $t->starts_at = $start;
            $t->ends_at = $end;
            if ($training) {
                $t->version++;
            }
            $t->save();
            $this->audit($t, $actor, $training ? 'updated' : 'created', ['before' => $before, 'after' => $t->only('title', 'trainer', 'location', 'notes', 'starts_at', 'ends_at')]);

            return $t;
        });
    }

    public function enroll(Training $training, array $data, User $actor): void
    {
        DB::transaction(function () use ($training, $data, $actor) {
            $t = $this->record($training, (int) $data['version']);
            if ($t->status !== 'scheduled') {
                throw ValidationException::withMessages(['status' => 'Peserta hanya dapat ditambahkan sebelum pelatihan ditandai selesai.']);
            }
            $employee = Employee::whereKey($data['employee_id'])->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
            if (! $employee || TrainingParticipant::where('training_id', $t->id)->where('employee_id', $employee->id)->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'Pegawai tidak aktif atau sudah terdaftar pada pelatihan ini.']);
            }
            $p = TrainingParticipant::create(['training_id' => $t->id, 'employee_id' => $employee->id]);
            $t->increment('version');
            $this->audit($t, $actor, 'enrolled', ['participant_id' => $p->id, 'employee_id' => $employee->id]);
        });
    }

    public function evaluate(Training $training, TrainingParticipant $participant, array $data, User $actor): void
    {
        DB::transaction(function () use ($training, $participant, $data, $actor) {
            $t = $this->record($training);
            $p = TrainingParticipant::whereKey($participant->id)->where('training_id', $t->id)->lockForUpdate()->firstOrFail();
            if ($p->version !== (int) $data['version']) {
                throw ValidationException::withMessages(['version' => 'Nilai sudah berubah. Muat ulang halaman.']);
            }
            if (($data['post_test'] ?? null) !== null) {
                if (($data['pre_test'] ?? null) === null) {
                    throw ValidationException::withMessages(['pre_test' => 'Isi pre-test sebelum mencatat post-test.']);
                }
                if ($t->ends_at->isFuture() || $data['attendance'] !== 'attended') {
                    throw ValidationException::withMessages(['post_test' => 'Post-test hanya untuk peserta hadir setelah pelatihan berakhir.']);
                }
            }
            $before = $p->only('attendance', 'pre_test', 'post_test', 'notes');
            $p->fill(['attendance' => $data['attendance'], 'pre_test' => $data['pre_test'] ?? null, 'post_test' => $data['post_test'] ?? null, 'notes' => $data['notes'] ?? null]);
            $p->version++;
            $p->save();
            $this->audit($t, $actor, 'evaluated', ['participant_id' => $p->id, 'before' => $before, 'after' => $p->only('attendance', 'pre_test', 'post_test', 'notes')]);
        });
    }

    public function status(Training $training, array $data, User $actor): void
    {
        DB::transaction(function () use ($training, $data, $actor) {
            $t = $this->record($training, (int) $data['version']);
            if ($t->status !== 'scheduled' || ($data['status'] === 'completed' && $t->ends_at->isFuture())) {
                throw ValidationException::withMessages(['status' => 'Pelatihan hanya dapat diselesaikan setelah waktu sesi berakhir. Status akhir tidak dapat diubah.']);
            }
            $t->status = $data['status'];
            $t->version++;
            $t->save();
            $this->audit($t, $actor, $data['status'], ['reason' => $data['reason'] ?? null]);
        });
    }

    public function upload(Training $training, UploadedFile $file, User $actor): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($training, $file, $actor, &$path) {
                $t = $this->record($training);
                $version = (int) TrainingMaterial::where('training_id', $t->id)->max('version') + 1;
                $path = $file->store('trainings/'.$t->id, 'training_materials');
                $m = TrainingMaterial::create(['training_id' => $t->id, 'uploaded_by' => $actor->id, 'version' => $version, 'path' => $path, 'size_bytes' => $file->getSize(), 'created_at' => now()]);
                $this->audit($t, $actor, 'material_uploaded', ['material_id' => $m->id, 'version' => $version]);
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('training_materials')->delete($path);
            } throw $e;
        }
    }

    public function summary(Training $training, bool $private): array
    {
        $total = $training->participants()->count();
        if (! $private) {
            return ['registered' => $total];
        }
        $pairs = $training->participants()->where('attendance', 'attended')->whereNotNull('pre_test')->whereNotNull('post_test')->selectRaw('COUNT(*) as evaluated, AVG(pre_test) as average_pre, AVG(post_test) as average_post, AVG(post_test-pre_test) as average_gain')->first();

        return ['registered' => $total, 'evaluated' => (int) $pairs->evaluated, ...collect(['average_pre', 'average_post', 'average_gain'])->mapWithKeys(fn ($key) => [$key => $pairs->$key === null ? null : round((float) $pairs->$key, 2)])->all()];
    }
}
