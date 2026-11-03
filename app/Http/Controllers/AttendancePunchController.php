<?php

namespace App\Http\Controllers;

use App\Services\AttendancePunches;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendancePunchController
{
    public function store(Request $r, AttendancePunches $punches)
    {
        $punches->employee($r->user());
        try {
            $data = $r->validate(['day_id' => ['required', 'integer', 'min:1'], 'version' => ['required', 'integer', 'min:1'], 'action' => ['required', Rule::in(['in', 'out'])], 'confirm' => ['accepted']]);
            $date = $punches->record($r->user(), $data);
        } catch (ValidationException $e) {
            throw $e->redirectTo(route('attendance.index'));
        }

        return redirect()->route('attendance.index', ['year' => substr($date, 0, 4), 'date' => $date])->with('success', $data['action'] === 'in' ? 'Jam masuk tersimpan dari waktu server.' : 'Jam pulang tersimpan dari waktu server.');
    }
}
