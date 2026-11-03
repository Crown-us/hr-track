<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRecapRequest;
use App\Models\Department;
use App\Services\AttendanceRecap;
use App\Services\TabularWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class AttendanceRecapController
{
    public function index(AttendanceRecapRequest $r, AttendanceRecap $recap)
    {
        $f = $r->validated();
        $data = $recap->make($f);
        $page = (int) ($f['page'] ?? 1);
        $employees = new LengthAwarePaginator(array_slice($data['employees'], ($page - 1) * 15, 15), $data['total'], 15, $page, ['path' => route('attendance.recap'), 'query' => $r->query()]);
        unset($data['employees']);

        return Inertia::render('attendance-recap', ['filters' => $f, 'report' => $data, 'employees' => $employees, 'columns' => AttendanceRecap::COLUMNS, 'departments' => Department::orderBy('name')->get(['id', 'name'])]);
    }

    public function export(AttendanceRecapRequest $r, AttendanceRecap $recap, TabularWorkbook $workbook)
    {
        $f = $r->validated();
        $data = $recap->tabular($recap->make($f, $f['format']));
        $headers = ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'];
        $name = 'rekap-absensi-'.$f['month'];
        if ($f['format'] === 'xlsx') {
            return response()->download($workbook->create($data), $name.'.xlsx', [...$headers, 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        }
        $pdf = Pdf::loadView('pdf.attendance-recap', ['report' => $data])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4', 'landscape');
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(748, 566, '{PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.35, 0.38, 0.42]);

        return $pdf->download($name.'.pdf')->withHeaders($headers);
    }
}
