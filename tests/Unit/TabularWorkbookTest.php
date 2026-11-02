<?php

namespace Tests\Unit;

use App\Services\TabularWorkbook;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class TabularWorkbookTest extends TestCase
{
    public function test_xml_invalid_unicode_is_removed_from_exported_cells(): void
    {
        $report = ['title' => 'Catatan', 'from' => '2026-11-01', 'to' => '2026-11-02', 'basis' => 'Tanggal kejadian', 'status_label' => 'Semua status', 'generated_at' => '2026-11-02T00:00:00Z', 'headers' => ['Nama'], 'rows' => [["Nama\u{FFFF}\u{FFFE}\u{0001} & Tim"]]];
        $path = (new TabularWorkbook)->create($report);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $old = libxml_use_internal_errors(true);
            try {
                $parsed = simplexml_load_string($xml);
                $this->assertNotFalse($parsed);
                $this->assertStringContainsString('Nama &amp; Tim', $xml);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($old);
            }
        } finally {
            unlink($path);
        }
    }
}
