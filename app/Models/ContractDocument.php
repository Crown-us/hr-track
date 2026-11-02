<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContractDocument extends Model
{
    public $timestamps = false;

    protected $fillable = ['employee_contract_id', 'uploaded_by', 'version', 'path', 'size_bytes', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'version' => 'integer'];
    }
}
