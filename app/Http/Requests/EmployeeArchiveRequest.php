<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class EmployeeArchiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->routeIs('employees.restore') ? 'restore' : 'delete', $this->route('employee'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:20', 'max:1000'],
            'confirmation' => ['required', 'string', Rule::in([$this->route('employee')->employee_number])],
            'version' => ['required', 'integer', 'min:1'],
            ...($this->routeIs('employees.restore') ? [] : ['acknowledged' => ['accepted'], 'document' => ['bail', 'required', File::types(['pdf'])->max(5120), 'extensions:pdf',
                function ($attribute, $value, $fail) {
                    if (file_get_contents($value->getRealPath(), false, null, 0, 5) !== '%PDF-') {
                        $fail('Isi berkas harus berupa PDF yang valid.');
                    }
                }]])];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Alasan wajib diisi.', 'reason.min' => 'Jelaskan alasan minimal 20 karakter.', 'reason.max' => 'Alasan maksimal 1.000 karakter.',
            'confirmation.required' => 'Ketik nomor pegawai untuk mengonfirmasi.', 'confirmation.in' => 'Nomor konfirmasi belum cocok.',
            'acknowledged.accepted' => 'Konfirmasikan bahwa berkas pendukung sudah diperiksa.',
            'document.required' => 'Lampirkan PDF pendukung sebelum mengarsipkan.', 'document.mimes' => 'Berkas harus berupa PDF yang valid.',
            'document.extensions' => 'Ekstensi berkas harus .pdf.', 'document.max' => 'Berkas maksimal 5 MiB.'];
    }
}
