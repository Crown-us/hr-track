<?php

namespace App\Services;

use InvalidArgumentException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads a CSV or XLSX file into rows keyed by canonical column names.
 * Dependency-free: XLSX is read through ZipArchive and SimpleXML. Formulas are never
 * evaluated; only the cached value stored in the file is read.
 */
class EmployeeImportReader
{
    public const MAX_ROWS = 500;

    public const REQUIRED = ['employee_number', 'full_name', 'work_email', 'department', 'position', 'employment_status', 'join_date'];

    public const LABELS = [
        'employee_number' => 'Nomor pegawai',
        'full_name' => 'Nama lengkap',
        'work_email' => 'Email kerja',
        'phone' => 'Telepon',
        'department' => 'Departemen',
        'position' => 'Jabatan',
        'employment_status' => 'Status kerja',
        'join_date' => 'Tanggal bergabung',
    ];

    private const ALIASES = [
        'employee_number' => ['nomorpegawai', 'nip', 'nomorinduk', 'employeenumber'],
        'full_name' => ['namalengkap', 'nama', 'fullname', 'name'],
        'work_email' => ['emailkerja', 'email', 'workemail'],
        'phone' => ['telepon', 'telp', 'nomortelepon', 'nohp', 'phone'],
        'department' => ['departemen', 'department', 'divisi'],
        'position' => ['jabatan', 'posisi', 'position'],
        'employment_status' => ['statuskerja', 'statuspegawai', 'status', 'employmentstatus'],
        'join_date' => ['tanggalbergabung', 'tanggalmasuk', 'tglbergabung', 'joindate'],
    ];

    private const MAX_XML_BYTES = 10 * 1024 * 1024;

    private const MAX_COLUMNS = 50;

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const NS_PKG = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /**
     * @return list<array{line: int, values: array<string, string>}>
     */
    public function read(string $path, string $extension): array
    {
        $table = match (strtolower($extension)) {
            'csv' => $this->csv($path),
            'xlsx' => $this->xlsx($path),
            default => throw new InvalidArgumentException('Format file harus .csv atau .xlsx.'),
        };

        return $this->rows($table);
    }

    /**
     * @param  array<int, list<string>>  $table  raw cells keyed by 1-based line number
     * @return list<array{line: int, values: array<string, string>}>
     */
    private function rows(array $table): array
    {
        $table = array_filter($table, fn (array $cells) => $this->filled($cells));
        if (! $table) {
            throw new InvalidArgumentException('File kosong. Isi baris pertama dengan judul kolom.');
        }
        ksort($table);
        $headerLine = array_key_first($table);
        $columns = $this->columns($table[$headerLine]);
        unset($table[$headerLine]);
        if (count($table) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal '.self::MAX_ROWS.' baris per impor. Bagi file menjadi beberapa bagian.');
        }
        if (! $table) {
            throw new InvalidArgumentException('File hanya berisi judul kolom. Tambahkan baris pegawai di bawahnya.');
        }
        $rows = [];
        foreach ($table as $line => $cells) {
            $values = array_fill_keys(array_keys(self::LABELS), '');
            foreach ($columns as $index => $key) {
                $values[$key] = trim($cells[$index] ?? '');
            }
            $rows[] = ['line' => $line, 'values' => $values];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @return array<int, string> column index => canonical key
     */
    private function columns(array $header): array
    {
        $lookup = [];
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$alias] = $key;
            }
        }
        $columns = [];
        foreach ($header as $index => $title) {
            $key = $lookup[preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($title)))] ?? null;
            if (! $key) {
                continue;
            }
            if (in_array($key, $columns, true)) {
                throw new InvalidArgumentException('Kolom "'.self::LABELS[$key].'" muncul lebih dari sekali.');
            }
            $columns[$index] = $key;
        }
        $missing = array_diff(self::REQUIRED, $columns);
        if ($missing) {
            throw new InvalidArgumentException('Kolom wajib belum ada: '.implode(', ', array_map(fn ($key) => self::LABELS[$key], $missing)).'.');
        }

        return $columns;
    }

    /**
     * @return array<int, list<string>>
     */
    private function csv(string $path): array
    {
        $content = (string) file_get_contents($path);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $first = strtok($content, "\r\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $table = [];
        $line = 0;
        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $line++;
            $table[$line] = array_map(fn ($cell) => (string) $cell, $cells);
        }
        fclose($stream);

        return $table;
    }

    /**
     * @return array<int, list<string>>
     */
    private function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('File .xlsx tidak dapat dibuka. Pastikan formatnya benar.');
        }
        try {
            $strings = $this->sharedStrings($zip);
            $sheet = $this->xml($zip, $this->sheetPath($zip) ?? '');
            $sheet->registerXPathNamespace('m', self::NS_MAIN);
            $table = [];
            $sequence = 0;
            foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
                $sequence++;
                $line = (int) ($row['r'] ?? 0) ?: $sequence;
                $sequence = $line;
                $cells = [];
                $next = 0;
                foreach ($row->children(self::NS_MAIN)->c as $cell) {
                    $index = $this->columnIndex((string) $cell->attributes()['r']) ?? $next;
                    $next = $index + 1;
                    if ($index >= self::MAX_COLUMNS) {
                        continue;
                    }
                    $cells[$index] = $this->cellValue($cell, $strings);
                }
                if ($cells) {
                    $table[$line] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
                }
            }

            return $table;
        } finally {
            $zip->close();
        }
    }

    private function sheetPath(ZipArchive $zip): ?string
    {
        $workbook = $this->xml($zip, 'xl/workbook.xml');
        $workbook->registerXPathNamespace('m', self::NS_MAIN);
        $sheet = ($workbook->xpath('//m:sheets/m:sheet') ?: [null])[0];
        $id = $sheet ? (string) $sheet->attributes(self::NS_REL)['id'] : '';
        $rels = $this->xml($zip, 'xl/_rels/workbook.xml.rels');
        $rels->registerXPathNamespace('p', self::NS_PKG);
        foreach ($rels->xpath('//p:Relationship') ?: [] as $rel) {
            if ($id !== '' && (string) $rel['Id'] === $id) {
                $target = ltrim((string) $rel['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return $zip->locateName('xl/worksheets/sheet1.xml') !== false ? 'xl/worksheets/sheet1.xml' : null;
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }
        $xml = $this->xml($zip, 'xl/sharedStrings.xml');
        $xml->registerXPathNamespace('m', self::NS_MAIN);
        $strings = [];
        foreach ($xml->xpath('//m:si') ?: [] as $item) {
            $item->registerXPathNamespace('m', self::NS_MAIN);
            $strings[] = $this->text($item);
        }

        return $strings;
    }

    private function text(SimpleXMLElement $node): string
    {
        $node->registerXPathNamespace('m', self::NS_MAIN);

        return implode('', array_map('strval', $node->xpath('.//m:t[not(ancestor::m:rPh)]') ?: []));
    }

    /**
     * @param  list<string>  $strings
     */
    private function cellValue(SimpleXMLElement $cell, array $strings): string
    {
        $type = (string) $cell->attributes()['t'];
        $cell = $cell->children(self::NS_MAIN);

        return match ($type) {
            's' => $strings[(int) $cell->v] ?? '',
            'inlineStr' => $this->text($cell->is),
            'b' => (string) $cell->v === '1' ? 'TRUE' : 'FALSE',
            'e' => '',
            default => (string) $cell->v,
        };
    }

    private function columnIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]{1,3})\d*$/', $reference, $match)) {
            return null;
        }
        $index = 0;
        foreach (str_split($match[1]) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }

        return $index - 1;
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stream = $name === '' ? false : $zip->getStream($name);
        if (! $stream) {
            throw new InvalidArgumentException('Isi file .xlsx tidak lengkap. Simpan ulang dari Excel atau Google Sheets.');
        }
        $content = stream_get_contents($stream, self::MAX_XML_BYTES + 1);
        fclose($stream);
        if (strlen($content) > self::MAX_XML_BYTES) {
            throw new InvalidArgumentException('Isi file .xlsx terlalu besar untuk diproses.');
        }
        // DOCTYPE/ENTITY declarations are never needed by spreadsheets and enable entity-expansion attacks.
        if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            throw new InvalidArgumentException('File .xlsx berisi struktur yang tidak didukung.');
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (! $xml) {
            throw new InvalidArgumentException('Isi file .xlsx tidak dapat dibaca.');
        }

        return $xml;
    }

    /**
     * @param  list<string>  $cells
     */
    private function filled(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return true;
            }
        }

        return false;
    }
}
