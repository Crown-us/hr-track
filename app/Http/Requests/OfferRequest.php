<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('application'));
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', Rule::exists('positions', 'id')->where('department_id', $this->input('department_id'))],
            'employment_status' => ['required', Rule::in(['permanent', 'contract', 'probation', 'intern'])],
            'response_deadline' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'join_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:response_deadline'],
            'terms' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return ['position_id.exists' => 'Pilih jabatan sesuai departemen.', 'response_deadline.after_or_equal' => 'Batas respons tidak boleh sebelum hari ini (WIB).', 'join_date.after_or_equal' => 'Tanggal mulai tidak boleh sebelum batas respons.'];
    }
}
