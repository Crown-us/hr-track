<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'extensions:csv,xlsx', 'max:2048']];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file .csv atau .xlsx terlebih dahulu.',
            'file.file' => 'File tidak dapat diunggah. Coba pilih ulang.',
            'file.extensions' => 'Format file harus .csv atau .xlsx.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
            'file.uploaded' => 'File gagal diunggah. Periksa ukuran (maksimal 2 MB) lalu coba lagi.',
        ];
    }
}
