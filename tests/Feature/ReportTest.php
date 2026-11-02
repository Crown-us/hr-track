<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_recruitment_rows_follow_application_date_and_stage_without_contact_details(): void
    {
        [$u,$e] = $this->setupHr();
        $v = Vacancy::create(['department_id' => $e->department_id, 'job_title' => 'Staf Operasional', 'employment_type' => 'full_time', 'location' => 'Kantor', 'description' => 'Tugas operasional', 'requirements' => 'Komunikasi baik', 'opening_date' => '2026-01-01', 'closing_date' => '2026-12-31', 'status' => 'published']);
        $a = Applicant::create(['full_name' => 'Pelamar Satu', 'email' => 'private.candidate@example.test', 'phone' => '08123456789']);
        Application::create(['applicant_id' => $a->id, 'vacancy_id' => $v->id, 'application_date' => '2026-11-02', 'notes' => 'CATATAN-HR-PRIVAT']);
        $this->get($this->url('/reports', 'recruitment', ['status' => 'screening']))->assertInertia(fn (Assert $p) => $p->where('report.total', 1)->where('report.rows.0.0', 'Pelamar Satu')->where('report.rows.0.3', 'Screening'))->assertDontSee('private.candidate@example.test')->assertDontSee('CATATAN-HR-PRIVAT')->assertDontSee('08123456789');
        $this->get($this->url('/reports', 'recruitment', ['status' => 'accepted']))->assertInertia(fn (Assert $p) => $p->where('report.total', 0));
        $this->get($this->url('/reports', 'recruitment', ['from' => '2026-11-03']))->assertInertia(fn (Assert $p) => $p->where('report.total', 0));
    }

    private function setupHr(): array
    {
        $this->withoutVite();
        $u = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($u);
        $d = Department::create(['code' => 'RPT', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        $e = Employee::create(['employee_number' => 'RPT-01', 'full_name' => '=HYPERLINK("https://example.test")', 'work_email' => 'private@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2026-01-01']);

        return [$u, $e];
    }

    private function filters(string $module = 'discipline', array $extra = []): array
    {
        return [...['module' => $module, 'from' => '2026-11-01', 'to' => '2026-11-30'], ...$extra];
    }

    private function url(string $path, string $module = 'discipline', array $extra = []): string
    {
        return $path.'?'.http_build_query($this->filters($module, $extra));
    }

    private function record(User $u, Employee $e, array $extra = []): DisciplinaryCase
    {
        return DisciplinaryCase::create([...['employee_id' => $e->id, 'created_by' => $u->id, 'reference_number' => 'RP-1', 'kind' => 'violation', 'incident_on' => '2026-11-02', 'subject' => 'Pemeriksaan prosedur', 'description' => 'RAHASIA-KONSELING'], ...$extra]);
    }

    public function test_preview_filters_and_exports_do_not_expose_private_case_notes(): void
    {
        [$u,$e] = $this->setupHr();
        $this->record($u, $e);
        $this->record($u, $e, ['reference_number' => 'RP-2', 'incident_on' => '2026-10-31']);
        $c = $this->record($u, $e, ['reference_number' => 'RP-3']);
        $c->status = 'resolved';
        $c->save();
        $this->get($this->url('/reports', 'discipline', ['status' => 'open']))->assertInertia(fn (Assert $p) => $p->where('report.total', 1)->has('report.rows', 1))->assertDontSee('RAHASIA-KONSELING')->assertDontSee('private@example.test');
        $this->get($this->url('/reports', 'discipline', ['status' => 'bogus']))->assertSessionHasErrors('status');
        $this->get($this->url('/reports', 'discipline', ['from' => '2026-12-01', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
        $this->get($this->url('/reports', 'discipline', ['from' => '2020-01-01']))->assertSessionHasErrors('to');
    }

    public function test_real_xlsx_stores_user_text_as_literal_strings_not_formulas(): void
    {
        [$u,$e] = $this->setupHr();
        $this->record($u, $e);
        $r = $this->get($this->url('/reports/export', 'discipline', ['format' => 'xlsx']));
        $r->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->assertHeader('X-Content-Type-Options', 'nosniff');
        $path = $r->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->getFromName('[Content_Types].xml'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('inlineStr', $sheet);
        $this->assertStringContainsString('=HYPERLINK', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertStringNotContainsString('RAHASIA-KONSELING', $sheet);
        $this->assertNotFalse(simplexml_load_string($sheet));
        $zip->close();
        unlink($path);
    }

    public function test_pdf_and_report_access_are_private(): void
    {
        [$u,$e] = $this->setupHr();
        $this->record($u, $e);
        $this->get($this->url('/reports/export', 'discipline', ['format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff')->assertSee('%PDF-', false);
        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        $this->get('/reports')->assertForbidden();
        foreach (['pdf', 'xlsx'] as $format) {
            $this->get($this->url('/reports/export', 'discipline', ['format' => $format]))->assertForbidden();
        }
        $this->post('/logout');
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_training_uses_wib_dates_and_preserves_missing_scores_and_zero(): void
    {
        [$u,$e] = $this->setupHr();
        $t = Training::create(['title' => 'Komunikasi', 'trainer' => 'Tim HR', 'location' => 'Kantor', 'starts_at' => '2026-10-31 18:00:00', 'ends_at' => '2026-10-31 20:00:00', 'created_by' => $u->id]);
        TrainingParticipant::create(['training_id' => $t->id, 'employee_id' => $e->id, 'attendance' => 'attended', 'pre_test' => 0, 'post_test' => null]);
        $r = $this->get($this->url('/reports', 'training', ['from' => '2026-11-01', 'to' => '2026-11-01']));
        $r->assertInertia(fn (Assert $p) => $p->where('report.total', 1)->where('report.rows.0.5', '0')->where('report.rows.0.6', '')->where('report.rows.0.7', ''));
        $this->get($this->url('/reports', 'training', ['from' => '2026-10-31', 'to' => '2026-10-31']))->assertInertia(fn (Assert $p) => $p->where('report.total', 0));
    }

    public function test_contract_and_recruitment_reports_use_the_selected_period(): void
    {
        [$u,$e] = $this->setupHr();
        EmployeeContract::create(['employee_id' => $e->id, 'contract_number' => 'CT-1', 'type' => 'pkwtt', 'starts_on' => '2026-11-01', 'ends_on' => null, 'created_by' => $u->id]);
        $this->get($this->url('/reports', 'contracts'))->assertInertia(fn (Assert $p) => $p->where('report.total', 1)->where('report.rows.0.5', ''));
        $this->get($this->url('/reports', 'recruitment'))->assertInertia(fn (Assert $p) => $p->where('report.total', 0));
        $this->get($this->url('/reports', 'unknown'))->assertSessionHasErrors('module');
    }

    public function test_large_exports_require_narrower_filters_instead_of_silent_truncation(): void
    {
        [$u,$e] = $this->setupHr();
        $rows = [];
        for ($i = 0; $i < 1001; $i++) {
            $rows[] = ['employee_id' => $e->id, 'created_by' => $u->id, 'reference_number' => 'LIMIT-'.$i, 'kind' => 'violation', 'incident_on' => '2026-11-02', 'subject' => 'Catatan', 'description' => 'Privat', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('disciplinary_cases')->insert($chunk);
        }
        $this->get($this->url('/reports'))->assertInertia(fn (Assert $p) => $p->where('report.total', 1001)->has('report.rows', 10));
        foreach (['xlsx', 'pdf'] as $format) {
            $this->get($this->url('/reports/export', 'discipline', ['format' => $format]))->assertSessionHasErrors('limit')->assertRedirect($this->url('/reports'));
        }
    }
}
