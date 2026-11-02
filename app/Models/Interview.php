<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['version' => 1];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime', 'version' => 'integer'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function forDisplay(bool $private): array
    {
        return [...$this->only('id', 'application_id', 'version'), 'name' => $this->application->applicant->full_name, 'job_title' => $this->application->vacancy->job_title, 'interviewer' => $this->interviewer->name, 'starts_at' => $this->starts_at->toIso8601String(), 'ends_at' => $this->ends_at->toIso8601String(), 'cancelled_at' => $this->cancelled_at?->toIso8601String(), 'can_cancel' => ! $this->cancelled_at && $this->ends_at->isFuture(), 'can_download' => ! $this->cancelled_at && $this->application->status === ApplicationStatus::Interview, ...($private ? $this->only('location', 'meeting_url', 'notes', 'cancel_reason') : [])];
    }
}
