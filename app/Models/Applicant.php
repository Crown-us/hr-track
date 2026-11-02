<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Applicant extends Model
{
    use SoftDeletes;

    protected $fillable = ['full_name', 'email', 'phone'];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class)->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class)->latest('id');
    }

    public function forDisplay(bool $private = false): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            ...($private ? ['phone' => $this->phone] : []),
            'application_count' => $this->applications_count ?? ($this->relationLoaded('applications') ? $this->applications->count() : 0),
            'archived_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
