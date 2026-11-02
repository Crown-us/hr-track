<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContractActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('contract'));
    }

    protected function getRedirectUrl(): string
    {
        return route('contracts.show', $this->route('contract'));
    }

    public function rules(): array
    {
        return $this->routeIs('contracts.void')
            ? ['version' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'min:10', 'max:1000']]
            : ['document' => ['required', ...ApplicationRequest::cvRules()]];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Isi alasan pembatalan.', 'reason.min' => 'Alasan minimal 10 karakter.', 'document.required' => 'Pilih berkas PDF.', 'document.max' => 'Berkas maksimal 5 MiB.'];
    }
}
