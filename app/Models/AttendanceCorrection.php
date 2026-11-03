<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    public const STATUSES = ['pending_supervisor' => 'Menunggu atasan', 'pending_hr' => 'Menunggu HR', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'withdrawn' => 'Ditarik'];

    public const PENDING = ['pending_supervisor', 'pending_hr'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['original' => 'array', 'clock_in' => 'immutable_datetime', 'clock_out' => 'immutable_datetime', 'version' => 'integer', 'day_version' => 'integer'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function day()
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }

    public function forDisplay(): array
    {
        return [...$this->only('id', 'status', 'version', 'reason', 'original', 'day_version'), 'status_label' => self::STATUSES[$this->status], 'employee' => $this->employee->only('id', 'full_name', 'employee_number'), 'date' => $this->day->date->format('Y-m-d'), 'clock_in' => $this->clock_in->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'clock_out' => $this->clock_out->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'created_at' => $this->created_at->toIso8601String()];
    }
}
