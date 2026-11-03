<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrections
{
    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function actor(User $user): User
    {
        DB::table('account_administration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
        $u = User::findOrFail($user->id);
        abort_unless($u->is_active && $u->auth_version === $user->auth_version, 403);

        return $u;
    }

    public function visible(User $u, AttendanceCorrection $c): void
    {
        abort_unless($u->is_active, 403);
        $leave = app(LeaveRecords::class);
        if ($leave->isHr($u)) {
            return;
        }
        abort_if($u->role === 'viewer', 403);
        abort_unless(in_array($u->role, ['employee', 'supervisor'], true) && ($u->employee_id === $c->employee_id || ($u->role === 'supervisor' && $leave->manager($c->employee) === $u->id)), 404);
        $leave->own($u);
    }

    public function actions(User $u, AttendanceCorrection $c): array
    {
        if (! in_array($c->status, AttendanceCorrection::PENDING, true)) {
            return [];
        }
        if ($u->employee_id === $c->employee_id || $u->id === (int) $c->submitted_by) {
            return in_array($u->role, ['employee', 'supervisor'], true) ? ['withdraw'] : [];
        }
        $s = app(LeaveRecords::class);
        if ($s->isHr($u)) {
            // HR may close an invalid request even when an employee becomes inactive.
            return $c->status === 'pending_hr' || $s->manager($c->employee) === null ? ['approve', 'reject'] : ['reject'];
        }

        return $c->status === 'pending_supervisor' && $u->role === 'supervisor' && $s->manager($c->employee) === $u->id ? ['approve', 'reject'] : [];
    }

    public function checkDay(Employee $e, AttendanceDay $day): void
    {
        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        if ($e->trashed() || $e->employment_status->value === 'inactive' || $day->voided_at || $day->date->format('Y-m-d') < $e->join_date->format('Y-m-d') || $day->date->format('Y-m-d') < $today->subDays(30)->toDateString() || $day->scheduled_end > now()) {
            $this->error('correction', 'Koreksi hanya untuk jadwal aktif yang sudah selesai dalam 31 hari terakhir. Hubungi HR untuk catatan lain.');
        }
    }

    private function checkTimes(Employee $e, AttendanceDay $day, CarbonImmutable $in, CarbonImmutable $out): void
    {
        if ($in->isFuture() || $out->isFuture() || $out <= $in || $in->diffInSeconds($out) > 86400 || abs($day->scheduled_start->diffInSeconds($in, false)) > 86400) {
            $this->error('correction', 'Periksa usulan jam: bukan masa depan, pulang setelah masuk, durasi maksimal 24 jam, masuk dalam 24 jam dari awal jadwal.');
        }
        if (LeaveRequest::where('employee_id', $e->id)->whereIn('status', ['approved', 'cancel_pending'])->whereJsonContains('work_dates', $day->date->format('Y-m-d'))->exists()) {
            $this->error('correction', 'Ada cuti atau izin disetujui pada tanggal ini. Hubungi HR untuk memeriksanya.');
        }
        if (AttendanceDay::where('employee_id', $e->id)->whereKeyNot($day->id)->whereNull('voided_at')->whereNotNull('clock_in')->where('clock_in', '<', $out)->where(fn ($q) => $q->whereNull('clock_out')->orWhere('clock_out', '>', $in))->exists()) {
            $this->error('correction', 'Usulan bertumpuk dengan catatan hadir lain atau jam masuk lain belum ditutup.');
        }
    }

    private function audit(User $u, AttendanceCorrection $c, string $action, string $reason, ?string $before): void
    {
        DB::table('attendance_correction_events')->insert(['attendance_correction_id' => $c->id, 'actor_id' => $u->id, 'action' => $action, 'changes' => json_encode(['before' => $before, 'after' => $c->status, 'reason' => $reason], JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function submit(User $user, array $data): AttendanceCorrection
    {
        return DB::transaction(function () use ($user, $data) {
            $u = $this->actor($user);
            $e = app(AttendancePunches::class)->employee($u, true);
            $day = AttendanceDay::where('employee_id', $e->id)->whereKey($data['day_id'])->lockForUpdate()->firstOrFail();
            $this->checkDay($e, $day);
            if ($day->version !== (int) $data['day_version']) {
                $this->error('version', 'Catatan absensi berubah. Buka kembali kalender dan periksa sebelum mengajukan.');
            }
            if (AttendanceCorrection::where('attendance_day_id', $day->id)->whereIn('status', AttendanceCorrection::PENDING)->exists()) {
                $this->error('correction', 'Masih ada pengajuan untuk tanggal ini. Tunggu keputusan atau tarik pengajuan lama.');
            }
            $times = [];
            foreach (['clock_in', 'clock_out'] as $field) {
                $times[$field] = $day->$field?->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i') === $data[$field] ? $day->$field : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$field], 'Asia/Jakarta')->utc();
            }
            $this->checkTimes($e, $day, $times['clock_in'], $times['clock_out']);
            if ($day->clock_in?->equalTo($times['clock_in']) && $day->clock_out?->equalTo($times['clock_out'])) {
                $this->error('correction', 'Usulan sama dengan catatan saat ini. Tidak ada waktu yang perlu dikoreksi.');
            }
            $original = [...$day->forDisplay(), 'raw_clock_in' => $day->clock_in?->toIso8601String(), 'raw_clock_out' => $day->clock_out?->toIso8601String()];
            $c = AttendanceCorrection::create(['attendance_day_id' => $day->id, 'employee_id' => $e->id, 'submitted_by' => $u->id, 'day_version' => $day->version, 'status' => app(LeaveRecords::class)->manager($e) ? 'pending_supervisor' : 'pending_hr', 'original' => $original, ...$times, 'reason' => $data['reason']]);
            $this->audit($u, $c, 'submitted', $data['reason'], null);

            return $c;
        });
    }

    public function decide(User $user, AttendanceCorrection $request, array $data): void
    {
        DB::transaction(function () use ($user, $request, $data) {
            $u = $this->actor($user);
            $e = Employee::withTrashed()->whereKey($request->employee_id)->lockForUpdate()->firstOrFail();
            $day = AttendanceDay::whereKey($request->attendance_day_id)->lockForUpdate()->firstOrFail();
            $c = AttendanceCorrection::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $c->setRelation('employee', $e);
            $this->visible($u, $c);
            if ($c->version !== (int) $data['version']) {
                $this->error('version', 'Pengajuan sudah berubah. Muat ulang dan periksa keputusan terbaru.');
            }
            if (! in_array($data['action'], $this->actions($u, $c), true)) {
                $this->error('correction', 'Tindakan ini tidak tersedia untuk Anda atau tahap pengajuan saat ini.');
            }
            $before = $c->status;
            $final = $data['action'] === 'approve' && app(LeaveRecords::class)->isHr($u);
            if ($data['action'] === 'approve') {
                // The 31-day deadline applies when submitting, not while waiting for approval.
                if ($e->trashed() || $e->employment_status->value === 'inactive' || $day->voided_at) {
                    $this->error('correction', 'Pegawai atau jadwal tidak aktif. Tolak pengajuan dan periksa bersama HR.');
                }
                if ($day->version !== $c->day_version) {
                    $this->error('version', 'Catatan absensi berubah sejak pengajuan. Tolak dengan alasan agar pegawai dapat mengajukan ulang.');
                }
                $this->checkTimes($e, $day, $c->clock_in, $c->clock_out);
            }
            if ($final) {
                app(AttendanceRecords::class)->save($u, $e, ['date' => $day->date->format('Y-m-d'), 'version' => $day->version, 'action' => 'save', 'scheduled_start' => $day->scheduled_start->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'scheduled_end' => $day->scheduled_end->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'clock_in' => $c->clock_in->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'clock_out' => $c->clock_out->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'tolerance_minutes' => $day->tolerance_minutes, 'reason' => 'Persetujuan koreksi #'.$c->id.': '.$data['reason']]);
            }
            $c->status = match ($data['action']) {
                'withdraw' => 'withdrawn', 'reject' => 'rejected', default => $final ? 'approved' : 'pending_hr'
            };
            $c->version++;
            $c->save();
            $this->audit($u, $c, $data['action'], $data['reason'], $before);
        });
    }
}
