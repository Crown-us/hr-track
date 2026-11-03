<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceRecords
{
    public function isHr(User $u): bool
    {
        return in_array($u->role, ['admin', 'hr_staff'], true);
    }

    public function authorize(User $u, Employee $e): void
    {
        abort_unless($u->is_active, 403);
        if ($this->isHr($u)) {
            return;
        }
        abort_if($u->role === 'viewer', 403);
        abort_unless(in_array($u->role, ['employee', 'supervisor'], true), 403);
        $own = Employee::whereKey($u->employee_id)->where('employment_status', '!=', 'inactive')->first();
        abort_unless($own && ! $e->trashed() && $e->employment_status->value !== 'inactive' && ($e->id === $own->id || ($u->role === 'supervisor' && $e->manager_employee_id === $own->id)), 404);
    }

    private function error(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function save(User $user, Employee $employee, array $data): void
    {
        DB::transaction(function () use ($user, $employee, $data) {
            DB::table('account_administration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
            $u = User::findOrFail($user->id);
            abort_unless($u->is_active && $u->auth_version === $user->auth_version && $this->isHr($u), 403);
            $e = Employee::withTrashed()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($e->trashed() || $e->employment_status->value === 'inactive') {
                $this->error('employee', 'Pencatatan hanya untuk pegawai aktif yang belum diarsipkan.');
            }
            if ($data['date'] < $e->join_date->format('Y-m-d')) {
                $this->error('date', 'Tanggal tidak boleh sebelum pegawai bergabung.');
            }
            $day = AttendanceDay::where('employee_id', $e->id)->where('date', $data['date'])->lockForUpdate()->first();
            if (($day?->version ?? 0) !== (int) $data['version']) {
                $this->error('version', 'Catatan sudah berubah. Halaman dimuat ulang; periksa data sebelum mengisi kembali.');
            }
            $before = $day?->forDisplay();
            if ($data['action'] === 'void') {
                if (! $day || $day->voided_at) {
                    $this->error('record', 'Tidak ada catatan aktif untuk dibatalkan.');
                }
                $day->voided_at = now();
            } else {
                $times = [];
                foreach (['scheduled_start', 'scheduled_end', 'clock_in', 'clock_out'] as $field) {
                    $times[$field] = empty($data[$field]) ? null : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$field], 'Asia/Jakarta')->utc();
                }
                $day ??= new AttendanceDay(['employee_id' => $e->id, 'date' => $data['date']]);
                foreach (['clock_in', 'clock_out'] as $field) {
                    // The HR form has minute precision; preserve web seconds/source when unchanged.
                    if (($before[$field] ?? null) === ($data[$field] ?: null)) {
                        $times[$field] = $day->$field;
                    } else {
                        $day->{$field.'_source'} = $times[$field] ? 'hr' : null;
                    }
                }
                $start = $times['scheduled_start'];
                $end = $times['scheduled_end'];
                $in = $times['clock_in'];
                $out = $times['clock_out'];
                if ($start->setTimezone('Asia/Jakarta')->toDateString() !== $data['date'] || $end <= $start || $start->diffInSeconds($end) > 86400) {
                    $this->error('scheduled_end', 'Jadwal dimulai pada tanggal kerja, durasi lebih dari 0 sampai 24 jam; akhir boleh hari berikutnya.');
                }
                if (($in && ($in->isFuture() || abs($start->diffInSeconds($in, false)) > 86400)) || ($out && (! $in || $out < $in || $out->isFuture() || $in->diffInSeconds($out) > 86400))) {
                    $this->error('clock_in', 'Periksa jam masuk/pulang: bukan masa depan, pulang setelah masuk, durasi maksimal 24 jam, masuk dalam 24 jam dari awal jadwal.');
                }
                if ($in && $out && AttendanceDay::where('employee_id', $e->id)->whereNull('voided_at')->where('date', '!=', $data['date'])->where('clock_in', '<', $out)->where('clock_out', '>', $in)->exists()) {
                    $this->error('clock_in', 'Jam hadir bertumpuk dengan catatan tanggal kerja lain.');
                }

                $day->fill([...$times, 'tolerance_minutes' => $data['tolerance_minutes'], 'voided_at' => null]);
            }
            $day->version = ($before['version'] ?? 0) + 1;
            $day->save();
            DB::table('attendance_events')->insert(['attendance_day_id' => $day->id, 'actor_id' => $u->id, 'action' => $data['action'] === 'void' ? 'voided' : ($before ? 'corrected' : 'recorded'), 'changes' => json_encode(['before' => $before, 'after' => $day->forDisplay(), 'reason' => $data['reason']], JSON_THROW_ON_ERROR), 'created_at' => now()]);
        });
    }
}
