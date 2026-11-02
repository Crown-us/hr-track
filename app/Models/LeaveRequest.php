<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    public const TYPES = ['annual' => 'Cuti tahunan', 'sick' => 'Sakit', 'permission' => 'Izin'];

    public const STATUSES = ['pending_supervisor' => 'Menunggu atasan', 'pending_hr' => 'Menunggu HR', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancel_pending' => 'Menunggu pembatalan', 'cancelled' => 'Dibatalkan'];

    public const HELD = ['pending_supervisor', 'pending_hr', 'approved', 'cancel_pending'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'work_dates' => 'array', 'year' => 'integer', 'days' => 'integer', 'version' => 'integer'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function forDisplay(): array
    {
        return [...$this->only('id', 'type', 'status', 'days', 'year', 'version'),
            'employee' => $this->employee->only('id', 'full_name', 'employee_number'),
            'type_label' => self::TYPES[$this->type], 'status_label' => self::STATUSES[$this->status],
            'starts_on' => $this->starts_on->format('Y-m-d'), 'ends_on' => $this->ends_on->format('Y-m-d'),
            'created_at' => $this->created_at->toIso8601String()];
    }
}
