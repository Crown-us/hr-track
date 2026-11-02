<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class LeaveRecords
{
    public function isHr(User $u): bool
    {
        return in_array($u->role, ['admin', 'hr_staff'], true);
    }

    public function own(User $u): Employee
    {
        abort_unless(in_array($u->role, ['employee', 'supervisor'], true) && $u->is_active, 403);
        $e = Employee::whereKey($u->employee_id)->where('employment_status', '!=', 'inactive')->first();
        abort_unless($e, 403, 'Data pegawai belum tersedia. Hubungi Admin.');

        return $e;
    }

    public function manager(Employee $e): ?int
    {
        if ($e->trashed() || $e->employment_status->value === 'inactive' || ! $e->manager_employee_id || $e->manager_employee_id === $e->id) {
            return null;
        }

        $q = Employee::whereKey($e->manager_employee_id)->where('employment_status', '!=', 'inactive');
        if (DB::transactionLevel() > 0) {
            $q->lockForUpdate();
        }
        if (! $q->first()) {
            return null;
        }

        return User::where('employee_id', $e->manager_employee_id)->where('role', 'supervisor')->where('is_active', true)->value('id');
    }

    public function visible(User $u, LeaveRequest $l): void
    {
        abort_unless($u->is_active, 403);
        if ($this->isHr($u)) {
            return;
        }
        if ($u->role === 'supervisor' && $this->manager($l->employee) === $u->id) {
            $this->own($u);

            return;
        }
        abort_unless(in_array($u->role, ['employee', 'supervisor'], true) && $u->employee_id === $l->employee_id, 404);
        $this->own($u);
    }

    public function balance(int $employee, int $year): array
    {
        $allocation = DB::table('leave_balances')->where('employee_id', $employee)->where('year', $year)->first();
        $q = LeaveRequest::where('employee_id', $employee)->where('year', $year)->where('type', 'annual');
        $reserved = (int) (clone $q)->whereIn('status', ['pending_supervisor', 'pending_hr', 'cancel_pending'])->sum('days');
        $used = (int) (clone $q)->where('status', 'approved')->sum('days');
        $days = (int) ($allocation?->days ?? 0);

        return ['year' => $year, 'allocated' => $days, 'reserved' => $reserved, 'used' => $used, 'available' => $days - $reserved - $used, 'version' => (int) ($allocation?->version ?? 0)];
    }

    private function actor(User $u): User
    {
        DB::table('account_administration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
        $fresh = User::findOrFail($u->id);
        abort_unless($fresh->is_active && $fresh->auth_version === $u->auth_version, 403);

        return $fresh;
    }

    private function error(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    private function audit(User $u, string $action, array $changes, ?LeaveRequest $l = null, ?int $employee = null): void
    {
        DB::table('leave_events')->insert(['actor_id' => $u->id, 'employee_id' => $l?->employee_id ?? $employee, 'leave_request_id' => $l?->id, 'action' => $action, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function allocate(User $user, array $d): void
    {
        DB::transaction(function () use ($user, $d) {
            $u = $this->actor($user);
            abort_unless($this->isHr($u), 403);
            $e = Employee::whereKey($d['employee_id'])->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
            if (! $e) {
                $this->error('employee_id', 'Pilih pegawai aktif yang belum diarsipkan.');
            }
            $before = $this->balance($e->id, (int) $d['year']);
            if ($before['version'] !== (int) $d['version']) {
                $this->error('version', 'Saldo sudah berubah. Muat ulang halaman.');
            }
            if ((int) $d['days'] < $before['reserved'] + $before['used']) {
                $this->error('days', 'Alokasi tidak boleh kurang dari hari yang tertahan dan terpakai.');
            }
            DB::table('leave_balances')->updateOrInsert(['employee_id' => $e->id, 'year' => $d['year']], ['days' => $d['days'], 'version' => $before['version'] + 1, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($u, 'balance_set', ['year' => $d['year'], 'before' => $before['allocated'], 'after' => (int) $d['days'], 'reason' => $d['reason']], employee: $e->id);
        });
    }

    public function holiday(User $user, array $d, ?int $remove = null): void
    {
        DB::transaction(function () use ($user, $d, $remove) {
            $u = $this->actor($user);
            abort_unless($this->isHr($u), 403);
            if ($remove !== null) {
                $old = DB::table('leave_holidays')->find($remove);
                abort_unless($old, 404);
                DB::table('leave_holidays')->where('id', $remove)->delete();
                $this->audit($u, 'holiday_removed', ['date' => $old->date, 'name' => $old->name, 'reason' => $d['reason']]);
            } else {
                if (DB::table('leave_holidays')->where('date', $d['date'])->exists()) {
                    $this->error('date', 'Tanggal libur sudah tercatat.');
                }
                DB::table('leave_holidays')->insert(['date' => $d['date'], 'name' => $d['name'], 'created_at' => now()]);
                $this->audit($u, 'holiday_added', $d);
            }
        });
    }

    public function submit(User $user, array $d, ?UploadedFile $file = null): LeaveRequest
    {
        $path = null;
        try {
            return DB::transaction(function () use ($user, $d, $file, &$path) {
                $u = $this->actor($user);
                $e = $this->own($u);
                $e = Employee::whereKey($e->id)->lockForUpdate()->firstOrFail();
                abort_if($e->employment_status->value === 'inactive', 403);
                $start = CarbonImmutable::parse($d['starts_on'], 'Asia/Jakarta')->startOfDay();
                $end = CarbonImmutable::parse($d['ends_on'], 'Asia/Jakarta')->startOfDay();
                $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
                $earliest = $d['type'] === 'annual' ? $today : $today->subDays(7);
                if ($start < $earliest || $start->format('Y-m-d') < $e->join_date->format('Y-m-d') || $end < $start || $start->year !== $end->year || ! in_array($start->year, [$today->year, $today->year + 1], true) || $start->diffInDays($end) >= 60) {
                    $this->error('starts_on', 'Pilih rentang dalam satu tahun (tahun ini/berikutnya), maksimal 60 hari. Cuti tahunan tidak boleh mundur; sakit/izin maksimal 7 hari ke belakang dan tidak sebelum bergabung.');
                }
                $holidays = DB::table('leave_holidays')->whereBetween('date', [$d['starts_on'], $d['ends_on']])->pluck('date')->all();
                $dates = [];
                for ($day = $start; $day <= $end; $day = $day->addDay()) {
                    if ($day->isWeekday() && ! in_array($day->format('Y-m-d'), $holidays, true)) {
                        $dates[] = $day->format('Y-m-d');
                    }
                }
                if (! $dates) {
                    $this->error('starts_on', 'Rentang ini tidak memiliki hari kerja.');
                }
                $held = LeaveRequest::where('employee_id', $e->id)->whereIn('status', LeaveRequest::HELD)->where('starts_on', '<=', $d['ends_on'])->where('ends_on', '>=', $d['starts_on'])->get(['work_dates']);
                if ($held->contains(fn ($l) => count(array_intersect($dates, $l->work_dates)) > 0)) {
                    $this->error('starts_on', 'Ada pengajuan aktif pada tanggal kerja yang sama.');
                }
                if ($d['type'] === 'annual' && count($dates) > $this->balance($e->id, $start->year)['available']) {
                    $this->error('balance', 'Saldo cuti belum cukup. Hubungi HR untuk memeriksa alokasi.');
                }
                if ($file) {
                    $path = $file->store('e'.$e->id, 'leave_documents');
                }
                $l = LeaveRequest::create(['employee_id' => $e->id, 'submitted_by' => $u->id, 'type' => $d['type'], 'status' => $this->manager($e) ? 'pending_supervisor' : 'pending_hr', 'starts_on' => $d['starts_on'], 'ends_on' => $d['ends_on'], 'year' => $start->year, 'days' => count($dates), 'work_dates' => $dates, 'reason' => $d['reason'], 'document_path' => $path, 'document_bytes' => $file?->getSize(), 'version' => 1]);
                $this->audit($u, 'submitted', ['status' => $l->status, 'work_dates' => $dates, 'days' => count($dates)], $l);

                return $l;
            });
        } catch (Throwable $ex) {
            if ($path) {
                Storage::disk('leave_documents')->delete($path);
            }
            throw $ex;
        }
    }

    public function actions(User $u, LeaveRequest $l): array
    {
        $own = in_array($u->role, ['employee', 'supervisor'], true) && $u->employee_id === $l->employee_id;
        $pending = in_array($l->status, ['pending_hr', 'pending_supervisor'], true);
        $future = $l->starts_on->format('Y-m-d') > now('Asia/Jakarta')->format('Y-m-d');
        if ($own) {
            return $pending || ($l->status === 'approved' && $future) ? ['cancel'] : [];
        }
        // Linked HR accounts must never decide their own historical requests.
        if ($u->employee_id === $l->employee_id) {
            return [];
        }
        $manager = $this->manager($l->employee);
        if ($l->status === 'pending_supervisor' && $manager === $u->id) {
            return ['approve', 'reject'];
        }
        if ($this->isHr($u)) {
            if ($l->status === 'pending_hr' || ($l->status === 'pending_supervisor' && $manager === null)) {
                return ['approve', 'reject'];
            }
            if ($l->status === 'cancel_pending' && $future) {
                return ['approve_cancel', 'reject_cancel'];
            }
            if ($l->status === 'cancel_pending') {
                return ['reject_cancel'];
            }
            if ($l->status === 'approved' && $future) {
                return ['cancel'];
            }
        }

        return [];
    }

    public function decide(User $user, LeaveRequest $request, array $d): void
    {
        DB::transaction(function () use ($user, $request, $d) {
            $u = $this->actor($user);
            $employee = Employee::withTrashed()->whereKey($request->employee_id)->lockForUpdate()->firstOrFail();
            $l = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $l->setRelation('employee', $employee);
            $this->visible($u, $l);
            if ($l->version !== (int) $d['version']) {
                $this->error('version', 'Pengajuan sudah berubah. Muat ulang halaman sebelum melanjutkan.');
            }
            if (! in_array($d['action'], $this->actions($u, $l), true)) {
                if (in_array($l->status, ['cancelled', 'rejected'], true)) {
                    $this->error('leave', 'Pengajuan sudah selesai dan tidak dapat diubah.');
                }
                abort(403);
            }
            $before = $l->status;
            $l->status = match ($d['action']) {
                'approve' => $before === 'pending_supervisor' && ! $this->isHr($u) ? 'pending_hr' : 'approved',
                'reject' => 'rejected',
                'reject_cancel' => 'approved',
                'approve_cancel' => 'cancelled',
                'cancel' => $before === 'approved' && ! $this->isHr($u) ? 'cancel_pending' : 'cancelled',
            };
            $l->version++;
            $l->save();
            $this->audit($u, $d['action'], ['from' => $before, 'to' => $l->status, 'reason' => $d['reason']], $l);
        });
    }
}
