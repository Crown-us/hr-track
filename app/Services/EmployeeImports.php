<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Employee import in two steps: the file is parsed and kept server-side for a short time (preview),
 * then every row is validated again and inserted in one transaction (commit). All or nothing.
 */
class EmployeeImports
{
    private const TTL_MINUTES = 30;

    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y'];

    public function __construct(private EmployeeImportReader $reader, private EmployeeRecords $records) {}

    public function preview(User $actor, UploadedFile $file): string
    {
        try {
            $rows = $this->reader->read($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }
        $token = Str::random(40);
        Cache::put($this->key($actor, $token), ['file' => Str::limit($file->getClientOriginalName(), 120, ''), 'rows' => $rows], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * @return array{token: string, file: string, total: int, valid: int, invalid: int, rows: list<array<string, mixed>>}|null
     */
    public function review(User $actor, string $token): ?array
    {
        $batch = Cache::get($this->key($actor, $token));
        if (! $batch) {
            return null;
        }
        $analysis = $this->analyze($batch['rows']);

        return ['token' => $token, 'file' => $batch['file'], 'total' => count($batch['rows']), 'valid' => count($analysis['data']),
            'invalid' => count($batch['rows']) - count($analysis['data']), 'rows' => $analysis['rows']];
    }

    public function commit(User $actor, string $token): int
    {
        $batch = Cache::get($this->key($actor, $token));
        if (! $batch) {
            throw ValidationException::withMessages(['file' => 'Pratinjau sudah kedaluwarsa atau sudah diimpor. Unggah ulang file.']);
        }
        $analysis = $this->analyze($batch['rows']);
        if (count($analysis['data']) !== count($batch['rows'])) {
            throw ValidationException::withMessages(['file' => 'Data berubah sejak pratinjau dibuat. Periksa baris bertanda di bawah, lalu unggah ulang file yang sudah diperbaiki.']);
        }
        DB::transaction(function () use ($analysis, $actor) {
            foreach ($analysis['data'] as $data) {
                $this->records->save(null, $data, $actor, ['source' => 'import']);
            }
        });
        Cache::forget($this->key($actor, $token));

        return count($analysis['data']);
    }

    /**
     * @param  list<array{line: int, values: array<string, string>}>  $rows
     * @return array{rows: list<array<string, mixed>>, data: list<array<string, mixed>>}
     */
    private function analyze(array $rows): array
    {
        $departments = [];
        foreach (Department::all() as $department) {
            $departments[mb_strtolower($department->name)] = $department;
            $departments[mb_strtolower($department->code)] = $department;
        }
        $positions = [];
        foreach (Position::all() as $position) {
            $positions[$position->department_id][mb_strtolower($position->name)] = $position;
        }
        $numbers = array_column(array_column($rows, 'values'), 'employee_number');
        $emails = array_map('strtolower', array_column(array_column($rows, 'values'), 'work_email'));
        $takenNumbers = Employee::withTrashed()->whereIn('employee_number', $numbers)->pluck('employee_number')->map(fn ($value) => mb_strtolower($value))->flip();
        $takenEmails = Employee::withTrashed()->whereIn('work_email', $emails)->pluck('work_email')->map(fn ($value) => mb_strtolower($value))->flip();
        $seenNumbers = [];
        $seenEmails = [];
        $display = [];
        $valid = [];
        foreach ($rows as $row) {
            $values = $row['values'];
            $department = $departments[mb_strtolower($values['department'])] ?? null;
            $position = $department ? ($positions[$department->id][mb_strtolower($values['position'])] ?? null) : null;
            $status = $this->status($values['employment_status']);
            $date = $this->date($values['join_date']);
            $data = ['employee_number' => $values['employee_number'], 'full_name' => $values['full_name'], 'work_email' => strtolower($values['work_email']),
                'phone' => $values['phone'] === '' ? null : $values['phone'], 'department_id' => $department?->id, 'position_id' => $position?->id,
                'employment_status' => $status?->value, 'join_date' => $date];
            $errors = $this->textErrors($data);
            if (! $department) {
                $errors[] = 'Departemen "'.Str::limit($values['department'], 40).'" tidak ditemukan. Cocokkan dengan nama atau kode departemen.';
            } elseif (! $position) {
                $errors[] = 'Jabatan "'.Str::limit($values['position'], 40).'" tidak ada di departemen '.$department->name.'.';
            }
            if (! $status) {
                $errors[] = 'Status kerja "'.Str::limit($values['employment_status'], 30).'" tidak dikenal. Gunakan '.implode(', ', array_map(fn (EmploymentStatus $case) => $case->label(), EmploymentStatus::cases())).'.';
            }
            if (! $date) {
                $errors[] = 'Tanggal bergabung "'.Str::limit($values['join_date'], 30).'" tidak valid. Gunakan format 2026-01-31 atau 31/01/2026.';
            }
            $number = mb_strtolower($values['employee_number']);
            $email = strtolower($values['work_email']);
            if ($number !== '') {
                if (isset($seenNumbers[$number])) {
                    $errors[] = 'Nomor pegawai sama dengan baris '.$seenNumbers[$number].' di file ini.';
                } elseif ($takenNumbers->has($number)) {
                    $errors[] = 'Nomor pegawai sudah dipakai, termasuk oleh pegawai yang diarsipkan.';
                }
                $seenNumbers[$number] ??= $row['line'];
            }
            if ($email !== '') {
                if (isset($seenEmails[$email])) {
                    $errors[] = 'Email sama dengan baris '.$seenEmails[$email].' di file ini.';
                } elseif ($takenEmails->has($email)) {
                    $errors[] = 'Email sudah dipakai, termasuk oleh pegawai yang diarsipkan.';
                }
                $seenEmails[$email] ??= $row['line'];
            }
            $display[] = ['line' => $row['line'], 'employee_number' => $values['employee_number'], 'full_name' => $values['full_name'], 'work_email' => $values['work_email'],
                'department' => $department?->name ?? $values['department'], 'position' => $position?->name ?? $values['position'],
                'status' => $status?->label() ?? $values['employment_status'], 'join_date' => $date ?? $values['join_date'], 'errors' => $errors];
            if (! $errors) {
                $valid[] = $data;
            }
        }

        return ['rows' => $display, 'data' => $valid];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function textErrors(array $data): array
    {
        $validator = Validator::make($data, [
            'employee_number' => ['required', 'string', 'max:50', 'regex:'.EmployeeRequest::NUMBER_PATTERN],
            'full_name' => ['required', 'string', 'max:255'],
            'work_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:'.EmployeeRequest::PHONE_PATTERN],
        ], EmployeeRequest::FORMAT_MESSAGES, EmployeeRequest::LABELS);

        return array_values(array_unique($validator->errors()->all()));
    }

    private function status(string $value): ?EmploymentStatus
    {
        $value = mb_strtolower($value);
        foreach (EmploymentStatus::cases() as $case) {
            if ($value === $case->value || $value === mb_strtolower($case->label())) {
                return $case;
            }
        }

        return null;
    }

    private function date(string $value): ?string
    {
        $value = preg_replace('/[ T]00:00(:00)?$/', '', $value);
        // Excel stores dates as day counts since 1899-12-30; a plain integer in this column is such a serial.
        if (preg_match('/^\d{5}(\.0+)?$/', $value) && (int) $value >= 20000 && (int) $value <= 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }
        foreach (self::DATE_FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function key(User $actor, string $token): string
    {
        return 'employee-import:'.$actor->id.':'.$token;
    }
}
