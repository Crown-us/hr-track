<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceCalendar
{
    public const LABELS = ['present' => 'Hadir tepat waktu', 'late' => 'Hadir terlambat', 'leave' => 'Cuti / izin disetujui', 'sick' => 'Sakit disetujui', 'absent' => 'Tanpa keterangan', 'off' => 'Libur / tidak dijadwalkan', 'future' => 'Tanggal mendatang', 'review' => 'Perlu diperiksa', 'unknown' => 'Belum ada data', 'scheduled' => 'Jadwal belum mulai', 'waiting' => 'Jadwal sedang berjalan', 'working' => 'Sudah masuk, belum pulang', 'not_employed' => 'Belum bergabung'];

    public function build(Employee $e, int $year, string $selected, bool $history): array
    {
        $first = CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta');
        $last = $first->endOfYear()->startOfDay();
        $now = CarbonImmutable::now('Asia/Jakarta');
        $today = $now->toDateString();
        $records = AttendanceDay::where('employee_id', $e->id)->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()->keyBy(fn ($d) => $d->date->format('Y-m-d'));
        $leave = [];
        foreach (LeaveRequest::where('employee_id', $e->id)->where('year', $year)->whereIn('status', ['approved', 'cancel_pending'])->get(['id', 'type', 'work_dates']) as $l) {
            foreach ($l->work_dates as $date) {
                $leave[$date] = ['type' => $l->type, 'label' => LeaveRequest::TYPES[$l->type]];
            }
        }
        $holidays = DB::table('leave_holidays')->whereBetween('date', [$first->toDateString(), $last->toDateString()])->pluck('name', 'date');
        $days = [];
        $summary = ['present' => 0, 'on_time' => 0, 'late' => 0, 'review' => 0, 'absent' => 0];
        $offset = $first->dayOfWeekIso - 1;
        for ($date = $first, $i = 0; $date <= $last; $date = $date->addDay(), $i++) {
            $key = $date->toDateString();
            $raw = $records[$key] ?? null;
            $record = $raw && ! $raw->voided_at ? $raw : null;
            $l = $leave[$key] ?? null;
            $late = $record?->clock_in ? max(0, (int) ceil($record->scheduled_start->diffInSeconds($record->clock_in, false) / 60)) : 0;
            $status = match (true) {
                $key < $e->join_date->format('Y-m-d') => 'not_employed',
                $key > $today => 'future',
                $l !== null && ($record?->clock_in || $record?->clock_out) => 'review',
                $l !== null => $l['type'] === 'sick' ? 'sick' : 'leave',
                $record?->clock_in !== null && $record?->clock_out !== null => $late > $record->tolerance_minutes ? 'late' : 'present',
                $record !== null && $now->utc() >= $record->scheduled_end => $record->clock_in ? 'review' : 'absent',
                $record?->clock_in !== null => 'working',
                $record !== null => $now->utc() < $record->scheduled_start ? 'scheduled' : 'waiting',
                isset($holidays[$key]) || $date->isWeekend() => 'off',
                default => 'unknown',
            };
            if ($status === 'present' || $status === 'late') {
                $summary['present']++;
                $summary[$status === 'present' ? 'on_time' : 'late']++;
            } elseif (in_array($status, ['review', 'absent'], true)) {
                $summary[$status]++;
            }
            $days[] = ['date' => $key, 'status' => $status, 'label' => self::LABELS[$status], 'week' => intdiv($offset + $i, 7), 'weekday' => $date->dayOfWeekIso - 1, 'month_start' => $date->day === 1];
            if ($key === $selected) {
                $detail = [...end($days), 'record' => $raw?->forDisplay(), 'late_minutes' => $late, 'leave' => $l, 'holiday' => $holidays[$key] ?? null];
            }
        }
        $events = [];
        if ($history && ($raw = $records[$selected] ?? null)) {
            $events = DB::table('attendance_events')->join('users', 'users.id', '=', 'attendance_events.actor_id')->where('attendance_day_id', $raw->id)->orderByDesc('attendance_events.id')->limit(30)->get(['attendance_events.id', 'action', 'changes', 'users.name as actor', 'attendance_events.created_at'])->map(fn ($v) => ['id' => $v->id, 'action' => $v->action, 'actor' => $v->actor, 'reason' => json_decode($v->changes, true)['reason'], 'created_at' => CarbonImmutable::parse($v->created_at, 'UTC')->toIso8601String()])->all();
        }

        return ['year' => $year, 'today' => $today, 'days' => $days, 'weeks' => intdiv($offset + count($days) - 1, 7) + 1, 'summary' => $summary, 'selected' => $detail, 'events' => $events, 'labels' => self::LABELS];
    }
}
