<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeContract extends Model
{
    protected $attributes = ['version' => 1];

    protected $fillable = ['employee_id', 'contract_number', 'type', 'starts_on', 'ends_on', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'voided_at' => 'immutable_datetime', 'version' => 'integer'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function scopeAttention(Builder $q, string $kind): void
    {
        $today = now('Asia/Jakarta')->toDateString();
        $q->whereNull('voided_at')->where('type', 'pkwt')->where('starts_on', '<=', $today)
            ->whereHas('employee', fn ($e) => $e->whereNull('employees.deleted_at')->where('employment_status', '!=', 'inactive'))
            ->whereNotExists(fn ($next) => $next->selectRaw('1')->from('employee_contracts as next_contract')
                ->whereColumn('next_contract.employee_id', 'employee_contracts.employee_id')->whereNull('next_contract.voided_at')
                ->whereColumn('next_contract.starts_on', '>', 'employee_contracts.ends_on')
                ->whereRaw('next_contract.starts_on <= DATE_ADD(employee_contracts.ends_on, INTERVAL 1 DAY)'));
        if ($kind === 'due') {
            $q->whereBetween('ends_on', [$today, now('Asia/Jakarta')->addDays(30)->toDateString()]);
        } else {
            $q->where('ends_on', '<', $today);
        }
    }

    public function forDisplay(bool $private = false): array
    {
        $today = now('Asia/Jakarta')->toDateString();
        $start = $this->starts_on->format('Y-m-d');
        $end = $this->ends_on?->format('Y-m-d');
        $status = $this->voided_at ? 'voided' : ($start > $today ? 'scheduled' : ($end && $end < $today ? 'expired' : 'active'));

        return [...$this->only('id', 'employee_id', 'contract_number', 'type', 'version'), 'starts_on' => $start, 'ends_on' => $end,
            'employee_name' => $this->employee->full_name, 'employee_number' => $this->employee->employee_number,
            'employee_archived' => $this->employee->trashed(), 'status' => $status,
            'status_label' => ['voided' => 'Dibatalkan', 'scheduled' => 'Dijadwalkan', 'expired' => 'Berakhir', 'active' => 'Berjalan'][$status],
            ...($private ? ['notes' => $this->notes] : [])];
    }
}
