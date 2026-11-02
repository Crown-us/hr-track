<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use App\Services\AttendanceRecords;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $worker;

    private User $boss;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 3)->setTime(18, 0));
        $d = Department::create(['code' => 'ATT', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        $make = fn ($i) => Employee::create(['employee_number' => 'ATT-'.$i, 'full_name' => 'Pegawai '.$i, 'work_email' => 'att'.$i.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2024-01-01']);
        $this->employee = $make(1);
        $manager = $make(2);
        $this->employee->manager_employee_id = $manager->id;
        $this->employee->save();
        $this->hr = User::factory()->create(['role' => 'hr_staff']);
        $this->worker = User::factory()->create(['role' => 'employee']);
        $this->worker->employee_id = $this->employee->id;
        $this->worker->save();
        $this->boss = User::factory()->create(['role' => 'supervisor']);
        $this->boss->employee_id = $manager->id;
        $this->boss->save();
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

    private function url(): string
    {
        return '/attendance/employees/'.$this->employee->id;
    }

    private function data(array $extra = []): array
    {
        return [...['date' => '2026-11-02', 'scheduled_start' => '2026-11-02T08:00', 'scheduled_end' => '2026-11-02T17:00', 'clock_in' => '2026-11-02T08:00', 'clock_out' => '2026-11-02T17:00', 'tolerance_minutes' => 10, 'reason' => 'Pencatatan berdasarkan rekap kehadiran', 'version' => 0, 'action' => 'save'], ...$extra];
    }

    private function save(array $extra = [])
    {
        return $this->as($this->hr)->post($this->url().'/days', $this->data($extra));
    }

    public function test_access_scopes_private_headers_and_empty_calendar(): void
    {
        $this->get('/attendance')->assertRedirect('/login');
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->as($viewer)->get('/attendance')->assertForbidden();
        $this->as($viewer)->get($this->url())->assertForbidden();
        $this->as($this->worker)->get('/attendance?year=2024')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertInertia(fn (Assert $p) => $p->component('attendance')->has('calendar.days', 366)->where('calendar.summary.present', 0)->where('calendar.days.0.status', 'unknown')->where('calendar.days.59.date', '2024-02-29'));
        $this->as($this->boss)->get($this->url())->assertOk();
        $this->as($this->worker)->post($this->url().'/days', $this->data())->assertForbidden();
        $other = User::factory()->create(['role' => 'supervisor']);
        $this->as($other)->get($this->url())->assertNotFound();
        $this->as($this->hr)->get('/attendance')->assertOk()->assertInertia(fn (Assert $p) => $p->where('employees.total', 2));
    }

    public function test_statuses_use_explicit_schedule_completed_punches_and_wib(): void
    {
        $this->save()->assertRedirect($this->url().'?year=2026&date=2026-11-02')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $this->employee->id, 'date' => '2026-11-02', 'scheduled_start' => '2026-11-02 01:00:00', 'clock_in' => '2026-11-02 01:00:00']);
        $this->as($this->worker)->get('/attendance?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'present')->where('calendar.summary.present', 1)->where('calendar.summary.on_time', 1)->where('calendar.summary.late', 0));
        $this->save(['version' => 1, 'clock_in' => '2026-11-02T08:11'])->assertSessionHasNoErrors();
        $this->get($this->url().'?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'late')->where('calendar.selected.late_minutes', 11)->where('calendar.summary.present', 1)->where('calendar.summary.on_time', 0)->where('calendar.summary.late', 1));
        $this->save(['version' => 2, 'clock_in' => null, 'clock_out' => null])->assertSessionHasNoErrors();
        $this->get($this->url().'?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'absent')->where('calendar.summary.absent', 1));
        $this->save(['version' => 3, 'clock_out' => null])->assertSessionHasNoErrors();
        $this->get($this->url().'?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'review')->where('calendar.summary.review', 1));
        $this->get($this->url().'?date=2026-11-03')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'unknown'));
    }

    public function test_overnight_future_and_tolerance_boundary(): void
    {
        $this->save(['scheduled_start' => '2026-11-02T22:00', 'scheduled_end' => '2026-11-03T06:00', 'clock_in' => '2026-11-02T22:10', 'clock_out' => '2026-11-03T06:00'])->assertSessionHasNoErrors();
        $this->as($this->worker)->get('/attendance?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'present')->where('calendar.selected.late_minutes', 10));
        $this->save(['date' => '2026-11-03', 'scheduled_start' => '2026-11-03T22:00', 'scheduled_end' => '2026-11-04T06:00', 'clock_in' => null, 'clock_out' => null])->assertSessionHasNoErrors();
        $this->get($this->url().'?date=2026-11-03')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'scheduled'));
        $this->travelTo(now('Asia/Jakarta')->setTime(23, 0));
        $this->get($this->url().'?date=2026-11-03')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'waiting'));
        $this->get($this->url().'?date=2026-11-04')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'future'));
    }

    public function test_invalid_dates_punches_versions_and_void_keep_history(): void
    {
        foreach ([['scheduled_end' => '2026-11-02T07:00'], ['scheduled_end' => '2026-11-04T17:00'], ['clock_in' => null], ['clock_out' => '2026-11-02T07:00'], ['clock_in' => '2026-11-04T08:00', 'clock_out' => null], ['date' => '2023-01-01']] as $extra) {
            $this->save($extra)->assertSessionHasErrors();
        }
        $this->save()->assertSessionHasNoErrors();
        $this->save()->assertSessionHasErrors('version');
        $this->save(['version' => 1, 'action' => 'void'])->assertSessionHasNoErrors();
        $this->get($this->url().'?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'unknown')->where('calendar.selected.record.version', 2));
        $this->assertDatabaseCount('attendance_days', 1);
        $this->assertDatabaseCount('attendance_events', 2);
        $this->save(['version' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $this->employee->id, 'version' => 3, 'voided_at' => null]);
    }

    public function test_approved_leave_snapshot_conflicts_privacy_and_holidays(): void
    {
        $l = LeaveRequest::create(['employee_id' => $this->employee->id, 'submitted_by' => $this->worker->id, 'type' => 'sick', 'status' => 'approved', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-03', 'year' => 2026, 'days' => 1, 'work_dates' => ['2026-11-02'], 'reason' => 'Private sick reason', 'document_path' => 'private.pdf', 'version' => 1]);
        DB::table('leave_holidays')->insert(['date' => '2026-11-03', 'name' => 'Libur kantor', 'created_at' => now()]);
        $this->as($this->worker)->get('/attendance?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'sick')->where('calendar.selected.leave.label', 'Sakit')->missing('calendar.selected.leave.reason')->missing('calendar.selected.leave.document_path'));
        $this->get('/attendance?date=2026-11-03')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'off'));
        $this->save()->assertSessionHasNoErrors();
        $this->as($this->worker)->get('/attendance?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'review')->where('calendar.summary.review', 1)->where('calendar.summary.present', 0)->has('calendar.events', 1));
        $this->as($this->boss)->get($this->url().'?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->has('calendar.events', 0));
        $l->status = 'cancel_pending';
        $l->save();
        $this->as($this->worker)->get('/attendance?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'review'));
        $l->status = 'cancelled';
        $l->save();
        $this->get('/attendance?date=2026-11-02')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.status', 'present')->where('calendar.summary.present', 1));
    }

    public function test_year_alignment_future_unknown_and_scope_after_lifecycle_changes(): void
    {
        $this->as($this->worker)->get('/attendance?year=2024&date=2024-12-31')->assertInertia(function (Assert $p) {
            return $p->where('calendar.days.0.week', 0)->where('calendar.days.0.weekday', 0)->where('calendar.weeks', 53)->where('calendar.selected.date', '2024-12-31');
        });
        $this->get('/attendance?year=2028&date=2028-02-29')->assertInertia(fn (Assert $p) => $p->has('calendar.days', 366)->where('calendar.selected.status', 'future')->where('calendar.summary.absent', 0));
        $this->get('/attendance?year=2024&date=2026-11-02')->assertSessionHasErrors('date');
        $this->employee->manager_employee_id = null;
        $this->employee->save();
        $this->as($this->boss)->get($this->url())->assertNotFound();
        $this->employee->delete();
        $this->as($this->worker)->get($this->url())->assertNotFound();
        $this->as($this->hr)->get($this->url())->assertOk();
        $this->post($this->url().'/days', $this->data())->assertSessionHasErrors('employee');
    }

    public function test_overlapping_punch_windows_are_rejected_and_audit_failures_roll_back(): void
    {
        $this->save()->assertSessionHasNoErrors();
        $this->save(['date' => '2026-11-03', 'scheduled_start' => '2026-11-03T00:00', 'scheduled_end' => '2026-11-03T08:00', 'clock_in' => '2026-11-02T16:00', 'clock_out' => '2026-11-03T08:00'])->assertSessionHasErrors('clock_in');
        $id = DB::table('attendance_days')->value('id');
        $s = app(AttendanceRecords::class);
        DB::listen(function (QueryExecuted $q) {
            if (str_contains($q->sql, 'insert into `attendance_events`')) {
                throw new \RuntimeException('audit failed');
            }
        });
        try {
            $s->save($this->hr, $this->employee, $this->data(['version' => 1, 'clock_in' => '2026-11-02T08:30']));
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $ex) {
            $this->assertSame('audit failed', $ex->getMessage());
        }
        $this->assertDatabaseHas('attendance_days', ['id' => $id, 'version' => 1, 'clock_in' => '2026-11-02 01:00:00']);
        $this->assertDatabaseCount('attendance_events', 1);
    }
}
