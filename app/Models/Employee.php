<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
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

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmployeeEvent::class);
    }

    public function forDisplay(): array
    {
        return [...$this->only('id', 'employee_number', 'full_name', 'work_email', 'phone', 'department_id', 'position_id', 'version'),
            'employment_status' => $this->employment_status->value, 'status_label' => $this->employment_status->label(),
            'join_date' => $this->join_date->format('Y-m-d'), 'department' => $this->department->name,
            'position' => $this->position->name, 'archived_at' => $this->deleted_at?->toIso8601String()];
    }
}
