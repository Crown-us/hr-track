<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, metadata-only view over the per-module event tables.
 * It selects who did what, when and to which record; never the `changes`, `reason` or `note` payloads.
 */
class AuditTrail
{
    /**
     * The array key, `action` and `subject` are SQL fragments taken from this constant only, never from request input.
     *
     * @var array<string, array{label: string, table: string, subject: string, action: string, route: ?string}>
     */
    public const SOURCES = [
        'employee' => ['label' => 'Pegawai', 'table' => 'employee_events', 'subject' => 'employee_id', 'action' => 'action', 'route' => 'employees.show'],
        'application' => ['label' => 'Lamaran', 'table' => 'application_status_histories', 'subject' => 'application_id', 'action' => "CONCAT('status:', new_status)", 'route' => 'applications.show'],
        'offer' => ['label' => 'Penawaran kerja', 'table' => 'recruitment_offer_events', 'subject' => 'recruitment_offer_id', 'action' => 'action', 'route' => null],
        'training' => ['label' => 'Pelatihan', 'table' => 'training_events', 'subject' => 'training_id', 'action' => 'action', 'route' => 'trainings.show'],
        'contract' => ['label' => 'Kontrak', 'table' => 'contract_events', 'subject' => 'employee_contract_id', 'action' => 'action', 'route' => 'contracts.show'],
        'discipline' => ['label' => 'Pelanggaran & SP', 'table' => 'discipline_events', 'subject' => 'disciplinary_case_id', 'action' => 'action', 'route' => 'discipline.show'],
        'account' => ['label' => 'Akun', 'table' => 'account_events', 'subject' => 'target_user_id', 'action' => 'action', 'route' => 'accounts.show'],
        'leave' => ['label' => 'Cuti & izin', 'table' => 'leave_events', 'subject' => 'leave_request_id', 'action' => 'action', 'route' => null],
        'attendance' => ['label' => 'Absensi', 'table' => 'attendance_events', 'subject' => 'attendance_day_id', 'action' => 'action', 'route' => null],
        'correction' => ['label' => 'Koreksi absensi', 'table' => 'attendance_correction_events', 'subject' => 'attendance_correction_id', 'action' => 'action', 'route' => null],
    ];

    /**
     * @param  array{source?: ?string, action?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function page(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return DB::query()
            ->fromSub($this->union($filters), 'e')
            ->leftJoin('users', 'users.id', '=', 'e.actor_id')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('e.action', $action))
            ->select('e.source', 'e.id', 'e.action', 'e.created_at', 'e.subject_id', 'users.name as actor_name')
            ->orderByDesc('e.created_at')
            ->orderBy('e.source')
            ->orderByDesc('e.id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => $this->present($row));
    }

    /**
     * Distinct action codes, used to fill the filter dropdown.
     *
     * @return list<string>
     */
    public function actions(?string $source): array
    {
        return DB::query()
            ->fromSub($this->union(['source' => $source]), 'e')
            ->distinct()
            ->orderBy('e.action')
            ->pluck('e.action')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function modules(): array
    {
        return array_map(fn (array $source) => $source['label'], self::SOURCES);
    }

    /**
     * @param  array{source?: ?string, from?: ?string, to?: ?string}  $filters
     */
    private function union(array $filters): Builder
    {
        $source = $filters['source'] ?? null;
        $sources = $source ? [$source => self::SOURCES[$source]] : self::SOURCES;
        $from = ($filters['from'] ?? null) ? CarbonImmutable::parse($filters['from'], 'Asia/Jakarta')->startOfDay()->utc()->toDateTimeString() : null;
        $to = ($filters['to'] ?? null) ? CarbonImmutable::parse($filters['to'], 'Asia/Jakarta')->addDay()->startOfDay()->utc()->toDateTimeString() : null;
        $union = null;
        foreach ($sources as $key => $definition) {
            $query = DB::table($definition['table'])
                ->selectRaw("'".$key."' as source, id, actor_id, ".$definition['action'].' as action, created_at, '.$definition['subject'].' as subject_id')
                ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->where('created_at', '<', $to));
            $union = $union ? $union->unionAll($query) : $query;
        }

        return $union;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $definition = self::SOURCES[$row->source];
        $subject = $row->subject_id === null ? null : (int) $row->subject_id;

        return [
            'key' => $row->source.'-'.$row->id,
            'module' => $definition['label'],
            'action' => $row->action,
            'actor' => $row->actor_name ?? 'Sistem',
            'subject_id' => $subject,
            'subject_url' => $subject && $definition['route'] ? route($definition['route'], $subject, false) : null,
            'created_at' => CarbonImmutable::parse($row->created_at, 'UTC')->toIso8601String(),
        ];
    }
}
