<?php

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Application::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['mode' => ['required', Rule::in(['new', 'existing'])], 'full_name' => ['required_if:mode,new', 'nullable', 'string', 'max:255'],
            'email' => ['required_if:mode,new', 'nullable', 'email', 'max:255', ...($this->input('mode') === 'new' ? [Rule::unique('applicants', 'email')] : [])],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[+0-9() .-]{8,32}$/'],
            'applicant_id' => ['required_if:mode,existing', 'nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')],
            'vacancy_id' => ['required', 'integer', 'exists:vacancies,id'], 'application_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'notes' => ['nullable', 'string', 'max:3000'], 'cv' => ['nullable', ...self::cvRules()]];
    }

    public static function cvRules(): array
    {
        return ['bail', File::types(['pdf'])->max(5120), 'extensions:pdf', function ($attribute, $value, $fail) {
            if (file_get_contents($value->getRealPath(), false, null, 0, 5) !== '%PDF-') {
                $fail('Isi CV harus berupa PDF yang valid.');
            }
        }];
    }

    public function messages(): array
    {
        return ['email.unique' => 'Email sudah terdaftar. Pilih tab Pelamar terdaftar agar profil tidak digandakan.', 'cv.mimes' => 'CV harus berupa PDF.', 'cv.max' => 'CV maksimal 5 MiB.', 'cv.extensions' => 'Ekstensi CV harus .pdf.', 'full_name.required_if' => 'Nama lengkap wajib diisi.', 'email.required_if' => 'Email wajib diisi.', 'applicant_id.required_if' => 'Pilih pelamar terdaftar.', 'application_date.before_or_equal' => 'Tanggal lamaran tidak boleh di masa depan.'];
    }

    public function attributes(): array
    {
        return ['full_name' => 'Nama lengkap', 'email' => 'Email', 'phone' => 'Telepon', 'vacancy_id' => 'Lowongan', 'application_date' => 'Tanggal lamaran', 'notes' => 'Catatan HR', 'cv' => 'CV'];
    }
}
