<?php

namespace App\Http\Requests;

use App\Models\Training;
use Illuminate\Foundation\Http\FormRequest;

class TrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Training::class);
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:255'], 'trainer' => ['required', 'string', 'max:255'], 'location' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:3000'], 'version' => [$this->route('training') ? 'required' : 'sometimes', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['title.required' => 'Isi judul pelatihan.', 'trainer.required' => 'Isi nama pemateri.', 'location.required' => 'Isi lokasi pelatihan.', 'starts_at.date_format' => 'Gunakan tanggal dan jam WIB yang valid.', 'ends_at.date_format' => 'Gunakan tanggal dan jam WIB yang valid.', 'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.'];
    }
}
