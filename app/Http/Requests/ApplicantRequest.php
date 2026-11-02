<?php

namespace App\Http\Requests;

use App\Models\Applicant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Applicant::class);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('applicants', 'email')->ignore($this->route('applicant'))],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'email' => 'Email pelamar',
            'phone' => 'Telepon (opsional)',
        ];
    }

    public function messages(): array
    {
        return ['full_name.required' => 'Isi nama lengkap pelamar.', 'email.required' => 'Isi email pelamar.', 'email.email' => 'Gunakan alamat email yang valid.', 'email.unique' => 'Email sudah terdaftar. Gunakan profil pelamar yang sudah ada.'];
    }
}
