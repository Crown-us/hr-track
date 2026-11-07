<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DisciplinaryCase;
use App\Models\EmployeeContract;
use App\Models\TrainingParticipant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrReports
{
    public const MODULES = ['recruitment' => 'Rekrutmen', 'training' => 'Evaluasi pelatihan', 'contracts' => 'Kontrak kerja', 'discipline' => 'Pelanggaran, SP & konseling'];

    public const BASES = ['recruitment' => 'Tanggal lamaran', 'training' => 'Tanggal mulai pelatihan (WIB)', 'contracts' => 'Tanggal mulai kontrak', 'discipline' => 'Tanggal kejadian'];

    public const CONTRACT_BASES = ['starts_on' => 'Tanggal mulai kontrak', 'ends_on' => 'Tanggal berakhir kontrak'];

    public const STATUSES = [
        'recruitment' => ['screening' => 'Screening', 'interview' => 'Interview', 'offering' => 'Offering', 'accepted' => 'Diterima', 'rejected' => 'Ditolak'],
        'training' => ['scheduled' => 'Dijadwalkan', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'],
        'contracts' => ['active' => 'Berjalan', 'scheduled' => 'Dijadwalkan', 'expired' => 'Berakhir', 'voided' => 'Dibatalkan'],
        'discipline' => DisciplinaryCase::STATUSES,
    ];

    public function make(array $f, ?string $format = null): array
    {
        return DB::transaction(function () use ($f, $format) {
            $module = $f['module'];
            $status = $f['status'] ?? null;
            $basis = self::BASES[$module];
            $headers = [];
            $map = null;
            if ($module === 'recruitment') {
                $q = Application::with(['applicant', 'vacancy'])->whereBetween('application_date', [$f['from'], $f['to']]);
                if ($status) {
                    $q->where('status', $status);
                }
                $q->orderBy('application_date')->orderBy('id');
                $headers = ['Pelamar', 'Lowongan', 'Tanggal lamaran', 'Tahap', 'Verifikasi email'];
                $map = fn ($a) => [$a->submitted_name ?? $a->applicant->full_name, $a->vacancy->job_title, $a->application_date->format('Y-m-d'), $a->status->label(), $a->source === 'public' ? ($a->email_verified_at ? 'Terverifikasi' : 'Belum terverifikasi') : 'Input HR'];
            } elseif ($module === 'training') {
                $start = CarbonImmutable::parse($f['from'], 'Asia/Jakarta')->startOfDay()->utc();
                $end = CarbonImmutable::parse($f['to'], 'Asia/Jakarta')->addDay()->startOfDay()->utc();
                $q = TrainingParticipant::with('employee')->join('trainings', 'trainings.id', '=', 'training_participants.training_id')->select('training_participants.*', 'trainings.title as training_title', 'trainings.starts_at as session_start')->where('trainings.starts_at', '>=', $start)->where('trainings.starts_at', '<', $end);
                if ($status) {
                    $q->where('trainings.status', $status);
                }
                $q->orderBy('trainings.starts_at')->orderBy('training_participants.id');
                $headers = ['Pelatihan', 'Tanggal mulai WIB', 'Pegawai', 'Nomor pegawai', 'Kehadiran', 'Pre-test', 'Post-test', 'Selisih poin'];
                $map = fn ($p) => [$p->training_title, CarbonImmutable::parse($p->session_start, 'UTC')->timezone('Asia/Jakarta')->format('Y-m-d'), $p->employee->full_name, $p->employee->employee_number, ['registered' => 'Terdaftar', 'attended' => 'Hadir', 'absent' => 'Tidak hadir'][$p->attendance], $p->pre_test === null ? '' : (string) $p->pre_test, $p->post_test === null ? '' : (string) $p->post_test, ($p->attendance === 'attended' && $p->pre_test !== null && $p->post_test !== null) ? (string) round($p->post_test - $p->pre_test, 2) : ''];
            } elseif ($module === 'contracts') {
                $column = $f['basis'] ?? 'starts_on';
                $basis = self::CONTRACT_BASES[$column];
                $q = EmployeeContract::with('employee')->whereBetween($column, [$f['from'], $f['to']]);
                $today = now('Asia/Jakarta')->toDateString();
                if ($status === 'voided') {
                    $q->whereNotNull('voided_at');
                } elseif ($status) {
                    $q->whereNull('voided_at');
                    if ($status === 'scheduled') {
                        $q->where('starts_on', '>', $today);
                    } elseif ($status === 'expired') {
                        $q->where('ends_on', '<', $today);
                    } else {
                        $q->where('starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today));
                    }
                }
                $q->orderBy($column)->orderBy('starts_on')->orderBy('id');
                $headers = ['Pegawai', 'Nomor pegawai', 'Nomor kontrak', 'Jenis', 'Mulai', 'Berakhir', 'Status saat ekspor'];
                $map = function ($c) {
                    $d = $c->forDisplay();

                    return [$d['employee_name'], $d['employee_number'], $d['contract_number'], strtoupper($d['type']), $d['starts_on'], $d['ends_on'] ?? '', $d['status_label']];
                };
            } else {
                $q = DisciplinaryCase::with('employee')->whereBetween('incident_on', [$f['from'], $f['to']]);
                if ($status) {
                    $q->where('status', $status);
                }
                $q->orderBy('incident_on')->orderBy('id');
                $headers = ['Pegawai', 'Nomor pegawai', 'Nomor catatan', 'Jenis', 'Tanggal kejadian', 'Tindak lanjut', 'Status'];
                $map = fn ($c) => [$c->employee->full_name, $c->employee->employee_number, $c->reference_number, DisciplinaryCase::KINDS[$c->kind], $c->incident_on->format('Y-m-d'), $c->follow_up_on?->format('Y-m-d') ?? '', DisciplinaryCase::STATUSES[$c->status]];
            }
            $total = (clone $q)->count();
            $limit = $format === 'xlsx' ? 1000 : ($format === 'pdf' ? 200 : 10);
            if ($format && $total > $limit) {
                throw ValidationException::withMessages(['limit' => 'Hasil '.$total.' baris melebihi batas '.$limit.' baris untuk '.strtoupper($format).'. Persempit tanggal atau status.'])->redirectTo(route('reports.index', array_intersect_key($f, array_flip(['module', 'from', 'to', 'status', 'basis']))));
            }

            return ['title' => self::MODULES[$module], 'basis' => $basis, 'from' => $f['from'], 'to' => $f['to'], 'status_label' => $status ? (self::STATUSES[$module][$status] ?? 'Semua status') : 'Semua status', 'generated_at' => now()->toIso8601String(), 'headers' => $headers, 'rows' => $q->limit($limit)->get()->map($map)->values()->all(), 'total' => $total];
        });
    }
}
