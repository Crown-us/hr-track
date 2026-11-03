<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendancePunches
{
    public function employee(User $u, bool $lock = false): Employee
    {
        abort_unless($u->is_active && in_array($u->role, ['employee', 'supervisor'], true), 403);
        $query = Employee::whereKey($u->employee_id)->where('employment_status', '!=', 'inactive');
        $e = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($e && $e->join_date->format('Y-m-d') <= now('Asia/Jakarta')->toDateString(), 403);

        return $e;
    }

    private function leave(Employee $e, AttendanceDay $day): bool
    {
        return LeaveRequest::where('employee_id', $e->id)->whereIn('status', ['approved', 'cancel_pending'])->whereJsonContains('work_dates', $day->date->format('Y-m-d'))->exists();
    }

    private function deadline(AttendanceDay $d): CarbonImmutable
    {
        $end = $d->scheduled_end->addHours(4);

        return $d->clock_in && $d->clock_in->addHours(24) < $end ? $d->clock_in->addHours(24) : $end;
    }

    private function blocked(Employee $e, AttendanceDay $d, string $action, CarbonImmutable $now): ?string
    {
        if ($d->voided_at || $d->date->format('Y-m-d') < $e->join_date->format('Y-m-d')) {
            return 'Jadwal tidak aktif. Hubungi HR untuk memeriksa catatan.';
        }
        if ($action === 'in') {
            if ($d->clock_in || $d->clock_out) {
                return 'Jam masuk sudah tercatat. Koreksi hanya melalui HR.';
            }
            if ($now < $d->scheduled_start->subHour() || $now >= $d->scheduled_end) {
                return 'Masuk dibuka 60 menit sebelum awal sampai sebelum akhir jadwal.';
            }
            if ($this->leave($e, $d)) {
                return 'Ada cuti atau izin disetujui pada tanggal kerja ini. Hubungi HR jika jadwal berubah.';
            }
        } elseif (! $d->clock_in || $d->clock_out) {
            return 'Pulang memerlukan jam masuk dan belum pernah dicatat.';
        } elseif ($now < $d->clock_in || $now > $this->deadline($d)) {
            return 'Batas pencatatan pulang sudah lewat atau jam masuk perlu diperiksa. Hubungi HR.';
        }
        $others = AttendanceDay::where('employee_id', $e->id)->whereKeyNot($d->id)->whereNull('voided_at')->whereNotNull('clock_in');
        if ($action === 'in') {
            if ($others->where(fn ($q) => $q->whereNull('clock_out')->orWhere(fn ($q) => $q->where('clock_in', '<=', $now)->where('clock_out', '>', $now)))->exists()) {
                return 'Masih ada jam masuk lain yang belum ditutup atau bertumpuk. Hubungi HR.';
            }
        } elseif ($others->where('clock_in', '<', $now)->where(fn ($q) => $q->whereNull('clock_out')->orWhere('clock_out', '>', $d->clock_in))->exists()) {
            return 'Jam hadir bertumpuk dengan catatan lain. Hubungi HR.';
        }

        return null;
    }

    public function panel(User $user): array
    {
        $e = $this->employee($user);
        $now = CarbonImmutable::now('UTC');
        $today = $now->setTimezone('Asia/Jakarta')->toDateString();
        $days = AttendanceDay::where('employee_id', $e->id)->whereNull('voided_at')->where(fn ($q) => $q->where('date', $today)->orWhere(fn ($q) => $q->where('scheduled_end', '>=', $now->subHours(4))->where('scheduled_start', '<=', $now->addHour())))->orderBy('scheduled_start')->get();

        return ['server_now' => $now->toIso8601String(), 'days' => $days->map(function ($d) use ($e, $now) {
            $action = $d->clock_in ? 'out' : 'in';
            $blocked = $this->blocked($e, $d, $action, $now);

            return [...$d->forDisplay(), 'date' => $d->date->format('Y-m-d'), 'can_in' => $action === 'in' && ! $blocked, 'can_out' => $action === 'out' && ! $blocked, 'message' => $d->clock_out ? 'Kehadiran sudah lengkap.' : ($blocked ?? ($action === 'in' ? 'Siap mencatat masuk.' : 'Jam masuk tersimpan. Catat pulang setelah selesai bekerja.')), 'checkout_deadline' => $this->deadline($d)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i')];
        })->all()];
    }

    public function record(User $user, array $data): string
    {
        return DB::transaction(function () use ($user, $data) {
            DB::table('account_administration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
            $u = User::findOrFail($user->id);
            abort_unless($u->auth_version === $user->auth_version, 403);
            $e = $this->employee($u, true);
            $d = AttendanceDay::where('employee_id', $e->id)->whereKey($data['day_id'])->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $error = $d->version !== (int) $data['version'] ? 'Catatan sudah berubah. Periksa ulang sebelum mencatat kehadiran.' : $this->blocked($e, $d, $data['action'], $now);
            if ($error) {
                throw ValidationException::withMessages(['punch' => $error]);
            }
            $before = $d->forDisplay();
            $field = $data['action'] === 'in' ? 'clock_in' : 'clock_out';
            $d->forceFill([$field => $now, $field.'_source' => 'web', 'version' => $d->version + 1])->save();
            DB::table('attendance_events')->insert(['attendance_day_id' => $d->id, 'actor_id' => $u->id, 'action' => $data['action'] === 'in' ? 'clocked_in' : 'clocked_out', 'changes' => json_encode(['before' => $before, 'after' => $d->forDisplay(), 'reason' => $data['action'] === 'in' ? 'Masuk dicatat pegawai melalui web.' : 'Pulang dicatat pegawai melalui web.', 'recorded_at' => $now->toIso8601String()], JSON_THROW_ON_ERROR), 'created_at' => $now]);

            return $d->date->format('Y-m-d');
        });
    }
}
