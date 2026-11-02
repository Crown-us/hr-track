<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use SoftDeletes;

    protected $attributes = ['version' => 1, 'status' => 'screening'];

    protected $fillable = ['applicant_id', 'vacancy_id', 'application_date', 'notes'];

    protected function casts(): array
    {
        return ['status' => ApplicationStatus::class, 'application_date' => 'date', 'version' => 'integer'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class)->withTrashed();
    }

    public function forDisplay(bool $private = false): array
    {
        return [...$this->only('id', 'applicant_id', 'vacancy_id', 'version'), 'name' => $this->applicant->full_name, 'email' => $this->applicant->email,
            'job_title' => $this->vacancy->job_title, 'status' => $this->status->value, 'status_label' => $this->status->label(), 'application_date' => $this->application_date->format('Y-m-d'),
            ...($private ? ['notes' => $this->notes, 'phone' => $this->applicant->phone] : [])];
    }
}
