<?php

namespace App\Http\Requests;

use App\Services\AttendanceRecords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AttendanceRecords::class)->isHr($this->user());
    }

    protected function getRedirectUrl(): string
    {
        $date = $this->input('date');
        if (! is_string($date) || ! preg_match('/^(20\d{2}|2100)-\d{2}-\d{2}$/', $date)) {
            $date = now('Asia/Jakarta')->toDateString();
        }

        return route('attendance.employee', ['employee' => $this->route('employee'), 'year' => substr($date, 0, 4), 'date' => $date]);
    }

    public function rules(): array
    {
        $time = ['nullable', 'date_format:Y-m-d\TH:i'];

        return ['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'], 'scheduled_start' => [$this->input('action') === 'save' ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'], 'scheduled_end' => [$this->input('action') === 'save' ? 'required' : 'nullable', 'date_format:Y-m-d\TH:i'], 'clock_in' => $time, 'clock_out' => $time, 'tolerance_minutes' => ['required', 'integer', 'between:0,120'], 'version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:10', 'max:2000'], 'action' => ['required', Rule::in(['save', 'void'])]];
    }

    public function attributes(): array
    {
        return ['date' => 'Tanggal kerja', 'scheduled_start' => 'Awal jadwal', 'scheduled_end' => 'Akhir jadwal', 'clock_in' => 'Jam masuk', 'clock_out' => 'Jam pulang', 'tolerance_minutes' => 'Toleransi', 'reason' => 'Alasan pencatatan'];
    }
}
