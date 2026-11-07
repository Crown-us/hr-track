<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinaryCase extends Model
{
    public const KINDS = ['violation' => 'Pelanggaran', 'sp1' => 'SP 1', 'sp2' => 'SP 2', 'sp3' => 'SP 3', 'counseling' => 'Konseling'];

    public const WARNINGS = ['sp1', 'sp2', 'sp3'];

    public const WARNING_VALIDITY_MONTHS = 6;

    public const VALIDITIES = ['active' => 'Masih berlaku', 'expired' => 'Kedaluwarsa', 'unset' => 'Tanggal terbit belum diisi'];

    public const STATUSES = ['open' => 'Perlu tindak lanjut', 'resolved' => 'Selesai', 'voided' => 'Dibatalkan'];

    protected $attributes = ['status' => 'open', 'version' => 1];

    protected $fillable = ['employee_id', 'reference_number', 'kind', 'incident_on', 'subject', 'description', 'follow_up_on', 'issued_on', 'valid_until', 'created_by'];

    protected function casts(): array
    {
        return ['incident_on' => 'immutable_date', 'follow_up_on' => 'immutable_date', 'issued_on' => 'immutable_date', 'valid_until' => 'immutable_date', 'version' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public static function isWarning(?string $kind): bool
    {
        return in_array($kind, self::WARNINGS, true);
    }

    public function validity(): ?string
    {
        if (! self::isWarning($this->kind) || $this->status === 'voided') {
            return null;
        }
        if (! $this->issued_on || ! $this->valid_until) {
            return 'unset';
        }

        return $this->valid_until->format('Y-m-d') < now('Asia/Jakarta')->toDateString() ? 'expired' : 'active';
    }

    public function forDisplay(): array
    {
        $validity = $this->validity();

        return [...$this->only('id', 'employee_id', 'reference_number', 'kind', 'subject', 'description', 'status', 'version'),
            'incident_on' => $this->incident_on->format('Y-m-d'), 'follow_up_on' => $this->follow_up_on?->format('Y-m-d'),
            'issued_on' => $this->issued_on?->format('Y-m-d'), 'valid_until' => $this->valid_until?->format('Y-m-d'),
            'validity' => $validity, 'validity_label' => $validity ? self::VALIDITIES[$validity] : null,
            'kind_label' => self::KINDS[$this->kind], 'status_label' => self::STATUSES[$this->status],
            'employee_name' => $this->employee->full_name, 'employee_number' => $this->employee->employee_number, 'employee_archived' => $this->employee->trashed()];
    }
}
