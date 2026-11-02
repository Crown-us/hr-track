<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\VacancyStatus;
use App\Models\Vacancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VacancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('vacancy') ? $this->user()->can('update', $this->route('vacancy')) : $this->user()->can('create', Vacancy::class);
    }

    public function rules(): array
    {
        return ['job_title' => ['required', 'string', 'max:255'], 'department_id' => ['required', 'integer', 'exists:departments,id'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)], 'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:10000'], 'requirements' => ['required', 'string', 'min:20', 'max:10000'],
            'opening_date' => ['required', 'date_format:Y-m-d'], 'closing_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:opening_date'],
            'status' => ['required', Rule::enum(VacancyStatus::class)], 'version' => [$this->route('vacancy') ? 'required' : 'sometimes', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['closing_date.after_or_equal' => 'Tanggal tutup tidak boleh sebelum tanggal buka.'];
    }

    public function attributes(): array
    {
        return ['job_title' => 'Judul lowongan', 'department_id' => 'Departemen', 'employment_type' => 'Jenis kerja', 'location' => 'Lokasi', 'description' => 'Deskripsi', 'requirements' => 'Persyaratan', 'opening_date' => 'Tanggal buka', 'closing_date' => 'Tanggal tutup', 'status' => 'Status'];
    }
}
