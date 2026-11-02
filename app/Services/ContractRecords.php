<?php

namespace App\Services;

use App\Models\ContractDocument;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ContractRecords
{
    private function audit(EmployeeContract $c, User $u, string $action, array $changes): void
    {
        DB::table('contract_events')->insert(['employee_contract_id' => $c->id, 'actor_id' => $u->id, 'action' => $action, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function locked(EmployeeContract $contract, ?int $version = null): EmployeeContract
    {
        Employee::withTrashed()->whereKey($contract->employee_id)->lockForUpdate()->firstOrFail();
        $c = EmployeeContract::whereKey($contract->id)->lockForUpdate()->firstOrFail();
        if ($version !== null && $c->version !== $version) {
            throw ValidationException::withMessages(['version' => 'Data sudah berubah. Muat ulang halaman sebelum melanjutkan.']);
        }
        if ($c->voided_at) {
            throw ValidationException::withMessages(['contract' => 'Kontrak yang dibatalkan tidak dapat diubah.']);
        }

        return $c;
    }

    public function save(?EmployeeContract $contract, array $data, User $u): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $data, $u) {
            if ($contract) {
                $c = $this->locked($contract, (int) $data['version']);
                if ($c->employee_id !== (int) $data['employee_id']) {
                    throw ValidationException::withMessages(['employee_id' => 'Pegawai pada kontrak tidak dapat diganti.']);
                }
            } else {
                $e = Employee::whereKey($data['employee_id'])->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
                if (! $e) {
                    throw ValidationException::withMessages(['employee_id' => 'Pilih pegawai aktif yang belum diarsipkan.']);
                }
                $c = new EmployeeContract(['employee_id' => $e->id, 'created_by' => $u->id]);
            }
            $overlap = EmployeeContract::where('employee_id', $c->employee_id)->whereNull('voided_at')
                ->when($contract, fn ($q) => $q->where('id', '!=', $c->id))
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data['starts_on']))
                ->when(! empty($data['ends_on']), fn ($q) => $q->where('starts_on', '<=', $data['ends_on']))->lockForUpdate()->first();
            if ($overlap) {
                throw ValidationException::withMessages(['starts_on' => 'Periode bertumpuk dengan kontrak '.$overlap->contract_number.'. Buat periode lanjutan setelah kontrak sebelumnya berakhir.']);
            }
            $fields = ['contract_number', 'type', 'starts_on', 'ends_on', 'notes'];
            $before = $contract ? $c->only($fields) : [];
            $data['ends_on'] = $data['type'] === 'pkwtt' ? null : $data['ends_on'];
            $c->fill(array_intersect_key($data, array_flip($fields)));
            if ($contract) {
                $c->version++;
            }
            $c->save();
            $this->audit($c, $u, $contract ? 'updated' : 'created', ['before' => $before, 'after' => $c->only($fields), 'reason' => $data['reason'] ?? null]);

            return $c;
        });
    }

    public function void(EmployeeContract $contract, array $data, User $u): void
    {
        DB::transaction(function () use ($contract, $data, $u) {
            $c = $this->locked($contract, (int) $data['version']);
            $c->voided_at = now();
            $c->version++;
            $c->save();
            $this->audit($c, $u, 'voided', ['reason' => $data['reason']]);
        });
    }

    public function upload(EmployeeContract $contract, UploadedFile $file, User $u): void
    {
        $path = null;
        try {
            DB::transaction(function () use ($contract, $file, $u, &$path) {
                $c = $this->locked($contract);
                $v = (int) ContractDocument::where('employee_contract_id', $c->id)->max('version') + 1;
                $path = $file->store('contracts/'.$c->id, 'contract_documents');
                $d = ContractDocument::create(['employee_contract_id' => $c->id, 'uploaded_by' => $u->id, 'version' => $v, 'path' => $path, 'size_bytes' => $file->getSize(), 'created_at' => now()]);
                $this->audit($c, $u, 'document_uploaded', ['document_id' => $d->id, 'version' => $v]);
            });
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('contract_documents')->delete($path);
            } throw $e;
        }
    }

    public function summary(): array
    {
        return ['due_count' => EmployeeContract::attention('due')->count(), 'expired_count' => EmployeeContract::attention('expired')->count(),
            'recent' => EmployeeContract::attention('due')->with('employee')->orderBy('ends_on')->orderBy('id')->limit(5)->get()->map(fn ($c) => $c->forDisplay())->all()];
    }
}
