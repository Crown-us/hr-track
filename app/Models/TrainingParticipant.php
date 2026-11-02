<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainingParticipant extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['attendance' => 'registered', 'version' => 1];

    protected function casts(): array
    {
        return ['pre_test' => 'float', 'post_test' => 'float', 'version' => 'integer'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function forDisplay(): array
    {
        return [...$this->only('id', 'employee_id', 'attendance', 'pre_test', 'post_test', 'notes', 'version'),
            'name' => $this->employee->full_name, 'employee_number' => $this->employee->employee_number,
            'archived' => $this->employee->trashed(),
            'gain' => $this->pre_test !== null && $this->post_test !== null ? round($this->post_test - $this->pre_test, 2) : null];
    }
}
