<?php

namespace Database\Seeders;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $admin = User::where('role', 'admin')->where('is_active', true)->orderBy('id')->first()
            ?? User::where('role', 'hr_staff')->where('is_active', true)->orderBy('id')->first();

        if ($employees->isEmpty() || ! $admin) {
            $this->command?->warn('Data cuti/kehadiran dilewati: butuh pegawai aktif dan akun Admin/Staf HR.');

            return;
        }

        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $year = (int) $today->year;
        $holidays = $this->holidays($year);

        $this->balances($employees, $year);
        $this->requests($today, $holidays, $admin);
        $this->attendance($employees, $today, $holidays, $admin);

        $this->command?->info('Saldo cuti, pengajuan contoh, dan catatan kehadiran sudah tersedia.');
    }

    /**
     * @return array<int, string>
     */
    private function holidays(int $year): array
    {
        $rows = [
            [$year.'-01-01', 'Tahun Baru'],
            [$year.'-08-17', 'Hari Kemerdekaan'],
            [$year.'-12-25', 'Hari Natal'],
        ];

        foreach ($rows as [$date, $name]) {
            DB::table('leave_holidays')->insertOrIgnore(['date' => $date, 'name' => $name, 'created_at' => now()]);
        }

        return DB::table('leave_holidays')->whereYear('date', $year)->orderBy('date')->pluck('date')->all();
    }

    private function balances($employees, int $year): void
    {
        foreach ($employees as $employee) {
            DB::table('leave_balances')->insertOrIgnore([
                'employee_id' => $employee->id,
                'year' => $year,
                'days' => 12,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<int, LeaveRequest>
     */
    private function requests(CarbonImmutable $today, array $holidays, User $admin): array
    {
        [$supervisorEmail, $memberEmail] = $this->accountEmails();
        $byEmail = User::whereIn('email', [$supervisorEmail, $memberEmail])
            ->whereNotNull('employee_id')
            ->get()
            ->keyBy('email');

        $supervisor = $byEmail->get($supervisorEmail);
        $member = $byEmail->get($memberEmail);

        if (! $supervisor?->employee || ! $member?->employee) {
            return [];
        }

        $manager = $supervisor->employee;
        $staff = $member->employee;
        $teammate = Employee::where('manager_employee_id', $manager->id)
            ->whereNotIn('id', [$manager->id, $staff->id])
            ->where('employment_status', '!=', 'inactive')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();

        $plans = [
            [
                'employee' => $staff,
                'submitter' => $member,
                'type' => 'annual',
                'status' => 'approved',
                'start' => $today->subDays(35)->startOfWeek(),
                'days' => 2,
                'reason' => 'Mengurus dokumen keluarga di luar kota.',
            ],
            [
                'employee' => $manager,
                'submitter' => $supervisor,
                'type' => 'annual',
                'status' => 'approved',
                'start' => $today->subDays(21)->startOfWeek(),
                'days' => 2,
                'reason' => 'Jeda tahunan bersama keluarga.',
            ],
            [
                'employee' => $staff,
                'submitter' => $member,
                'type' => 'annual',
                'status' => 'pending_supervisor',
                'start' => $today->startOfWeek()->addWeek(),
                'days' => 1,
                'reason' => 'Keperluan pribadi pada awal pekan.',
            ],
        ];

        if ($teammate) {
            $plans[] = [
                'employee' => $teammate,
                'submitter' => $supervisor,
                'type' => 'annual',
                'status' => 'pending_hr',
                'start' => $today->startOfWeek()->addWeek()->addDay(),
                'days' => 2,
                'reason' => 'Menghadiri acara keluarga di luar kota.',
            ];
            $plans[] = [
                'employee' => $teammate,
                'submitter' => $supervisor,
                'type' => 'sick',
                'status' => 'approved',
                'start' => $today->subWeek(),
                'days' => 1,
                'reason' => 'Istirahat atas anjuran dokter.',
            ];
        }

        $created = [];

        foreach ($plans as $plan) {
            $end = $plan['start']->addDays($plan['days'] - 1);
            $dates = $this->workDates($plan['start'], $end, $holidays);

            if (! $dates || $dates[0] < $plan['employee']->join_date->format('Y-m-d')) {
                continue;
            }

            $exists = LeaveRequest::where('employee_id', $plan['employee']->id)
                ->where('type', $plan['type'])
                ->where('starts_on', $dates[0])
                ->exists();

            if ($exists) {
                continue;
            }

            $createdAt = CarbonImmutable::parse($dates[0], 'Asia/Jakarta')->subDays(9)->setTime(9, 15);

            $leave = LeaveRequest::create([
                'employee_id' => $plan['employee']->id,
                'submitted_by' => $plan['submitter']->id,
                'type' => $plan['type'],
                'status' => $plan['status'],
                'starts_on' => $dates[0],
                'ends_on' => end($dates),
                'year' => (int) substr($dates[0], 0, 4),
                'days' => count($dates),
                'work_dates' => $dates,
                'reason' => $plan['reason'],
                'version' => 1,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $this->events($leave, $plan, $manager, $supervisor, $admin);

            $created[] = $leave;
        }

        return $created;
    }

    private function events(LeaveRequest $leave, array $plan, Employee $manager, User $supervisor, User $admin): void
    {
        $at = $leave->created_at;

        DB::table('leave_events')->insert([
            'leave_request_id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'actor_id' => $plan['submitter']->id,
            'action' => 'submitted',
            'changes' => json_encode(['status' => $plan['status'], 'work_dates' => $leave->work_dates, 'days' => $leave->days], JSON_THROW_ON_ERROR),
            'created_at' => $at,
        ]);

        if (! in_array($leave->status, ['approved', 'pending_hr', 'rejected'], true)) {
            return;
        }

        $hasManager = $plan['employee']->manager_employee_id === $manager->id;
        $actor = $hasManager ? $supervisor : $admin;

        if ($leave->status === 'pending_hr' && $hasManager) {
            DB::table('leave_events')->insert([
                'leave_request_id' => $leave->id,
                'employee_id' => $leave->employee_id,
                'actor_id' => $actor->id,
                'action' => 'approve',
                'changes' => json_encode(['from' => 'pending_supervisor', 'to' => 'pending_hr', 'reason' => 'Tanggal kerja tidak bentrok dengan agenda tim.'], JSON_THROW_ON_ERROR),
                'created_at' => $at->addDay(),
            ]);

            return;
        }

        if ($leave->status === 'rejected') {
            DB::table('leave_events')->insert([
                'leave_request_id' => $leave->id,
                'employee_id' => $leave->employee_id,
                'actor_id' => $actor->id,
                'action' => 'reject',
                'changes' => json_encode(['from' => 'pending_supervisor', 'to' => 'rejected', 'reason' => 'Beban kerja tim pada tanggal tersebut belum memungkinkan.'], JSON_THROW_ON_ERROR),
                'created_at' => $at->addDay(),
            ]);

            return;
        }

        if ($hasManager) {
            DB::table('leave_events')->insert([
                'leave_request_id' => $leave->id,
                'employee_id' => $leave->employee_id,
                'actor_id' => $supervisor->id,
                'action' => 'approve',
                'changes' => json_encode(['from' => 'pending_supervisor', 'to' => 'pending_hr'], JSON_THROW_ON_ERROR),
                'created_at' => $at->addDay(),
            ]);
        }

        DB::table('leave_events')->insert([
            'leave_request_id' => $leave->id,
            'employee_id' => $leave->employee_id,
            'actor_id' => $admin->id,
            'action' => 'approve',
            'changes' => json_encode(['from' => 'pending_hr', 'to' => 'approved'], JSON_THROW_ON_ERROR),
            'created_at' => $at->addDays(2),
        ]);
    }

    /** @return array{string, string} */
    private function accountEmails(): array
    {
        return app()->environment('staging')
            ? ['supervisor@hris.example.test', 'employee@hris.example.test']
            : ['atasan@example.test', 'pegawai@example.test'];
    }

    private function attendance($employees, CarbonImmutable $today, array $holidays, User $admin): void
    {
        [$supervisorEmail, $memberEmail] = $this->accountEmails();
        $managerId = User::where('email', $supervisorEmail)->value('employee_id');
        $staffId = User::where('email', $memberEmail)->value('employee_id');

        $ids = collect([$managerId, $staffId])
            ->merge(Employee::where('manager_employee_id', $managerId)->pluck('id'))
            ->filter()
            ->unique()
            ->all();

        $people = $employees->whereIn('id', $ids);
        $leave = LeaveRequest::whereIn('employee_id', $ids)
            ->where('year', $today->year)
            ->whereIn('status', ['approved', 'cancel_pending'])
            ->get()
            ->groupBy('employee_id');

        foreach ($people as $employee) {
            $start = CarbonImmutable::parse($employee->join_date->format('Y-m-d'), 'Asia/Jakarta');
            if ($start < $today->subDays(50)) {
                $start = $today->subDays(50);
            }

            $off = collect($leave->get($employee->id, []))->flatMap(fn ($l) => $l->work_dates)->unique()->all();

            for ($date = $start; $date < $today; $date = $date->addDay()) {
                if (! $date->isWeekday() || in_array($date->format('Y-m-d'), $holidays, true) || in_array($date->format('Y-m-d'), $off, true)) {
                    continue;
                }

                $seed = crc32($employee->id.'|'.$date->format('Y-m-d'));
                $absent = $seed % 41 === 0;
                $missingOut = ! $absent && $seed % 23 === 0;
                $lateMinutes = $seed % 47;
                $clockIn = $absent ? null : ($lateMinutes < 12 ? 8 * 60 + 45 + $seed % 15 : 9 * 60 + $seed % 40);
                $clockOut = $absent || $missingOut ? null : 17 * 60 + $seed % 25;

                $day = AttendanceDay::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $date->format('Y-m-d')],
                    [
                        'scheduled_start' => $this->wib($date, 9 * 60),
                        'scheduled_end' => $this->wib($date, 17 * 60),
                        'clock_in' => $clockIn === null ? null : $this->wib($date, $clockIn),
                        'clock_out' => $clockOut === null ? null : $this->wib($date, $clockOut),
                        'tolerance_minutes' => 15,
                        'version' => 1,
                    ]
                );

                if (! $day->wasRecentlyCreated) {
                    continue;
                }

                DB::table('attendance_events')->insert([
                    'attendance_day_id' => $day->id,
                    'actor_id' => $admin->id,
                    'action' => 'recorded',
                    'changes' => json_encode(['before' => null, 'after' => $day->forDisplay(), 'reason' => 'Pencatatan awal dari rekap kehadiran tim.'], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function wib(CarbonImmutable $date, int $minutes): CarbonImmutable
    {
        return $date->setTime(0, 0)->addMinutes($minutes)->utc();
    }

    /**
     * @return array<int, string>
     */
    private function workDates(CarbonImmutable $start, CarbonImmutable $end, array $holidays): array
    {
        $dates = [];

        for ($date = $start; $date <= $end; $date = $date->addDay()) {
            if ($date->isWeekday() && ! in_array($date->format('Y-m-d'), $holidays, true)) {
                $dates[] = $date->format('Y-m-d');
            }
        }

        return $dates;
    }
}
