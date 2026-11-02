<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingMaterial extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'version' => 'integer', 'size_bytes' => 'integer'];
    }
}
