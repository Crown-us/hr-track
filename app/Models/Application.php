<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use SoftDeletes;

    protected $attributes = ['version' => 1, 'status' => 'screening', 'source' => 'internal'];

    protected $fillable = ['applicant_id', 'vacancy_id', 'application_date', 'notes'];

    protected function casts(): array
    {
        return ['status' => ApplicationStatus::class, 'application_date' => 'date', 'version' => 'integer', 'email_verified_at' => 'datetime', 'verification_sent_at' => 'datetime'];
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
            ...($private ? ['notes' => $this->notes, 'phone' => $this->applicant->phone, 'source' => $this->source, 'submitted_name' => $this->submitted_name, 'submitted_phone' => $this->submitted_phone, 'email_verified_at' => $this->email_verified_at?->toIso8601String()] : [])];
    }
}
