<?php

namespace App\Http\Requests;

use App\Services\HrReports;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'hr_staff'], true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['module' => $this->input('module', 'recruitment'), 'from' => $this->input('from', now('Asia/Jakarta')->startOfMonth()->toDateString()), 'to' => $this->input('to', now('Asia/Jakarta')->toDateString())]);
    }

    protected function getRedirectUrl(): string
    {
        return route('reports.index');
    }

    public function rules(): array
    {
        $module = is_string($this->input('module')) ? $this->input('module') : '';

        return ['module' => ['required', Rule::in(array_keys(HrReports::MODULES))], 'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::in(array_keys(HrReports::STATUSES[$module] ?? []))],
            'format' => [$this->routeIs('reports.export') ? 'required' : 'sometimes', Rule::in(['xlsx', 'pdf'])]];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            if (CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 365) {
                $v->errors()->add('to', 'Pilih periode maksimal 366 hari.');
            }
        });
    }

    public function messages(): array
    {
        return ['to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum awal.', 'module.in' => 'Pilih jenis laporan yang tersedia.', 'status.in' => 'Status tidak cocok dengan jenis laporan.'];
    }

    public function attributes(): array
    {
        return ['from' => 'Tanggal awal', 'to' => 'Tanggal akhir', 'module' => 'Jenis laporan'];
    }
}
