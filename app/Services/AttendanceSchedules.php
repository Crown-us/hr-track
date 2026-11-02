<?php

namespace App\Services;

use App\Http\Requests\AttendanceScheduleRequest;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceSchedules
{
    public const REASONS = ['create' => 'Jadwal baru', 'existing' => 'Sudah ada catatan', 'holiday' => 'Libur kantor', 'leave' => 'Cuti / izin disetujui', 'before_join' => 'Belum bergabung', 'started' => 'Jam mulai sudah lewat', 'overlap' => 'Bertumpuk dengan jadwal lain'];

    private function error(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    private function actor(User $user): User
    {
        DB::table('account_administration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
        $u = User::findOrFail($user->id);
        abort_unless($u->is_active && $u->auth_version === $user->auth_version && in_array($u->role, ['admin', 'hr_staff'], true), 403);

        return $u;
    }

    private function input(array $data): array
    {
        $v = Validator::make($data, AttendanceScheduleRequest::inputRules())->validate();
        $v['employee_ids'] = array_map('intval', $v['employee_ids']);
        sort($v['employee_ids']);
        $v['weekdays'] = array_map('intval', $v['weekdays']);
        sort($v['weekdays']);
        $v['ends_next_day'] = (bool) $v['ends_next_day'];
        $v['skip_holidays'] = (bool) $v['skip_holidays'];
        $v['tolerance_minutes'] = (int) $v['tolerance_minutes'];

        return $v;
    }

    private function plan(array $d): array
    {
        $start = CarbonImmutable::parse($d['starts_on'], 'Asia/Jakarta')->startOfDay();
        $end = CarbonImmutable::parse($d['ends_on'], 'Asia/Jakarta')->startOfDay();
        if ($start->diffInDays($end) > 30) {
            $this->error('ends_on', 'Satu proses maksimal 31 hari kalender.');
        }
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $duration = $minutes($d['ends_at']) + ($d['ends_next_day'] ? 1440 : 0) - $minutes($d['starts_at']);
        if ($duration <= 0 || $duration > 1440) {
            $this->error('ends_at', 'Durasi jadwal harus lebih dari 0 sampai 24 jam. Periksa opsi hari berikutnya.');
        }
        $dates = [];
        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            if (in_array($day->dayOfWeekIso, $d['weekdays'], true)) {
                $dates[] = $day;
            }
        }
        if (! $dates) {
            $this->error('weekdays', 'Tidak ada hari terpilih pada rentang tanggal ini.');
        }
        if (count($dates) * count($d['employee_ids']) > 1000) {
            $this->error('employee_ids', 'Maksimal 1.000 pasangan pegawai/tanggal. Kurangi pegawai atau rentang tanggal.');
        }
        $employees = Employee::withTrashed()->whereIn('id', $d['employee_ids'])->orderBy('id')->lockForUpdate()->get();
        if ($employees->count() !== count($d['employee_ids']) || $employees->contains(fn ($e) => $e->trashed() || $e->employment_status->value === 'inactive')) {
            $this->error('employee_ids', 'Semua pilihan harus pegawai aktif yang belum diarsipkan. Periksa pilihan Anda.');
        }
        // Current reads protect versions and adjacent-day overlap even after waiting for employee locks.
        $records = AttendanceDay::whereIn('employee_id', $d['employee_ids'])->whereBetween('date', [$start->subDay()->toDateString(), $end->addDay()->toDateString()])->orderBy('employee_id')->orderBy('date')->lockForUpdate()->get();
        $byEmployee = $records->groupBy('employee_id');
        $holidays = DB::table('leave_holidays')->whereBetween('date', [$d['starts_on'], $d['ends_on']])->orderBy('date')->get(['id', 'date', 'name']);
        $holidayDates = $holidays->pluck('date')->all();
        $leaves = LeaveRequest::whereIn('employee_id', $d['employee_ids'])->whereIn('status', ['approved', 'cancel_pending'])->where('starts_on', '<=', $d['ends_on'])->where('ends_on', '>=', $d['starts_on'])->orderBy('id')->get(['id', 'employee_id', 'status', 'version', 'work_dates']);
        $leaveDates = [];
        foreach ($leaves as $l) {
            foreach ($l->work_dates as $date) {
                $leaveDates[$l->employee_id][$date] = true;
            }
        }
        $rows = [];
        $now = CarbonImmutable::now('UTC');
        foreach ($employees as $employee) {
            $existing = $byEmployee->get($employee->id, collect());
            foreach ($dates as $date) {
                $key = $date->toDateString();
                $begins = CarbonImmutable::parse($key.' '.$d['starts_at'], 'Asia/Jakarta')->utc();
                $finishes = $begins->addMinutes($duration);
                $code = match (true) {
                    $existing->contains(fn ($r) => $r->date->format('Y-m-d') === $key) => 'existing',
                    $key < $employee->join_date->format('Y-m-d') => 'before_join',
                    $begins <= $now => 'started',
                    isset($leaveDates[$employee->id][$key]) => 'leave',
                    $d['skip_holidays'] && in_array($key, $holidayDates, true) => 'holiday',
                    $existing->contains(fn ($r) => ! $r->voided_at && $r->scheduled_start < $finishes && $r->scheduled_end > $begins) => 'overlap',
                    default => 'create',
                };
                $rows[] = ['employee_id' => $employee->id, 'employee_name' => $employee->full_name, 'employee_number' => $employee->employee_number, 'date' => $key, 'starts_at' => $begins->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'ends_at' => $finishes->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'code' => $code, 'label' => self::REASONS[$code]];
            }
        }
        $count = count(array_filter($rows, fn ($r) => $r['code'] === 'create'));
        $state = ['input' => $d, 'rows' => $rows,
            'employees' => $employees->map(fn ($e) => [...$e->only('id', 'version', 'full_name', 'employee_number'), 'join_date' => $e->join_date->format('Y-m-d')])->all(),
            'records' => $records->map(fn ($r) => [...$r->only('employee_id'), 'date' => $r->date->format('Y-m-d'), ...$r->forDisplay()])->all(),
            'holidays' => $holidays->all(), 'leaves' => $leaves->toArray()];

        return ['rows' => $rows, 'summary' => ['total' => count($rows), 'create' => $count, 'skip' => count($rows) - $count], 'fingerprint' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR)), 'employees' => $employees->map(fn ($e) => $e->only('id', 'full_name', 'employee_number'))->all()];
    }

    public function preview(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $u = $this->actor($user);
            $input = $this->input($data);
            $plan = $this->plan($input);
            $expires = now()->addMinutes(15);
            $token = Crypt::encryptString(json_encode(['user_id' => $u->id, 'auth_version' => $u->auth_version, 'input' => $input, 'fingerprint' => $plan['fingerprint'], 'expires_at' => $expires->getTimestamp()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

            return [...array_diff_key($plan, ['fingerprint' => true]), 'input' => $input, 'token' => $token, 'expires_at' => $expires->toIso8601String()];
        });
    }

    public function store(User $user, string $token): array
    {
        try {
            $snapshot = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $e) {
            $this->error('preview', 'Pratinjau tidak valid. Buat pratinjau baru.');
        }

        return DB::transaction(function () use ($user, $snapshot) {
            $u = $this->actor($user);
            if (($snapshot['user_id'] ?? null) !== $u->id || ($snapshot['auth_version'] ?? null) !== $u->auth_version || ($snapshot['expires_at'] ?? 0) <= now()->getTimestamp()) {
                $this->error('preview', 'Pratinjau kedaluwarsa atau bukan milik akun ini. Buat pratinjau baru.');
            }
            $input = $this->input($snapshot['input']);
            $plan = $this->plan($input);
            if (! hash_equals($snapshot['fingerprint'], $plan['fingerprint'])) {
                $this->error('preview', 'Data berubah sejak pratinjau. Tidak ada jadwal disimpan; buat pratinjau baru.');
            }
            if ($plan['summary']['create'] === 0) {
                $this->error('preview', 'Tidak ada jadwal baru yang dapat disimpan.');
            }
            $batch = (string) Str::uuid();
            foreach ($plan['rows'] as $row) {
                if ($row['code'] !== 'create') {
                    continue;
                }
                $day = AttendanceDay::create(['employee_id' => $row['employee_id'], 'date' => $row['date'], 'scheduled_start' => CarbonImmutable::parse($row['starts_at'], 'Asia/Jakarta')->utc(), 'scheduled_end' => CarbonImmutable::parse($row['ends_at'], 'Asia/Jakarta')->utc(), 'clock_in' => null, 'clock_out' => null, 'tolerance_minutes' => $input['tolerance_minutes'], 'version' => 1]);
                DB::table('attendance_events')->insert(['attendance_day_id' => $day->id, 'actor_id' => $u->id, 'action' => 'recorded', 'changes' => json_encode(['before' => null, 'after' => $day->forDisplay(), 'reason' => $input['reason'], 'batch_id' => $batch], JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }

            return $plan['summary'];
        });
    }
}
