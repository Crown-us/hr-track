<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\VacancyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacancy extends Model
{
    use SoftDeletes;

    protected $attributes = ['version' => 1, 'status' => 'draft'];

    protected $fillable = ['job_title', 'department_id', 'employment_type', 'location', 'description', 'requirements', 'opening_date', 'closing_date', 'status'];

    protected function casts(): array
    {
        return ['employment_type' => EmploymentType::class, 'status' => VacancyStatus::class, 'opening_date' => 'date', 'closing_date' => 'date', 'version' => 'integer'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $today = now('Asia/Jakarta')->toDateString();
        $query->where('status', 'published')->where('opening_date', '<=', $today)->where('closing_date', '>=', $today);
    }

    public function forDisplay(bool $details = false): array
    {
        $today = now('Asia/Jakarta')->toDateString();
        $state = $this->status === VacancyStatus::Published
            ? ($this->opening_date->format('Y-m-d') > $today ? 'Terjadwal' : ($this->closing_date->format('Y-m-d') < $today ? 'Berakhir' : 'Dibuka'))
            : $this->status->label();

        return [...$this->only('id', 'job_title', 'department_id', 'location', 'version'), 'department' => $this->department->name,
            'employment_type' => $this->employment_type->value, 'type_label' => $this->employment_type->label(),
            'status' => $this->status->value, 'state_label' => $state, 'opening_date' => $this->opening_date->format('Y-m-d'), 'closing_date' => $this->closing_date->format('Y-m-d'),
            ...($details ? $this->only('description', 'requirements') : [])];
    }
}
