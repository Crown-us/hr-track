<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('position')
            ? $this->user()->can('update', $this->route('position'))
            : $this->user()->can('create', Position::class);
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->ignore($this->route('position'), 'id')->where('department_id', $this->department_id)],
        ];
    }

    public function attributes(): array
    {
        return ['department_id' => 'Departemen', 'name' => 'Nama jabatan'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'Nama jabatan sudah digunakan pada departemen ini.', 'name.required' => 'Isi nama jabatan.', 'department_id.required' => 'Pilih departemen.', 'department_id.exists' => 'Departemen tidak tersedia.'];
    }
}
