<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class EmployeeRecords
{
    public function save(?Employee $employee, array $data, User $actor): Employee
    {
        try {
            return DB::transaction(function () use ($employee, $data, $actor) {
                $record = $employee ? Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail() : new Employee;
                if ($employee) {
                    $this->checkVersion($record, $data['version']);
                }
                $before = $record->only($record->getFillable());
                $record->fill($data);
                if ($employee) {
                    $record->version++;
                }
                $record->save();
                $this->event($record, $actor, $employee ? 'updated' : 'created', ['changes' => ['before' => $before, 'after' => $record->only($record->getFillable())]]);

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'Nomor pegawai atau email sudah digunakan. Muat ulang dan periksa data.']);
        }
    }

    public function archive(Employee $employee, array $data, UploadedFile $file, User $actor): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($employee, $data, $file, $actor, &$path) {
                $record = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
                $this->checkVersion($record, $data['version']);
                $path = $file->store('employees/'.$record->id, 'employee_records');
                $this->event($record, $actor, 'archived', ['reason' => $data['reason'], 'document_path' => $path, 'document_size' => $file->getSize()]);
                $record->version++;
                $record->save();
                $record->delete();
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('employee_records')->delete($path);
            }
            throw $exception;
        }
    }

    public function restore(Employee $employee, array $data, User $actor): void
    {
        DB::transaction(function () use ($employee, $data, $actor) {
            $record = Employee::onlyTrashed()->whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $this->checkVersion($record, $data['version']);
            $record->version++;
            $record->restore();
            $this->event($record, $actor, 'restored', ['reason' => $data['reason']]);
        });
    }

    private function checkVersion(Employee $employee, int $version): void
    {
        if ($employee->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Data sudah berubah sejak formulir dibuka. Muat ulang halaman sebelum melanjutkan.']);
        }
    }

    private function event(Employee $employee, User $actor, string $action, array $extra = []): void
    {
        EmployeeEvent::create(['employee_id' => $employee->id, 'actor_id' => $actor->id, 'action' => $action, 'created_at' => now(), ...$extra]);
    }
}
