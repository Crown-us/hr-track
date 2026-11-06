<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'Nomor Pegawai,Nama Lengkap,Email Kerja,Telepon,Departemen,Jabatan,Status Kerja,Tanggal Bergabung';

    public function test_only_admin_and_hr_staff_can_use_the_import(): void
    {
        $this->get('/employees/import')->assertRedirect('/login');
        foreach (['viewer', 'employee', 'supervisor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get('/employees/import')->assertForbidden();
            $this->get('/employees/import/template')->assertForbidden();
            $this->post('/employees/import/preview', ['file' => $this->csv([])])->assertForbidden();
            $this->post('/employees/import', ['batch' => str_repeat('a', 40)])->assertForbidden();
        }
        $this->assertDatabaseCount('employees', 0);
        $this->actingAs(User::factory()->create(['role' => 'hr_staff']))->withoutVite()->get('/employees/import')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employee-import', false)->where('preview', null)->where('limits.rows', 500)->has('columns', 8));
    }

    public function test_template_lists_the_expected_columns(): void
    {
        $response = $this->actingAs($this->hr())->get('/employees/import/template')->assertOk();
        $this->assertStringContainsString('Nomor pegawai;Nama lengkap;Email kerja;Telepon;Departemen;Jabatan;Status kerja;Tanggal bergabung', $response->streamedContent());
    }

    public function test_preview_saves_nothing_and_commit_inserts_all_rows_with_audit(): void
    {
        $hr = $this->hr();
        $this->setUpOrganization();
        $token = $this->preview($hr, $this->csv([
            'EMP-001,Ayu Fiktif,AYU@example.test,08123456789,Human Resources,HR Staff,Tetap,2026-01-05',
            'EMP-002,Bima Fiktif,bima@example.test,,HR,HR Staff,contract,05/02/2026',
        ]));
        $this->assertDatabaseCount('employees', 0);
        $this->get('/employees/import?batch='.$token)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('preview.total', 2)->where('preview.valid', 2)->where('preview.invalid', 0)
                ->where('preview.rows.0.status', 'Tetap')->where('preview.rows.1.join_date', '2026-02-05')->where('preview.rows.1.department', 'Human Resources')->etc());

        $this->post('/employees/import', ['batch' => $token])->assertRedirect('/employees')->assertSessionHas('success', '2 pegawai berhasil diimpor.');
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-001', 'work_email' => 'ayu@example.test', 'employment_status' => 'permanent', 'join_date' => '2026-01-05']);
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-002', 'phone' => null, 'employment_status' => 'contract', 'join_date' => '2026-02-05']);
        $this->assertSame(2, DB::table('employee_events')->where('action', 'created')->where('actor_id', $hr->id)->count());
        $this->assertSame('import', json_decode(DB::table('employee_events')->first()->changes, true)['source']);

        $this->post('/employees/import', ['batch' => $token])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 2);
    }

    public function test_one_bad_row_blocks_the_whole_commit_and_is_explained(): void
    {
        $hr = $this->hr();
        $this->setUpOrganization();
        $archived = Employee::create([...$this->existing(), 'employee_number' => 'EMP-OLD', 'work_email' => 'old@example.test']);
        $archived->delete();
        $token = $this->preview($hr, $this->csv([
            'EMP-001,Ayu Fiktif,ayu@example.test,08123456789,Human Resources,HR Staff,Tetap,2026-01-05',
            'emp-001,Duplikat Nomor,dup@example.test,,Human Resources,HR Staff,Tetap,2026-01-05',
            'EMP-003,Email Rusak,bukan-email,,Human Resources,HR Staff,Tetap,2026-01-05',
            'EMP-004,Departemen Hilang,d@example.test,,Gudang,HR Staff,Tetap,2026-01-05',
            'EMP-005,Jabatan Salah,e@example.test,,Human Resources,Engineer,Tetap,2026-01-05',
            'EMP-006,Status Salah,f@example.test,,Human Resources,HR Staff,Bos,2026-01-05',
            'EMP-007,Tanggal Salah,g@example.test,,Human Resources,HR Staff,Tetap,2026-02-31',
            'EMP-OLD,Nomor Arsip,old2@example.test,,Human Resources,HR Staff,Tetap,2026-01-05',
            'EMP-009,Email Arsip,OLD@example.test,,Human Resources,HR Staff,Tetap,2026-01-05',
            'EMP-010,Telepon Salah,h@example.test,abc,Human Resources,HR Staff,Tetap,2026-01-05',
        ]));
        $this->get('/employees/import?batch='.$token)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('preview.total', 10)->where('preview.valid', 1)->where('preview.invalid', 9)
                ->where('preview.rows.0.errors', [])
                ->where('preview.rows.1.errors.0', 'Nomor pegawai sama dengan baris 2 di file ini.')
                ->where('preview.rows.2.errors.0', fn ($message) => str_contains($message, 'Email kerja'))
                ->where('preview.rows.3.errors.0', fn ($message) => str_contains($message, 'Departemen "Gudang" tidak ditemukan'))
                ->where('preview.rows.4.errors.0', fn ($message) => str_contains($message, 'Jabatan "Engineer" tidak ada'))
                ->where('preview.rows.5.errors.0', fn ($message) => str_contains($message, 'Status kerja "Bos"'))
                ->where('preview.rows.6.errors.0', fn ($message) => str_contains($message, 'Tanggal bergabung'))
                ->where('preview.rows.7.errors.0', 'Nomor pegawai sudah dipakai, termasuk oleh pegawai yang diarsipkan.')
                ->where('preview.rows.8.errors.0', 'Email sudah dipakai, termasuk oleh pegawai yang diarsipkan.')
                ->where('preview.rows.9.errors.0', fn ($message) => str_contains($message, 'Nomor telepon'))
                ->etc());

        $this->post('/employees/import', ['batch' => $token])->assertRedirect('/employees/import?batch='.$token)->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('employee_events', 0);
    }

    public function test_commit_rechecks_data_changed_after_the_preview(): void
    {
        $hr = $this->hr();
        $this->setUpOrganization();
        $token = $this->preview($hr, $this->csv(['EMP-001,Ayu Fiktif,ayu@example.test,,Human Resources,HR Staff,Tetap,2026-01-05']));
        Employee::create($this->existing());

        $this->post('/employees/import', ['batch' => $token])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_batches_are_private_to_the_user_who_uploaded_them(): void
    {
        $this->setUpOrganization();
        $token = $this->preview($this->hr(), $this->csv(['EMP-001,Ayu Fiktif,ayu@example.test,,Human Resources,HR Staff,Tetap,2026-01-05']));
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($other)->withoutVite()->get('/employees/import?batch='.$token)->assertInertia(fn (Assert $page) => $page->where('preview', null));
        $this->post('/employees/import', ['batch' => $token])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_semicolon_csv_with_bom_and_reordered_columns_is_accepted(): void
    {
        $this->setUpOrganization();
        $content = "\xEF\xBB\xBF".implode("\r\n", ['Nama;Email;Nomor Pegawai;Tanggal Masuk;Status;Divisi;Posisi',
            'Citra Fiktif;citra@example.test;EMP-100;5/1/2026;Magang;HR;HR Staff', '']);
        $token = $this->preview($this->hr(), UploadedFile::fake()->createWithContent('pegawai.csv', $content));
        $this->post('/employees/import', ['batch' => $token])->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-100', 'employment_status' => 'intern', 'join_date' => '2026-01-05']);
    }

    public function test_xlsx_with_excel_date_serial_is_accepted(): void
    {
        $this->setUpOrganization();
        $token = $this->preview($this->hr(), $this->xlsx([
            ['Nomor Pegawai', 'Nama Lengkap', 'Email Kerja', 'Departemen', 'Jabatan', 'Status Kerja', 'Tanggal Bergabung'],
            ['EMP-200', 'Dewi Fiktif', 'dewi@example.test', 'Human Resources', 'HR Staff', 'Tetap', '46027'],
        ]));
        $this->post('/employees/import', ['batch' => $token])->assertRedirect('/employees');
        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-200', 'join_date' => '2026-01-05']);
    }

    public function test_unreadable_or_oversized_files_are_rejected_before_anything_is_stored(): void
    {
        $hr = $this->hr();
        $this->setUpOrganization();
        $this->actingAs($hr);
        $this->post('/employees/import/preview', [])->assertSessionHasErrors('file');
        $this->post('/employees/import/preview', ['file' => UploadedFile::fake()->createWithContent('pegawai.pdf', '%PDF-1.4')])->assertSessionHasErrors('file');
        $this->post('/employees/import/preview', ['file' => UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan zip')])->assertSessionHasErrors('file');
        $this->post('/employees/import/preview', ['file' => UploadedFile::fake()->createWithContent('kolom.csv', "Nama,Email\nA,a@example.test\n")])->assertSessionHasErrors('file');
        $this->post('/employees/import/preview', ['file' => $this->csv([])])->assertSessionHasErrors('file');
        $rows = array_map(fn (int $i) => 'EMP-'.$i.',Nama '.$i.',p'.$i.'@example.test,,Human Resources,HR Staff,Tetap,2026-01-05', range(1, 501));
        $this->post('/employees/import/preview', ['file' => $this->csv($rows)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 0);
    }

    private function hr(): User
    {
        return User::factory()->create(['role' => 'hr_staff']);
    }

    private function setUpOrganization(): void
    {
        $department = Department::create(['code' => 'HR', 'name' => 'Human Resources']);
        Position::create(['department_id' => $department->id, 'name' => 'HR Staff']);
        $other = Department::create(['code' => 'IT', 'name' => 'Engineering']);
        Position::create(['department_id' => $other->id, 'name' => 'Engineer']);
    }

    private function existing(): array
    {
        $department = Department::where('code', 'HR')->firstOrFail();

        return ['employee_number' => 'EMP-001', 'full_name' => 'Pegawai Lama', 'work_email' => 'lama@example.test', 'phone' => null,
            'department_id' => $department->id, 'position_id' => Position::where('name', 'HR Staff')->firstOrFail()->id,
            'employment_status' => 'permanent', 'join_date' => '2025-01-01'];
    }

    /**
     * @param  list<string>  $lines
     */
    private function csv(array $lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pegawai.csv', implode("\n", $lines ? [self::HEADER, ...$lines] : []));
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $sheet = '';
        foreach ($rows as $r => $cells) {
            $sheet .= '<row r="'.($r + 1).'">';
            foreach ($cells as $c => $value) {
                $sheet .= '<c r="'.chr(65 + $c).($r + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $base = tempnam(sys_get_temp_dir(), 'import-test-');
        $path = $base.'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);
        unlink($base);

        return UploadedFile::fake()->createWithContent('pegawai.xlsx', $content);
    }

    private function preview(User $user, UploadedFile $file): string
    {
        $response = $this->actingAs($user)->withoutVite()->post('/employees/import/preview', ['file' => $file]);
        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        return $query['batch'];
    }
}
