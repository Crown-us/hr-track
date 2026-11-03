<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use App\Services\AttendanceCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class AttendanceRecapTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private Employee $employee;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 3)->setTime(18, 0));
        $this->department = Department::create(['code' => 'RECAP', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $this->department->id, 'name' => 'Staf']);
        $this->employee = Employee::create(['employee_number' => 'RECAP-1', 'full_name' => '=SUM(A1)', 'work_email' => 'private-recap@example.test', 'department_id' => $this->department->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2025-01-01']);
        $this->hr = User::factory()->create(['role' => 'hr_staff']);
        $this->as($this->hr);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function as(User $u): static
    {
        return $this->actingAs($u)->withSession(['account_auth_version' => $u->auth_version]);
    }

    private function day(string $date, array $extra = []): AttendanceDay
    {
        return AttendanceDay::create([...['employee_id' => $this->employee->id, 'date' => $date, 'scheduled_start' => $date.' 01:00:00', 'scheduled_end' => $date.' 10:00:00', 'tolerance_minutes' => 10], ...$extra]);
    }

    private function leave(string $date, string $type): void
    {
        LeaveRequest::create(['employee_id' => $this->employee->id, 'submitted_by' => $this->hr->id, 'type' => $type, 'status' => 'approved', 'starts_on' => $date, 'ends_on' => $date, 'year' => 2026, 'days' => 1, 'work_dates' => [$date], 'reason' => 'Private leave narrative', 'document_path' => 'private-proof.pdf', 'version' => 1]);
    }

    public function test_access_private_headers_month_validation_and_zero_state(): void
    {
        foreach (['viewer', 'employee', 'supervisor'] as $role) {
            $this->as(User::factory()->create(['role' => $role]))->get('/attendance/recap')->assertForbidden();
            $this->get('/attendance/recap/export?format=xlsx')->assertForbidden();
        }
        $this->as($this->hr);
        foreach (['2026-13', '2027-01', '1999-12', 'wrong'] as $month) {
            $this->get('/attendance/recap?month='.$month)->assertSessionHasErrors('month');
        }
        $this->get('/attendance/recap?month=2026-11')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn (Assert $p) => $p->component('attendance-recap')->where('report.total', 1)->where('report.summary.present', 0)->where('employees.data.0.counts.unknown', 2)->where('employees.data.0.counts.future', 27));
    }

    public function test_month_counts_match_calendar_overnight_and_leave_conflicts(): void
    {
        $this->day('2026-10-02', ['clock_in' => '2026-10-02 01:10:00', 'clock_out' => '2026-10-02 10:00:00']);
        $this->day('2026-10-05', ['clock_in' => '2026-10-05 01:11:00', 'clock_out' => '2026-10-05 10:00:00']);
        $this->day('2026-10-06');
        $this->day('2026-10-07', ['clock_in' => '2026-10-07 01:00:00']);
        $this->leave('2026-10-08', 'annual');
        $this->leave('2026-10-09', 'sick');
        $this->leave('2026-10-12', 'permission');
        $this->leave('2026-10-13', 'annual');
        $this->day('2026-10-13', ['clock_in' => '2026-10-13 01:00:00', 'clock_out' => '2026-10-13 10:00:00']);
        $this->day('2026-10-31', ['scheduled_start' => '2026-10-31 15:00:00', 'scheduled_end' => '2026-10-31 23:00:00', 'clock_in' => '2026-10-31 15:00:00', 'clock_out' => '2026-10-31 23:00:00']);
        $this->day('2026-11-01', ['clock_in' => '2026-11-01 01:00:00', 'clock_out' => '2026-11-01 10:00:00']);
        $this->get('/attendance/recap?month=2026-10')->assertInertia(fn (Assert $p) => $p->where('report.summary.present', 3)->where('report.summary.on_time', 2)->where('report.summary.late', 1)->where('report.summary.absent', 1)->where('report.summary.review', 2)->where('report.summary.annual', 1)->where('report.summary.permission', 1)->where('report.summary.sick', 1)->where('report.summary.future', 0));
        $calendar = app(AttendanceCalendar::class)->build($this->employee, 2026, '2026-10-31', false);
        $october = array_filter($calendar['days'], fn ($d) => str_starts_with($d['date'], '2026-10'));
        $this->get('/attendance/recap?month=2026-10')->assertInertia(fn (Assert $p) => $p->where('report.summary.unknown', count(array_filter($october, fn ($d) => $d['status'] === 'unknown'))));
    }

    public function test_filters_join_date_archived_history_and_no_private_fields(): void
    {
        $e = $this->employee->replicate();
        $e->forceFill(['employee_number' => 'RECAP-2', 'full_name' => 'Baru Bergabung', 'work_email' => 'new@example.test', 'join_date' => '2026-11-03'])->save();
        $this->employee->delete();
        $this->get('/attendance/recap?month=2026-11&q=RECAP-1')->assertInertia(fn (Assert $p) => $p->where('report.total', 1)->where('employees.data.0.archived', true)->missing('employees.data.0.work_email')->missing('employees.data.0.reason'));
        $this->get('/attendance/recap?month=2026-11&q=RECAP-2')->assertInertia(fn (Assert $p) => $p->where('report.summary.unknown', 1)->where('report.summary.future', 27));
        $this->get('/attendance/recap?month=2026-10&q=RECAP-2')->assertInertia(fn (Assert $p) => $p->where('report.total', 0)->has('employees.data', 0));
        $other = Department::create(['code' => 'OTHER', 'name' => 'Lain']);
        $this->get('/attendance/recap?month=2026-11&department_id='.$other->id)->assertInertia(fn (Assert $p) => $p->where('report.total', 0));
        $this->get('/attendance/recap?department_id=99999')->assertSessionHasErrors('department_id');
    }

    public function test_current_month_wib_future_and_running_are_not_absent(): void
    {
        $this->day('2026-11-03', ['scheduled_start' => '2026-11-03 15:00:00', 'scheduled_end' => '2026-11-03 23:00:00']);
        $this->day('2026-11-04');
        $this->get('/attendance/recap?month=2026-11')->assertInertia(fn (Assert $p) => $p->where('report.summary.absent', 0)->where('report.summary.running', 1)->where('report.summary.future', 27)->where('report.as_of', '2026-11-03'));
    }

    public function test_exports_share_filters_are_real_files_and_keep_formula_text_safe(): void
    {
        $this->day('2026-10-02', ['clock_in' => '2026-10-02 01:00:00', 'clock_out' => '2026-10-02 10:00:00']);
        $response = $this->get('/attendance/recap/export?month=2026-10&q=RECAP-1&format=xlsx')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $path = $response->baseResponse->getFile()->getPathname();
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('=SUM(A1)', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertStringNotContainsString('private-recap', $xml);
            $this->assertStringContainsString('RECAP-1', $xml);
            $document = new \DOMDocument;
            $document->loadXML($xml);
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame('n', $xpath->query('//m:c[@r="C7"]')->item(0)->getAttribute('t'));
            $zip->close();
        } finally {
            @unlink($path);
        }
        $pdf = $this->get('/attendance/recap/export?month=2026-10&q=RECAP-1&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get('/attendance/recap/export?format=exe')->assertSessionHasErrors('format');
    }

    public function test_limits_reject_without_truncating_and_batch_queries_stay_bounded(): void
    {
        $row = $this->employee->getAttributes();
        unset($row['id']);
        $rows = [];
        for ($i = 2; $i <= 201; $i++) {
            $rows[] = [...$row, 'employee_number' => 'RECAP-'.$i, 'work_email' => 'recap'.$i.'@example.test'];
        }
        DB::table('employees')->insert($rows);
        $this->get('/attendance/recap/export?month=2026-10&format=pdf')->assertSessionHasErrors('limit');
        DB::enableQueryLog();
        $this->get('/attendance/recap?month=2026-10')->assertOk()->assertInertia(fn (Assert $p) => $p->where('report.total', 201)->has('employees.data', 15));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(25, $queries);
        $rows = [];
        for ($i = 202; $i <= 1001; $i++) {
            $rows[] = [...$row, 'employee_number' => 'RECAP-'.$i, 'work_email' => 'recap'.$i.'@example.test'];
        }
        DB::table('employees')->insert($rows);
        $this->get('/attendance/recap?month=2026-10')->assertOk()->assertInertia(fn (Assert $p) => $p->where('report.total', 1001)->where('report.limited', true)->where('report.summary', null));
        $this->get('/attendance/recap/export?month=2026-10&format=xlsx')->assertSessionHasErrors('limit');
    }

    public function test_zero_search_remains_a_real_filter(): void
    {
        $e = $this->employee->replicate();
        $e->forceFill(['employee_number' => 'RECAP-0', 'full_name' => 'Pegawai Nol', 'work_email' => 'zero@example.test'])->save();
        $this->get('/attendance/recap?month=2026-10&q=0')->assertInertia(fn (Assert $p) => $p->where('report.total', 1)->where('employees.data.0.employee_number', 'RECAP-0')->where('report.filter_label', 'Semua departemen · Pencarian: 0'));
    }

    public function test_pending_corrections_and_leave_do_not_change_recap_and_wib_cutoff_is_used(): void
    {
        $day = $this->day('2026-10-01', ['clock_in' => '2026-10-01 01:00:00']);
        DB::table('attendance_corrections')->insert(['attendance_day_id' => $day->id, 'employee_id' => $this->employee->id, 'submitted_by' => $this->hr->id, 'day_version' => 1, 'version' => 1, 'status' => 'pending_hr', 'original' => '{}', 'clock_in' => '2026-10-01 01:00:00', 'clock_out' => '2026-10-01 10:00:00', 'reason' => 'Private pending correction', 'created_at' => now(), 'updated_at' => now()]);
        $this->leave('2026-10-05', 'annual');
        LeaveRequest::query()->update(['status' => 'pending_hr']);
        $this->day('2026-10-02', ['clock_in' => '2026-10-02 01:00:00', 'clock_out' => '2026-10-02 10:00:00', 'voided_at' => now()]);
        DB::table('leave_holidays')->insert(['date' => '2026-10-02', 'name' => 'Libur kantor', 'created_at' => now()]);
        $this->get('/attendance/recap?month=2026-10')->assertInertia(fn (Assert $p) => $p->where('report.summary.present', 0)->where('report.summary.review', 1)->where('report.summary.annual', 0));
        $this->travelTo(now('Asia/Jakarta')->setTime(0, 15));
        $this->get('/attendance/recap?month=2026-11')->assertInertia(fn (Assert $p) => $p->where('report.as_of', '2026-11-03')->where('report.generated_at', '2026-11-02T17:15:00+00:00')->where('report.summary.future', 27));
    }
}
