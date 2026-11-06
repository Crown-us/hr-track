<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public const NUMBER_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/';

    public const PHONE_PATTERN = '/^[+0-9() .-]{8,32}$/';

    public const LABELS = ['employee_number' => 'Nomor pegawai', 'full_name' => 'Nama lengkap', 'work_email' => 'Email kerja', 'phone' => 'Telepon', 'department_id' => 'Departemen', 'position_id' => 'Jabatan', 'employment_status' => 'Status kerja', 'join_date' => 'Tanggal bergabung'];

    public const FORMAT_MESSAGES = [
        'employee_number.regex' => 'Gunakan huruf, angka, titik, garis miring, tanda hubung atau garis bawah.',
        'phone.regex' => 'Nomor telepon harus berisi 8–32 karakter angka, spasi atau tanda telepon.',
    ];

    public function authorize(): bool
    {
        return $this->route('employee') ? $this->user()->can('update', $this->route('employee')) : $this->user()->can('create', Employee::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('work_email'))) {
            $this->merge(['work_email' => strtolower(trim($this->input('work_email')))]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;

        return [
            'employee_number' => ['required', 'string', 'max:50', 'regex:'.self::NUMBER_PATTERN, Rule::unique('employees')->ignore($id)],
            'full_name' => ['required', 'string', 'max:255'],
            'work_email' => ['required', 'email', 'max:255', Rule::unique('employees')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:32', 'regex:'.self::PHONE_PATTERN],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')->where('department_id', $this->input('department_id'))],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)], 'join_date' => ['required', 'date_format:Y-m-d'],
            'version' => [$id ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [...self::FORMAT_MESSAGES, 'employee_number.unique' => 'Nomor pegawai sudah dipakai, termasuk oleh pegawai yang diarsipkan.',
            'work_email.unique' => 'Email sudah dipakai, termasuk oleh pegawai yang diarsipkan.',
            'position_id.exists' => 'Pilih jabatan yang sesuai dengan departemen.', 'employment_status.enum' => 'Pilih status kerja yang tersedia.',
            'join_date.date_format' => 'Tanggal bergabung tidak valid.'];
    }

    public function attributes(): array
    {
        return self::LABELS;
    }
}
