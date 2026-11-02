<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Position;
use App\Models\User;
use App\Services\AttendanceSchedules;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private Employee $first;

    private Employee $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 11, 3)->setTime(10, 0));
        $d = Department::create(['code' => 'BATCH', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        $make = fn ($i) => Employee::create(['employee_number' => 'BATCH-'.$i, 'full_name' => 'Pegawai '.$i, 'work_email' => 'batch'.$i.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2025-01-01']);
        $this->first = $make(1);
        $this->second = $make(2);
        $this->hr = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($this->hr)->withSession(['account_auth_version' => 1]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function input(array $extra = []): array
    {
        return [...['employee_ids' => [$this->first->id, $this->second->id], 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-06', 'weekdays' => [1, 2, 3, 4, 5], 'starts_at' => '08:00', 'ends_at' => '17:00', 'ends_next_day' => false, 'skip_holidays' => true, 'tolerance_minutes' => 10, 'reason' => 'Penetapan jadwal kerja tim operasional'], ...$extra];
    }

    private function preview(array $extra = []): array
    {
        $this->post('/attendance/schedules/preview', $this->input($extra))->assertRedirect('/attendance/schedules')->assertSessionHasNoErrors();

        return session('schedule_preview');
    }

    private function commit(string $token)
    {
        return $this->post('/attendance/schedules', ['token' => $token, 'confirm' => true]);
    }

    public function test_hr_preview_commit_wib_audit_and_replay(): void
    {
        $this->get('/attendance/schedules')->assertOk();
        $p = $this->preview();
        $this->assertSame(['total' => 6, 'create' => 6, 'skip' => 0], $p['summary']);
        $this->assertDatabaseCount('attendance_days', 0);
        $this->commit($p['token'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_days', 6);
        $this->assertDatabaseCount('attendance_events', 6);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $this->first->id, 'date' => '2026-11-04', 'scheduled_start' => '2026-11-04 01:00:00', 'scheduled_end' => '2026-11-04 10:00:00', 'clock_in' => null, 'clock_out' => null]);
        $ids = DB::table('attendance_events')->get()->map(fn ($e) => json_decode($e->changes, true)['batch_id'])->unique();
        $this->assertCount(1, $ids);
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('attendance_events', 6);
    }

    public function test_existing_holiday_leave_and_join_days_are_visible_skips(): void
    {
        AttendanceDay::create(['employee_id' => $this->first->id, 'date' => '2026-11-04', 'scheduled_start' => '2026-11-04 01:00', 'scheduled_end' => '2026-11-04 10:00', 'version' => 1]);
        DB::table('leave_holidays')->insert(['date' => '2026-11-05', 'name' => 'Libur kantor', 'created_at' => now()]);
        $this->second->join_date = '2026-11-06';
        $this->second->save();
        $p = $this->preview();
        $this->assertSame(['total' => 6, 'create' => 2, 'skip' => 4], $p['summary']);
        $this->assertCount(6, $p['rows']);
        $this->commit($p['token'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_days', 3);
        $this->assertDatabaseCount('attendance_events', 2);
    }

    public function test_snapshot_changes_abort_entire_batch_and_preview_expires(): void
    {
        $p = $this->preview();
        AttendanceDay::create(['employee_id' => $this->second->id, 'date' => '2026-11-06', 'scheduled_start' => '2026-11-06 01:00', 'scheduled_end' => '2026-11-06 10:00', 'version' => 1]);
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('attendance_days', 1);
        $p = $this->preview();
        $this->travel(16)->minutes();
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('attendance_days', 1);
    }

    public function test_roles_tamper_actor_and_validation(): void
    {
        $p = $this->preview();
        $this->commit('broken-token')->assertSessionHasErrors('preview');
        $other = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($other)->withSession(['account_auth_version' => 1]);
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        foreach (['viewer', 'employee', 'supervisor'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->withSession(['account_auth_version' => 1]);
            $this->get('/attendance/schedules')->assertForbidden();
            $this->post('/attendance/schedules/preview', $this->input())->assertForbidden();
            $this->commit($p['token'])->assertForbidden();
        }
        $this->actingAs($this->hr)->withSession(['account_auth_version' => 1]);
        foreach ([['employee_ids' => []], ['employee_ids' => [$this->first->id, $this->first->id]], ['weekdays' => []], ['ends_on' => '2026-12-10'], ['starts_on' => '2026-11-02'], ['starts_at' => '18:00'], ['reason' => 'short']] as $extra) {
            $this->post('/attendance/schedules/preview', $this->input($extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_night_shift_overlap_and_adjacent_boundaries_are_respected(): void
    {
        AttendanceDay::create(['employee_id' => $this->first->id, 'date' => '2026-11-03', 'scheduled_start' => '2026-11-03 15:00', 'scheduled_end' => '2026-11-04 02:00', 'version' => 1]);
        $p = $this->preview(['employee_ids' => [$this->first->id], 'ends_on' => '2026-11-04']);
        $this->assertSame('overlap', $p['rows'][0]['code']);
        $this->assertSame(0, $p['summary']['create']);
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $p = $this->preview(['employee_ids' => [$this->first->id], 'ends_on' => '2026-11-04', 'starts_at' => '09:00', 'ends_at' => '17:00']);
        $this->assertSame('create', $p['rows'][0]['code']);
        $p = $this->preview(['employee_ids' => [$this->second->id], 'ends_on' => '2026-11-04', 'starts_at' => '22:00', 'ends_at' => '06:00', 'ends_next_day' => true]);
        $this->assertSame('2026-11-05T06:00', $p['rows'][0]['ends_at']);
        $this->commit($p['token'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $this->second->id, 'date' => '2026-11-04', 'scheduled_start' => '2026-11-04 15:00', 'scheduled_end' => '2026-11-04 23:00']);
    }

    public function test_leave_voided_rows_and_elapsed_start_times_are_not_scheduled(): void
    {
        LeaveRequest::create(['employee_id' => $this->first->id, 'submitted_by' => $this->hr->id, 'type' => 'permission', 'status' => 'approved', 'starts_on' => '2026-11-04', 'ends_on' => '2026-11-05', 'year' => 2026, 'days' => 1, 'work_dates' => ['2026-11-04'], 'reason' => 'Keperluan pribadi', 'version' => 1]);
        AttendanceDay::create(['employee_id' => $this->first->id, 'date' => '2026-11-05', 'scheduled_start' => '2026-11-05 01:00', 'scheduled_end' => '2026-11-05 10:00', 'voided_at' => now(), 'version' => 2]);
        $p = $this->preview(['employee_ids' => [$this->first->id], 'starts_on' => '2026-11-03']);
        $this->assertSame(['started', 'leave', 'existing', 'create'], array_column($p['rows'], 'code'));
        $this->commit($p['token'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_days', 2);
        $this->assertDatabaseHas('attendance_days', ['employee_id' => $this->first->id, 'date' => '2026-11-05', 'version' => 2]);
    }

    public function test_holiday_employee_and_leave_changes_require_a_new_preview(): void
    {
        $p = $this->preview();
        DB::table('leave_holidays')->insert(['date' => '2026-11-04', 'name' => 'Libur', 'created_at' => now()]);
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $p = $this->preview();
        $this->second->join_date = '2026-11-05';
        $this->second->version++;
        $this->second->save();
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $p = $this->preview();
        LeaveRequest::create(['employee_id' => $this->first->id, 'submitted_by' => $this->hr->id, 'type' => 'permission', 'status' => 'cancel_pending', 'starts_on' => '2026-11-06', 'ends_on' => '2026-11-06', 'year' => 2026, 'days' => 1, 'work_dates' => ['2026-11-06'], 'reason' => 'Keperluan pribadi', 'version' => 1]);
        $this->commit($p['token'])->assertSessionHasErrors('preview');
        $this->assertDatabaseCount('attendance_days', 0);
        $p = $this->preview();
        $this->second->delete();
        $this->commit($p['token'])->assertSessionHasErrors('employee_ids');
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_audit_failure_rolls_back_the_whole_batch_and_client_rows_are_ignored(): void
    {
        $p = $this->preview();
        $fail = true;
        $audits = 0;
        DB::listen(function (QueryExecuted $q) use (&$fail, &$audits) {
            if ($fail && str_contains($q->sql, 'insert into `attendance_events`') && ++$audits === 2) {
                throw new \RuntimeException('audit failed');
            }
        });
        try {
            app(AttendanceSchedules::class)->store($this->hr, $p['token']);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failed', $e->getMessage());
        }
        $this->assertDatabaseCount('attendance_days', 0);
        $this->assertDatabaseCount('attendance_events', 0);
        $fail = false;
        $this->post('/attendance/schedules', ['token' => $p['token'], 'confirm' => true, 'employee_ids' => [99999], 'rows' => [], 'reason' => 'Tampered payload'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_days', 6);
        $reason = json_decode(DB::table('attendance_events')->first()->changes, true)['reason'];
        $this->assertSame($this->input()['reason'], $reason);
    }

    public function test_slot_limit_and_holiday_opt_out_are_explicit(): void
    {
        $employees = [$this->first->id, $this->second->id];
        for ($i = 3; $i <= 34; $i++) {
            $e = $this->first->replicate();
            $e->employee_number = 'BATCH-'.$i;
            $e->work_email = 'batch'.$i.'@example.test';
            $e->save();
            $employees[] = $e->id;
        }
        $this->post('/attendance/schedules/preview', $this->input(['employee_ids' => $employees, 'starts_on' => '2026-11-04', 'ends_on' => '2026-12-04', 'weekdays' => [1, 2, 3, 4, 5, 6, 7]]))->assertSessionHasErrors('employee_ids');
        DB::table('leave_holidays')->insert(['date' => '2026-11-04', 'name' => 'Libur', 'created_at' => now()]);
        $p = $this->preview(['skip_holidays' => false]);
        $this->assertSame(6, $p['summary']['create']);
    }

    public function test_maximum_unicode_reason_produces_a_submittable_preview(): void
    {
        $p = $this->preview(['reason' => str_repeat('🙂', 2000)]);
        $this->commit($p['token'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attendance_days', 6);
    }
}
