<?php

namespace App\Http\Requests;

use App\Models\DisciplinaryCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', DisciplinaryCase::class);
    }

    protected function getRedirectUrl(): string
    {
        return $this->route('case') ? route('discipline.edit', $this->route('case')) : route('discipline.create');
    }

    public function rules(): array
    {
        $case = $this->route('case');
        $documentRules = $case ? ['prohibited', 'nullable'] : [in_array($this->input('kind'), ['sp1', 'sp2', 'sp3'], true) ? 'required' : 'nullable'];

        return ['employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'reference_number' => ['required', 'string', 'max:100', Rule::unique('disciplinary_cases', 'reference_number')->ignore($case?->id)],
            'kind' => ['required', Rule::in(array_keys(DisciplinaryCase::KINDS))],
            'incident_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'subject' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:5000'],
            'follow_up_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:incident_on'],
            'reason' => [$case ? 'required' : 'nullable', 'string', 'min:10', 'max:1000'],
            'version' => [$case ? 'required' : 'sometimes', 'integer', 'min:1'],
            'document' => [...$documentRules, ...ApplicationRequest::cvRules()]];
    }

    public function messages(): array
    {
        return ['document.required' => 'Lampirkan PDF surat peringatan.', 'document.max' => 'Berkas maksimal 5 MiB.', 'reference_number.unique' => 'Nomor catatan sudah digunakan.',
            'incident_on.before_or_equal' => 'Tanggal kejadian tidak boleh di masa depan.', 'follow_up_on.after_or_equal' => 'Tindak lanjut tidak boleh sebelum tanggal kejadian.',
            'reason.required' => 'Isi alasan koreksi.', 'reason.min' => 'Alasan minimal 10 karakter.'];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'Pegawai', 'reference_number' => 'Nomor catatan', 'incident_on' => 'Tanggal kejadian', 'subject' => 'Judul', 'description' => 'Uraian', 'follow_up_on' => 'Tanggal tindak lanjut', 'document' => 'Berkas'];
    }
}
