<?php

namespace App\Services;

use App\Models\AttendanceDay;
use Carbon\CarbonImmutable;

class AttendanceStatus
{
    public function classify(string $date, string $joined, ?AttendanceDay $raw, ?array $leave, bool $off, CarbonImmutable $now): array
    {
        $record = $raw && ! $raw->voided_at ? $raw : null;
        $late = $record?->clock_in ? max(0, (int) ceil($record->scheduled_start->diffInSeconds($record->clock_in, false) / 60)) : 0;
        $status = match (true) {
            $date < $joined => 'not_employed',
            $date > $now->setTimezone('Asia/Jakarta')->toDateString() => 'future',
            $leave !== null && ($record?->clock_in || $record?->clock_out) => 'review',
            $leave !== null => $leave['type'] === 'sick' ? 'sick' : 'leave',
            $record?->clock_in !== null && $record?->clock_out !== null => $late > $record->tolerance_minutes ? 'late' : 'present',
            $record !== null && $now->utc() >= $record->scheduled_end => $record->clock_in ? 'review' : 'absent',
            $record?->clock_in !== null => 'working',
            $record !== null => $now->utc() < $record->scheduled_start ? 'scheduled' : 'waiting',
            $off => 'off',
            default => 'unknown',
        };

        return ['status' => $status, 'late_minutes' => $late];
    }
}
