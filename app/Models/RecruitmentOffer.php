<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentOffer extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['evidence_path'];

    protected function casts(): array
    {
        return ['join_date' => 'date', 'response_deadline' => 'date', 'response_date' => 'date', 'evidence_size' => 'integer'];
    }

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
