<?php

namespace App\Http\Requests;

use App\Services\AttendanceRecords;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AttendanceRecords::class)->isHr($this->user());
    }

    protected function getRedirectUrl(): string
    {
        return route('attendance.schedules');
    }

    public static function inputRules(): array
    {
        return ['employee_ids' => ['required', 'array', 'min:1', 'max:50'], 'employee_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:'.now('Asia/Jakarta')->addDays(90)->toDateString()],
            'weekdays' => ['required', 'array', 'min:1', 'max:7'], 'weekdays.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'starts_at' => ['required', 'date_format:H:i'], 'ends_at' => ['required', 'date_format:H:i'], 'ends_next_day' => ['required', 'boolean'], 'skip_holidays' => ['required', 'boolean'],
            'tolerance_minutes' => ['required', 'integer', 'between:0,120'], 'reason' => ['required', 'string', 'min:10', 'max:2000']];
    }

    public function rules(): array
    {
        return $this->routeIs('attendance.schedules.store') ? ['token' => ['required', 'string', 'max:30000'], 'confirm' => ['accepted']] : self::inputRules();
    }

    public function attributes(): array
    {
        return ['employee_ids' => 'Pilihan pegawai', 'weekdays' => 'Hari kerja', 'starts_on' => 'Tanggal mulai', 'ends_on' => 'Tanggal akhir', 'starts_at' => 'Jam mulai', 'ends_at' => 'Jam akhir', 'reason' => 'Alasan penjadwalan', 'tolerance_minutes' => 'Toleransi', 'token' => 'Pratinjau', 'confirm' => 'Konfirmasi penjadwalan'];
    }
}
