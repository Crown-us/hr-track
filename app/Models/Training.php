<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'scheduled', 'version' => 1];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'version' => 'integer'];
    }

    public function participants()
    {
        return $this->hasMany(TrainingParticipant::class);
    }

    public function forDisplay(bool $private = false): array
    {
        return [...$this->only('id', 'title', 'trainer', 'location', 'status', 'version'),
            'status_label' => ['scheduled' => 'Terjadwal', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$this->status],
            'starts_at' => $this->starts_at->toIso8601String(), 'ends_at' => $this->ends_at->toIso8601String(),
            'participant_count' => $this->participants_count ?? 0,
            ...($private ? ['notes' => $this->notes] : [])];
    }
}
