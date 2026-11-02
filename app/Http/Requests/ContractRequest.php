<?php

namespace App\Http\Requests;

use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractRequest extends FormRequest
{
    protected function getRedirectUrl(): string
    {
        return $this->route('contract') ? route('contracts.edit', $this->route('contract')) : route('contracts.create');
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', EmployeeContract::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->contract_number)) {
            $this->merge(['contract_number' => trim($this->contract_number)]);
        }
    }

    public function rules(): array
    {
        $contract = $this->route('contract');

        return ['employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'contract_number' => ['required', 'string', 'max:100', Rule::unique('employee_contracts', 'contract_number')->ignore($contract?->id)],
            'type' => ['required', Rule::in(['pkwt', 'pkwtt'])], 'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => [$this->input('type') === 'pkwt' ? 'required' : 'prohibited', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:3000'], 'reason' => [$contract ? 'required' : 'nullable', 'string', 'min:10', 'max:1000'],
            'version' => [$contract ? 'required' : 'sometimes', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['ends_on.required' => 'PKWT memerlukan tanggal berakhir.', 'ends_on.prohibited' => 'PKWTT tidak memakai tanggal berakhir.',
            'ends_on.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal mulai.', 'reason.required' => 'Isi alasan koreksi.', 'reason.min' => 'Alasan minimal 10 karakter.',
            'contract_number.unique' => 'Nomor kontrak sudah digunakan.', 'employee_id.exists' => 'Pegawai tidak ditemukan.'];
    }
}
