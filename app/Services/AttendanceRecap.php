<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceRecap
{
    public const COLUMNS = ['present' => 'Hadir lengkap', 'late' => 'Terlambat', 'annual' => 'Cuti', 'permission' => 'Izin', 'sick' => 'Sakit', 'absent' => 'Tanpa keterangan', 'review' => 'Perlu diperiksa', 'unknown' => 'Belum ada data', 'running' => 'Berjalan', 'off' => 'Libur', 'future' => 'Mendatang'];

    public function make(array $f, ?string $format = null): array
    {
        return DB::transaction(function () use ($f, $format) {
            $now = CarbonImmutable::now('Asia/Jakarta');
            $first = CarbonImmutable::createFromFormat('!Y-m', $f['month'], 'Asia/Jakarta');
            $last = $first->endOfMonth()->startOfDay();
            $q = Employee::withTrashed()->with('department')->where('join_date', '<=', $last->toDateString());
            if ($f['department_id'] ?? null) {
                $q->where('department_id', $f['department_id']);
            }
            $term = trim($f['q'] ?? '');
            if ($term !== '') {
                $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
            }
            $total = (clone $q)->count();
            if (! $format && $total > 1000) {
                return ['month' => $f['month'], 'from' => $first->toDateString(), 'to' => $last->toDateString(), 'as_of' => $now->toDateString(), 'generated_at' => $now->utc()->toIso8601String(), 'total' => $total, 'summary' => null, 'employees' => [], 'filter_label' => 'Hasil terlalu luas', 'limited' => true];
            }
            $limit = $format === 'pdf' ? 200 : 1000;
            if ($total > $limit) {
                throw ValidationException::withMessages(['limit' => 'Hasil '.$total.' pegawai melebihi batas '.$limit.($format ? ' untuk '.strtoupper($format) : '').'. Persempit departemen atau pencarian pegawai.'])->redirectTo(route('attendance.recap', array_intersect_key($f, array_flip(['month', 'department_id', 'q']))));
            }
            $employees = $q->orderBy('full_name')->orderBy('id')->get(['id', 'full_name', 'employee_number', 'department_id', 'employment_status', 'join_date', 'deleted_at']);
            $ids = $employees->modelKeys();
            $days = AttendanceDay::whereIn('employee_id', $ids)->whereBetween('date', [$first->toDateString(), $last->toDateString()])->get()->groupBy('employee_id')->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->date->format('Y-m-d')));
            $leaves = [];
            foreach (LeaveRequest::whereIn('employee_id', $ids)->where('year', $first->year)->whereIn('status', ['approved', 'cancel_pending'])->get(['employee_id', 'type', 'work_dates']) as $leave) {
                foreach ($leave->work_dates as $date) {
                    if ($date >= $first->toDateString() && $date <= $last->toDateString()) {
                        $leaves[$leave->employee_id][$date] = ['type' => $leave->type];
                    }
                }
            }
            $holidays = DB::table('leave_holidays')->whereBetween('date', [$first->toDateString(), $last->toDateString()])->pluck('name', 'date');
            $summary = array_fill_keys([...array_keys(self::COLUMNS), 'on_time'], 0);
            $rows = [];
            $classifier = app(AttendanceStatus::class);
            foreach ($employees as $e) {
                $counts = array_fill_keys(array_keys($summary), 0);
                for ($date = $first; $date <= $last; $date = $date->addDay()) {
                    $key = $date->toDateString();
                    $leave = $leaves[$e->id][$key] ?? null;
                    $status = $classifier->classify($key, $e->join_date->format('Y-m-d'), $days[$e->id][$key] ?? null, $leave, isset($holidays[$key]) || $date->isWeekend(), $now)['status'];
                    if ($status === 'not_employed') {
                        continue;
                    }
                    $column = match ($status) {
                        'present' => 'present', 'late' => 'late', 'leave' => $leave['type'], 'scheduled','waiting','working' => 'running', default => $status
                    };
                    $counts[$column]++;
                    if ($status === 'present') {
                        $counts['on_time']++;
                    }
                    if ($status === 'late') {
                        $counts['present']++;
                    }
                }
                foreach ($counts as $key => $value) {
                    $summary[$key] += $value;
                }
                $rows[] = ['id' => $e->id, 'full_name' => $e->full_name, 'employee_number' => $e->employee_number, 'department' => $e->department->name, 'status_label' => $e->employment_status->label(), 'archived' => $e->trashed(), 'counts' => $counts];
            }
            $department = empty($f['department_id']) ? 'Semua departemen' : ($employees->first()?->department->name ?? Department::find($f['department_id'])?->name ?? 'Departemen');
            $filterLabel = $department.' · Pencarian: '.($term !== '' ? $term : 'Semua pegawai');

            return ['month' => $f['month'], 'from' => $first->toDateString(), 'to' => $last->toDateString(), 'as_of' => $now->toDateString(), 'generated_at' => $now->utc()->toIso8601String(), 'total' => $total, 'summary' => $summary, 'employees' => $rows, 'filter_label' => $filterLabel];
        });
    }

    public function tabular(array $data): array
    {
        $rows = array_map(fn ($r) => [$r['full_name'].' / '.$r['employee_number'].' / '.($r['archived'] ? 'Diarsipkan' : $r['status_label']), $r['department'], ...array_map(fn ($k) => $r['counts'][$k], array_keys(self::COLUMNS))], $data['employees']);
        if ($rows) {
            $rows[] = ['TOTAL (hari-pegawai)', '', ...array_map(fn ($k) => $data['summary'][$k], array_keys(self::COLUMNS))];
        }

        return [...array_diff_key($data, ['employees' => true]), 'title' => 'Rekap absensi bulanan', 'basis' => 'Tanggal awal jadwal WIB; sampai '.$data['as_of'].'. Hadir termasuk terlambat. Data kosong bukan absen. Status pegawai saat ini, termasuk arsip; sebelum bergabung tidak dihitung', 'status_label' => $data['filter_label'], 'headers' => ['Pegawai / nomor / status saat ini', 'Departemen', ...array_values(self::COLUMNS)], 'rows' => $rows];
    }
}
