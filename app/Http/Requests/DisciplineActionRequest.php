<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisciplineActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('case'));
    }

    protected function getRedirectUrl(): string
    {
        return route('discipline.show', $this->route('case'));
    }

    public function rules(): array
    {
        return $this->routeIs('discipline.status') ? ['version' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['resolved', 'voided'])], 'reason' => ['required', 'string', 'min:10', 'max:2000']] : ['document' => ['required', ...ApplicationRequest::cvRules()]];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Isi hasil tindak lanjut atau alasan pembatalan.', 'reason.min' => 'Catatan minimal 10 karakter.', 'document.required' => 'Pilih berkas PDF.', 'document.max' => 'Berkas maksimal 5 MiB.'];
    }
}
