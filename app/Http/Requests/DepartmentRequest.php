<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('department')
            ? $this->user()->can('update', $this->route('department'))
            : $this->user()->can('create', Department::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($this->route('department'))],
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($this->route('department'))],
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'Kode departemen', 'name' => 'Nama departemen'];
    }

    public function messages(): array
    {
        return ['code.unique' => 'Kode departemen sudah digunakan.', 'name.unique' => 'Nama departemen sudah digunakan.', 'code.required' => 'Isi kode departemen.', 'name.required' => 'Isi nama departemen.'];
    }
}
