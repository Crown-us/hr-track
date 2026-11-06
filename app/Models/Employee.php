<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $attributes = ['version' => 1];

    protected $fillable = ['employee_number', 'full_name', 'work_email', 'phone', 'department_id', 'position_id', 'employment_status', 'join_date'];

    protected function casts(): array
    {
        return ['employment_status' => EmploymentStatus::class, 'join_date' => 'date', 'version' => 'integer'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_employee_id')->withTrashed();
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmployeeEvent::class);
    }

    public function scopeWithoutCurrentContract(Builder $query): void
    {
        $today = now('Asia/Jakarta')->toDateString();
        $query->where('employment_status', '!=', 'inactive')->where('join_date', '<=', $today)
            ->whereNotExists(fn ($contract) => $contract->selectRaw('1')->from('employee_contracts')
                ->whereColumn('employee_contracts.employee_id', 'employees.id')->whereNull('employee_contracts.voided_at')
                ->where('employee_contracts.starts_on', '<=', $today)
                ->where(fn ($end) => $end->whereNull('employee_contracts.ends_on')->orWhere('employee_contracts.ends_on', '>=', $today)));
    }

    public function forDisplay(bool $private = false): array
    {
        return [...$this->only('id', 'employee_number', 'full_name', 'department_id', 'position_id', 'version'),
            ...($private ? $this->only('work_email', 'phone') : []),
            'employment_status' => $this->employment_status->value, 'status_label' => $this->employment_status->label(),
            'join_date' => $this->join_date->format('Y-m-d'), 'department' => $this->department->name,
            'position' => $this->position->name, 'archived_at' => $this->deleted_at?->toIso8601String()];
    }
}
