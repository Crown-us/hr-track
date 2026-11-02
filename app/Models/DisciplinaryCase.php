<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryCase extends Model
{
    public const KINDS = ['violation' => 'Pelanggaran', 'sp1' => 'SP 1', 'sp2' => 'SP 2', 'sp3' => 'SP 3', 'counseling' => 'Konseling'];

    public const STATUSES = ['open' => 'Perlu tindak lanjut', 'resolved' => 'Selesai', 'voided' => 'Dibatalkan'];

    protected $attributes = ['status' => 'open', 'version' => 1];

    protected $fillable = ['employee_id', 'reference_number', 'kind', 'incident_on', 'subject', 'description', 'follow_up_on', 'created_by'];

    protected function casts(): array
    {
        return ['incident_on' => 'immutable_date', 'follow_up_on' => 'immutable_date', 'version' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function forDisplay(): array
    {
        return [...$this->only('id', 'employee_id', 'reference_number', 'kind', 'subject', 'description', 'status', 'version'),
            'incident_on' => $this->incident_on->format('Y-m-d'), 'follow_up_on' => $this->follow_up_on?->format('Y-m-d'),
            'kind_label' => self::KINDS[$this->kind], 'status_label' => self::STATUSES[$this->status],
            'employee_name' => $this->employee->full_name, 'employee_number' => $this->employee->employee_number, 'employee_archived' => $this->employee->trashed()];
    }
}
