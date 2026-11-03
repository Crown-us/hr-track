<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $worker;

    private User $boss;

    private User $hr;

    private Employee $employee;

    private AttendanceDay $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 3)->setTime(18, 0));
        $d = Department::create(['code' => 'COR', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        $make = fn ($i) => Employee::create(['employee_number' => 'COR-'.$i, 'full_name' => 'Pegawai '.$i, 'work_email' => 'cor'.$i.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2025-01-01']);
        $this->employee = $make(1);
        $manager = $make(2);
        $this->employee->forceFill(['manager_employee_id' => $manager->id])->save();
        $this->worker = User::factory()->create(['role' => 'employee']);
        $this->worker->forceFill(['employee_id' => $this->employee->id])->save();
        $this->boss = User::factory()->create(['role' => 'supervisor']);
        $this->boss->forceFill(['employee_id' => $manager->id])->save();
        $this->hr = User::factory()->create(['role' => 'hr_staff']);
        $this->day = AttendanceDay::create(['employee_id' => $this->employee->id, 'date' => '2026-11-02', 'scheduled_start' => '2026-11-02 01:00:00', 'scheduled_end' => '2026-11-02 10:00:00', 'clock_in' => '2026-11-02 01:20:37', 'clock_in_source' => 'web']);
        $this->as($this->worker);
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

    private function submit(array $extra = [])
    {
        return $this->post('/attendance/corrections', [...['day_id' => $this->day->id, 'day_version' => 1, 'clock_in' => '2026-11-02T08:20', 'clock_out' => '2026-11-02T17:00', 'reason' => 'Lupa mencatat pulang setelah bertugas', 'confirm' => true], ...$extra]);
    }

    private function id(): int
    {
        return DB::table('attendance_corrections')->latest('id')->value('id');
    }

    private function decision(User $u, string $action, int $version = 1)
    {
        return $this->as($u)->post('/attendance/corrections/'.$this->id().'/decision', ['action' => $action, 'version' => $version, 'reason' => 'Sudah diperiksa dengan rekap tim', 'confirm' => true]);
    }

    public function test_workflow_preserves_original_until_hr_and_keeps_sources(): void
    {
        $this->get('/attendance/corrections/create?day_id='.$this->day->id)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->submit()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_corrections', ['status' => 'pending_supervisor', 'day_version' => 1]);
        $this->assertNull($this->day->fresh()->clock_out);
        $this->decision($this->hr, 'approve')->assertSessionHasErrors('correction');
        $this->decision($this->boss, 'approve')->assertSessionHasNoErrors();
        $this->assertNull($this->day->fresh()->clock_out);
        $this->decision($this->hr, 'approve', 2)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['id' => $this->day->id, 'clock_in' => '2026-11-02 01:20:37', 'clock_out' => '2026-11-02 10:00:00', 'clock_in_source' => 'web', 'clock_out_source' => 'hr', 'version' => 2]);
        $this->assertDatabaseHas('attendance_corrections', ['status' => 'approved', 'version' => 3]);
        $this->assertDatabaseCount('attendance_correction_events', 3);
        $this->assertDatabaseCount('attendance_events', 1);
        $this->get('/attendance/corrections/'.$this->id())->assertInertia(fn (Assert $p) => $p->where('events.2.after', 'approved'));
        $this->decision($this->hr, 'approve', 2)->assertSessionHasErrors('version');
    }

    public function test_scope_idor_own_decisions_and_duplicate_pending(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->as($viewer)->get('/attendance/corrections')->assertForbidden();
        $this->as($this->hr)->post('/attendance/corrections', [])->assertForbidden();
        $this->as($this->boss)->get('/attendance/corrections/create?day_id='.$this->day->id)->assertNotFound();
        $this->as($this->worker);
        $this->submit()->assertSessionHasNoErrors();
        $this->submit()->assertSessionHasErrors('correction');
        $this->decision($this->worker, 'approve')->assertSessionHasErrors('correction');
        $other = User::factory()->create(['role' => 'employee']);
        $this->as($other)->get('/attendance/corrections/'.$this->id())->assertNotFound();
        $this->worker->forceFill(['role' => 'hr_staff'])->save();
        $this->decision($this->worker, 'reject')->assertSessionHasErrors('correction');
    }

    public function test_stale_day_and_request_do_not_overwrite_hr_corrections(): void
    {
        $this->submit(['day_version' => 999])->assertSessionHasErrors('version');
        $this->submit()->assertSessionHasNoErrors();
        $this->decision($this->boss, 'approve')->assertSessionHasNoErrors();
        $this->day->update(['version' => 2, 'clock_out' => '2026-11-02 09:00:00']);
        $this->decision($this->hr, 'approve', 2)->assertSessionHasErrors('version');
        $this->assertDatabaseHas('attendance_days', ['clock_out' => '2026-11-02 09:00:00']);
        $this->decision($this->hr, 'reject', 2)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_corrections', ['status' => 'rejected']);
    }

    public function test_withdraw_orphan_and_inactive_employee(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $this->decision($this->worker, 'withdraw')->assertSessionHasNoErrors();
        $this->as($this->worker);
        $this->submit()->assertSessionHasNoErrors();
        $this->boss->forceFill(['is_active' => false])->save();
        $this->decision($this->hr, 'approve')->assertSessionHasNoErrors();
        $this->as($this->worker);
        $this->submit(['day_version' => 2, 'clock_in' => '2026-11-02T08:00'])->assertSessionHasNoErrors();
        $this->employee->update(['employment_status' => 'inactive']);
        $this->decision($this->hr, 'approve')->assertSessionHasErrors('correction');
        $this->decision($this->hr, 'reject')->assertSessionHasNoErrors();
    }

    public function test_invalid_time_window_leave_and_overlap(): void
    {
        foreach ([['clock_out' => '2026-11-02T08:00'], ['clock_out' => '2026-11-04T17:00'], ['clock_in' => '2026-11-01T07:00'], ['clock_out' => null], ['reason' => 'x'], ['confirm' => false]] as $extra) {
            $this->submit($extra)->assertSessionHasErrors();
        }
        $this->day->update(['date' => '2026-10-03', 'scheduled_start' => '2026-10-03 01:00:00', 'scheduled_end' => '2026-10-03 10:00:00']);
        $this->submit()->assertSessionHasErrors('correction');
        $this->day->update(['date' => '2026-11-02', 'scheduled_start' => '2026-11-02 01:00:00', 'scheduled_end' => '2026-11-02 10:00:00']);
        $l = LeaveRequest::create(['employee_id' => $this->employee->id, 'submitted_by' => $this->worker->id, 'type' => 'permission', 'status' => 'approved', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-02', 'year' => 2026, 'days' => 1, 'work_dates' => ['2026-11-02'], 'reason' => 'Keperluan keluarga', 'version' => 1]);
        $this->submit()->assertSessionHasErrors('correction');
        $l->update(['status' => 'cancelled']);
        AttendanceDay::create(['employee_id' => $this->employee->id, 'date' => '2026-11-01', 'scheduled_start' => '2026-11-01 15:00:00', 'scheduled_end' => '2026-11-01 23:00:00', 'clock_in' => '2026-11-02 02:00:00', 'clock_out' => '2026-11-02 03:00:00']);
        $this->submit()->assertSessionHasErrors('correction');
        $this->assertDatabaseCount('attendance_corrections', 0);
    }

    public function test_final_audit_failure_rolls_back_attendance_and_request(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $this->decision($this->boss, 'approve')->assertSessionHasNoErrors();
        DB::listen(function (QueryExecuted $q) {
            if (str_starts_with($q->sql, 'insert into `attendance_correction_events`')) {
                throw new \RuntimeException('correction audit unavailable');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->decision($this->hr, 'approve', 2);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('correction audit unavailable', $e->getMessage());
        }
        $this->assertNull($this->day->fresh()->clock_out);
        $this->assertDatabaseHas('attendance_corrections', ['status' => 'pending_hr', 'version' => 2]);
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_manager_changes_and_new_leave_are_rechecked_before_approval(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $this->employee->forceFill(['manager_employee_id' => null])->save();
        $this->decision($this->boss, 'approve')->assertNotFound();
        LeaveRequest::create(['employee_id' => $this->employee->id, 'submitted_by' => $this->worker->id, 'type' => 'sick', 'status' => 'approved', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-02', 'year' => 2026, 'days' => 1, 'work_dates' => ['2026-11-02'], 'reason' => 'Istirahat sesuai pemeriksaan', 'version' => 1]);
        $this->decision($this->hr, 'approve')->assertSessionHasErrors('correction');
        $this->assertNull($this->day->fresh()->clock_out);
        $this->decision($this->hr, 'reject')->assertSessionHasNoErrors();
    }

    public function test_overnight_proposal_and_no_change_are_handled(): void
    {
        $this->day->update(['scheduled_start' => '2026-11-02 15:00:00', 'scheduled_end' => '2026-11-02 23:00:00', 'clock_in' => '2026-11-02 15:20:37']);
        $this->submit(['clock_in' => '2026-11-02T22:20', 'clock_out' => '2026-11-03T06:00'])->assertSessionHasNoErrors();
        $this->decision($this->boss, 'approve')->assertSessionHasNoErrors();
        $this->decision($this->hr, 'approve', 2)->assertSessionHasNoErrors();
        $this->as($this->worker);
        $this->submit(['day_version' => 2, 'clock_in' => '2026-11-02T22:20', 'clock_out' => '2026-11-03T06:00'])->assertSessionHasErrors('correction');
        $this->assertDatabaseHas('attendance_days', ['clock_in' => '2026-11-02 15:20:37', 'clock_out' => '2026-11-02 23:00:00']);
    }

    public function test_original_submitter_cannot_approve_after_account_relink_and_promotion(): void
    {
        $this->submit()->assertSessionHasNoErrors();
        $this->decision($this->boss, 'approve')->assertSessionHasNoErrors();
        $this->worker->forceFill(['role' => 'hr_staff', 'employee_id' => null])->save();
        $this->decision($this->worker, 'approve', 2)->assertSessionHasErrors('correction');
        $this->assertNull($this->day->fresh()->clock_out);
    }
}
