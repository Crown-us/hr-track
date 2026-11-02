<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;
use XMLWriter;
use ZipArchive;

class TabularWorkbook
{
    private function column(int $n): string
    {
        $s = '';
        do {
            $s = chr(65 + $n % 26).$s;
            $n = intdiv($n, 26) - 1;
        } while ($n >= 0);

        return $s;
    }

    public function create(array $report): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hr-report-');
        if ($path === false) {
            throw new RuntimeException('File laporan sementara tidak dapat dibuat.');
        }
        $zip = new ZipArchive;
        try {
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Workbook tidak dapat dibuat.');
            }
            $parts = [
                '[Content_Types].xml' => '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
                '_rels/.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                'xl/workbook.xml' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Laporan HR" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
                'xl/styles.xml' => '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
            ];
            $xml = new XMLWriter;
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('worksheet');
            $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetViews');
            $xml->startElement('sheetView');
            $xml->writeAttribute('workbookViewId', '0');
            $xml->startElement('pane');
            foreach (['ySplit' => '6', 'topLeftCell' => 'A7', 'activePane' => 'bottomLeft', 'state' => 'frozen'] as $k => $v) {
                $xml->writeAttribute($k, $v);
            }$xml->endElement();
            $xml->endElement();
            $xml->endElement();
            $xml->startElement('cols');
            foreach ($report['headers'] as $i => $h) {
                $xml->startElement('col');
                $xml->writeAttribute('min', (string) ($i + 1));
                $xml->writeAttribute('max', (string) ($i + 1));
                $xml->writeAttribute('width', $i === 0 ? '34' : '24');
                $xml->writeAttribute('customWidth', '1');
                $xml->endElement();
            }$xml->endElement();
            $xml->startElement('sheetData');
            $rows = [['HRIS Lite · '.$report['title']], ['Periode '.$report['from'].' s.d. '.$report['to']], [$report['basis'].' · '.$report['status_label']], ['Dibuat '.CarbonImmutable::parse($report['generated_at'])->timezone('Asia/Jakarta')->format('Y-m-d H:i').' WIB'], [], $report['headers'], ...$report['rows']];
            foreach ($rows as $i => $row) {
                $xml->startElement('row');
                $xml->writeAttribute('r', (string) ($i + 1));
                foreach ($row as $j => $value) {
                    $xml->startElement('c');
                    $xml->writeAttribute('r', $this->column($j).($i + 1));
                    $xml->writeAttribute('t', 'inlineStr');
                    if ($i === 0 || $i === 5) {
                        $xml->writeAttribute('s', '1');
                    }$xml->startElement('is');
                    $xml->startElement('t');
                    $xml->writeAttribute('xml:space', 'preserve');
                    $xml->text(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u', '', (string) $value));
                    $xml->endElement();
                    $xml->endElement();
                    $xml->endElement();
                }$xml->endElement();
            }
            $xml->endElement();
            $xml->startElement('autoFilter');
            $xml->writeAttribute('ref', 'A6:'.$this->column(count($report['headers']) - 1).max(6, count($rows)));
            $xml->endElement();
            $xml->endElement();
            $xml->endDocument();
            $parts['xl/worksheets/sheet1.xml'] = $xml->outputMemory();
            foreach ($parts as $name => $content) {
                if (! $zip->addFromString($name, $content)) {
                    throw new RuntimeException('Workbook tidak dapat ditulis.');
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Workbook tidak dapat diselesaikan.');
            }

            return $path;
        } catch (Throwable $e) {
            if ($zip->status === ZipArchive::ER_OK) {
                try {
                    $zip->close();
                } catch (Throwable) {
                }
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $e;
        }
    }
}
