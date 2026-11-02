<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Services\HrReports;
use App\Services\TabularWorkbook;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

class ReportController
{
    public function index(ReportRequest $r, HrReports $reports)
    {
        $f = $r->validated();

        return Inertia::render('reports', ['filters' => $f, 'modules' => HrReports::MODULES, 'statuses' => HrReports::STATUSES[$f['module']], 'report' => $reports->make($f)]);
    }

    public function export(ReportRequest $r, HrReports $reports, TabularWorkbook $workbook)
    {
        $f = $r->validated();
        $data = $reports->make($f, $f['format']);
        $name = 'laporan-'.$f['module'].'-'.$f['from'].'-'.$f['to'];
        $headers = ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'];
        if ($f['format'] === 'xlsx') {
            return response()->download($workbook->create($data), $name.'.xlsx', [...$headers, 'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true);
        }
        $pdf = Pdf::loadView('pdf.hr-report', ['report' => $data])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4', 'landscape');
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(748, 566, '{PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.35, 0.38, 0.42]);

        return $pdf->download($name.'.pdf')->withHeaders($headers);
    }
}
