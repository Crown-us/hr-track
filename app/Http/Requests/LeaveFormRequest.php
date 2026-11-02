<?php

namespace App\Http\Requests;

use App\Models\LeaveRequest;
use App\Services\LeaveRecords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaveFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $s = app(LeaveRecords::class);
        if ($this->routeIs('leave.store')) {
            $s->own($this->user());
        } elseif ($this->routeIs('leave.action')) {
            $s->visible($this->user(), $this->route('leave'));
        } else {
            abort_unless($s->isHr($this->user()), 403);
        }

        return true;
    }

    protected function getRedirectUrl(): string
    {
        if ($this->routeIs('leave.store')) {
            return route('leave.create');
        }
        if ($this->routeIs('leave.action')) {
            return route('leave.show', $this->route('leave'));
        }

        return route('leave.settings', ['year' => $this->input('year', now('Asia/Jakarta')->year)]);
    }

    public function rules(): array
    {
        $reason = ['required', 'string', 'min:10', 'max:2000'];
        $year = ['required', 'integer', 'between:'.now('Asia/Jakarta')->year.','.(now('Asia/Jakarta')->year + 1)];

        return match ($this->route()->getName()) {
            'leave.store' => ['type' => ['required', Rule::in(array_keys(LeaveRequest::TYPES))], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d'], 'reason' => $reason, 'document' => ['nullable', ...ApplicationRequest::cvRules()]],
            'leave.action' => ['version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['approve', 'reject', 'cancel', 'approve_cancel', 'reject_cancel'])], 'reason' => $reason],
            'leave.balance' => ['employee_id' => ['required', 'integer'], 'year' => $year, 'days' => ['required', 'integer', 'between:0,366'], 'version' => ['required', 'integer', 'min:0'], 'reason' => $reason],
            'leave.holiday' => ['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Jakarta')->startOfYear()->toDateString(), 'before_or_equal:'.now('Asia/Jakarta')->addYear()->endOfYear()->toDateString()], 'name' => ['required', 'string', 'max:150'], 'reason' => $reason],
            'leave.holiday.remove' => ['reason' => $reason],
        };
    }

    public function messages(): array
    {
        return ['reason.min' => 'Alasan minimal 10 karakter.', 'document.max' => 'Berkas maksimal 5 MiB.', 'document.extensions' => 'Gunakan berkas PDF.', 'document.mimes' => 'Gunakan berkas PDF.'];
    }

    public function attributes(): array
    {
        return ['starts_on' => 'Tanggal mulai', 'ends_on' => 'Tanggal akhir', 'reason' => 'Alasan', 'employee_id' => 'Pegawai', 'days' => 'Alokasi hari', 'year' => 'Tahun', 'type' => 'Jenis pengajuan', 'date' => 'Tanggal libur', 'name' => 'Nama hari libur'];
    }
}
