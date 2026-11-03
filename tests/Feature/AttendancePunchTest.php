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

class AttendancePunchTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private User $worker;

    private AttendanceDay $day;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 3)->setTime(7, 30, 12));
        $d = Department::create(['code' => 'PUNCH', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        $this->employee = Employee::create(['employee_number' => 'PUNCH-1', 'full_name' => 'Raka', 'work_email' => 'punch@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2025-01-01']);
        $this->worker = User::factory()->create(['role' => 'employee']);
        $this->worker->forceFill(['employee_id' => $this->employee->id])->save();
        $this->day = AttendanceDay::create(['employee_id' => $this->employee->id, 'date' => '2026-11-03', 'scheduled_start' => '2026-11-03 01:00:00', 'scheduled_end' => '2026-11-03 10:00:00', 'tolerance_minutes' => 10]);
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

    private function punch(string $action = 'in', array $extra = [])
    {
        return $this->post('/attendance/punch', [...['day_id' => $this->day->id, 'version' => $this->day->fresh()->version, 'action' => $action, 'confirm' => true], ...$extra]);
    }

    public function test_server_time_audit_sources_and_stale_replay(): void
    {
        $this->get('/attendance')->assertInertia(fn (Assert $p) => $p->has('punch.days', 1)->where('punch.days.0.can_in', true));
        $this->punch(extra: ['clock_in' => '2000-01-01', 'employee_id' => 999])->assertRedirect('/attendance?year=2026&date=2026-11-03')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['id' => $this->day->id, 'clock_in' => '2026-11-03 00:30:12', 'clock_in_source' => 'web', 'version' => 2]);
        $this->punch(extra: ['version' => 1])->assertSessionHasErrors('punch');
        $this->punch()->assertSessionHasErrors('punch');
        $this->travelTo(now('Asia/Jakarta')->setTime(17, 0, 24));
        $this->punch('out')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['id' => $this->day->id, 'clock_out' => '2026-11-03 10:00:24', 'clock_out_source' => 'web', 'version' => 3]);
        $this->assertDatabaseCount('attendance_events', 2);
        $event = DB::table('attendance_events')->latest('id')->first();
        $this->assertSame('clocked_out', $event->action);
        $this->assertSame($this->worker->id, $event->actor_id);
        $this->assertSame('2026-11-03T10:00:24+00:00', json_decode($event->changes, true)['recorded_at']);
        $this->get('/attendance')->assertInertia(fn (Assert $p) => $p->where('calendar.summary.present', 1)->where('calendar.selected.record.source', 'Web pegawai'));
    }

    public function test_roles_ownership_and_confirmation_are_enforced(): void
    {
        foreach (['admin', 'hr_staff', 'viewer'] as $role) {
            $this->as(User::factory()->create(['role' => $role]));
            $this->punch()->assertForbidden();
        }
        $other = User::factory()->create(['role' => 'supervisor']);
        $otherEmployee = $this->employee->replicate();
        $otherEmployee->forceFill(['employee_number' => 'PUNCH-2', 'work_email' => 'punch2@example.test'])->save();
        $other->forceFill(['employee_id' => $otherEmployee->id])->save();
        $this->employee->forceFill(['manager_employee_id' => $otherEmployee->id])->save();
        $this->as($other)->get('/attendance/employees/'.$this->employee->id)->assertInertia(fn (Assert $p) => $p->where('punch', null));
        $this->punch()->assertNotFound();
        $this->as($this->worker);
        $this->punch(extra: ['confirm' => false])->assertSessionHasErrors('confirm');
        $this->punch('out')->assertSessionHasErrors('punch');
        $this->assertDatabaseCount('attendance_events', 0);
        $this->worker->role = 'supervisor';
        $this->worker->save();
        $this->as($this->worker);
        $this->punch()->assertSessionHasNoErrors();
    }

    public function test_window_boundaries_void_and_leave_are_rechecked(): void
    {
        $this->travelTo(now('Asia/Jakarta')->setTime(6, 59, 59));
        $this->punch()->assertSessionHasErrors('punch');
        $this->travelTo(now('Asia/Jakarta')->setTime(7, 0));
        $l = LeaveRequest::create(['employee_id' => $this->employee->id, 'submitted_by' => $this->worker->id, 'type' => 'permission', 'status' => 'cancel_pending', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-03', 'year' => 2026, 'days' => 1, 'work_dates' => ['2026-11-03'], 'reason' => 'Keperluan keluarga', 'version' => 1]);
        $this->punch()->assertSessionHasErrors('punch');
        $l->update(['status' => 'cancelled']);
        $this->day->update(['voided_at' => now()]);
        $this->punch()->assertSessionHasErrors('punch');
        $this->day->update(['voided_at' => null]);
        $this->punch()->assertSessionHasNoErrors();
        $l->update(['status' => 'approved']);
        $this->travelTo(now('Asia/Jakarta')->setTime(21, 0));
        $this->punch('out')->assertSessionHasNoErrors();
    }

    public function test_overnight_and_expired_checkout_and_checkin_at_end(): void
    {
        $this->day->update(['date' => '2026-11-02', 'scheduled_start' => '2026-11-02 15:00:00', 'scheduled_end' => '2026-11-02 23:00:00']);
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 2)->setTime(22, 0));
        $this->punch()->assertSessionHasNoErrors();
        $this->travelTo(now('Asia/Jakarta')->addDay()->setTime(6, 0));
        $this->get('/attendance')->assertInertia(fn (Assert $p) => $p->where('punch.days.0.date', '2026-11-02')->where('punch.days.0.can_out', true));
        $this->punch('out')->assertRedirect('/attendance?year=2026&date=2026-11-02')->assertSessionHasNoErrors();
        $this->day->update(['clock_out' => null]);
        $this->travelTo(now('Asia/Jakarta')->setTime(10, 0, 1));
        $this->punch('out')->assertSessionHasErrors('punch');
        $this->day->update(['clock_in' => null]);
        $this->travelTo(now('Asia/Jakarta')->setTime(6, 0));
        $this->punch()->assertSessionHasErrors('punch');
    }

    public function test_overlap_open_punch_and_inactive_employee_block_writes(): void
    {
        $other = AttendanceDay::create(['employee_id' => $this->employee->id, 'date' => '2026-11-02', 'scheduled_start' => '2026-11-02 15:00:00', 'scheduled_end' => '2026-11-02 23:00:00', 'clock_in' => '2026-11-02 15:00:00']);
        $this->punch()->assertSessionHasErrors('punch');
        $other->update(['clock_out' => '2026-11-03 01:00:00']);
        $this->punch()->assertSessionHasErrors('punch');
        $other->update(['clock_out' => '2026-11-03 00:00:00']);
        $this->employee->update(['employment_status' => 'inactive']);
        $this->punch()->assertForbidden();
        $this->employee->update(['employment_status' => 'permanent']);
        $this->employee->delete();
        $this->punch()->assertForbidden();
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_audit_failure_rolls_back_punch(): void
    {
        DB::listen(function (QueryExecuted $q) {
            if (str_starts_with($q->sql, 'insert into `attendance_events`')) {
                throw new \RuntimeException('audit unavailable');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->punch();
            $this->fail('Audit failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit unavailable', $e->getMessage());
        }
        $this->assertNull($this->day->fresh()->clock_in);
        $this->assertSame(1, $this->day->fresh()->version);
        $this->assertDatabaseCount('attendance_events', 0);
    }

    public function test_hr_preserves_web_seconds_and_tracks_mixed_sources(): void
    {
        $this->punch()->assertSessionHasNoErrors();
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $data = ['date' => '2026-11-03', 'version' => 2, 'action' => 'save', 'scheduled_start' => '2026-11-03T08:00', 'scheduled_end' => '2026-11-03T17:00', 'clock_in' => '2026-11-03T07:30', 'clock_out' => '2026-11-03T07:30', 'tolerance_minutes' => 10, 'reason' => 'Pemeriksaan rekap kehadiran'];
        $url = '/attendance/employees/'.$this->employee->id.'/days';
        $this->as($hr)->post($url, $data)->assertSessionHasErrors('clock_in');
        $this->travelTo(now('Asia/Jakarta')->setTime(17, 0));
        $this->post($url, [...$data, 'clock_out' => '2026-11-03T17:00'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['id' => $this->day->id, 'clock_in' => '2026-11-03 00:30:12', 'clock_in_source' => 'web', 'clock_out_source' => 'hr']);
        $this->as($this->worker)->get('/attendance')->assertInertia(fn (Assert $p) => $p->where('calendar.selected.record.source', 'Web pegawai / koreksi HR'));
    }

    public function test_current_account_state_and_checkout_overlap_are_rechecked(): void
    {
        $this->worker->forceFill(['is_active' => false])->save();
        $this->punch()->assertRedirect('/login');
        $this->assertNull($this->day->fresh()->clock_in);
        $this->worker->forceFill(['is_active' => true])->save();
        $this->as($this->worker);
        $this->punch()->assertSessionHasNoErrors();
        AttendanceDay::create(['employee_id' => $this->employee->id, 'date' => '2026-11-04', 'scheduled_start' => '2026-11-04 01:00:00', 'scheduled_end' => '2026-11-04 10:00:00', 'clock_in' => '2026-11-03 02:00:00', 'clock_out' => '2026-11-03 03:00:00']);
        $this->travelTo(now('Asia/Jakarta')->setTime(17, 0));
        $this->punch('out')->assertSessionHasErrors('punch');
        $this->assertNull($this->day->fresh()->clock_out);
    }

    public function test_next_day_shift_is_available_before_midnight(): void
    {
        $this->day->update(['date' => '2026-11-04', 'scheduled_start' => '2026-11-03 17:30:00', 'scheduled_end' => '2026-11-04 01:30:00']);
        $this->travelTo(now('Asia/Jakarta')->setTime(23, 45));
        $this->get('/attendance')->assertInertia(fn (Assert $p) => $p->has('punch.days', 1)->where('punch.days.0.date', '2026-11-04')->where('punch.days.0.can_in', true));
        $this->punch()->assertSessionHasNoErrors();
    }
}
