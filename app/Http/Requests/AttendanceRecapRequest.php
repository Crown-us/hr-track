<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceRecapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'hr_staff'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['month' => $this->input('month', now('Asia/Jakarta')->format('Y-m'))]);
    }

    protected function getRedirectUrl(): string
    {
        return route('attendance.recap');
    }

    public function rules(): array
    {
        return ['month' => ['required', 'date_format:Y-m', function ($attribute, $value, $fail) {
            if (! is_string($value) || $value < '2000-01' || $value > now('Asia/Jakarta')->format('Y-m')) {
                $fail('Pilih bulan dari Januari 2000 sampai bulan berjalan.');
            }
        }], 'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'format' => [$this->routeIs('attendance.recap.export') ? 'required' : 'sometimes', Rule::in(['xlsx', 'pdf'])]];
    }
}
