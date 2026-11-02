<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\LeaveRecords;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LeaveCurrentReadTest extends TestCase
{
    use DatabaseMigrations;

    protected function beforeRefreshingDatabase(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'hris_lite_test' || DB::connection()->getConfig('username') !== 'hris_test') {
            throw new \RuntimeException('This test may refresh only the dedicated hris_lite_test database with hris_test.');
        }
    }

    public function test_employee_and_manager_lifecycle_changes_use_current_rows_after_snapshot(): void
    {
        $this->assertSame('hris_lite_test', DB::connection()->getDatabaseName());
        $d = Department::create(['code' => 'RACE', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        $make = fn ($n) => Employee::create(['employee_number' => 'RACE-'.$n, 'full_name' => 'Pegawai '.$n, 'work_email' => 'race'.$n.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2025-01-01']);
        $e = $make(1);
        $m = $make(2);
        $e->manager_employee_id = $m->id;
        $e->save();
        $worker = User::factory()->create(['role' => 'employee']);
        $worker->employee_id = $e->id;
        $worker->save();
        $boss = User::factory()->create(['role' => 'supervisor']);
        $boss->employee_id = $m->id;
        $boss->save();
        $s = app(LeaveRecords::class);
        $start = now('Asia/Jakarta')->addWeek()->nextWeekday()->toDateString();
        $l = $s->submit($worker, ['type' => 'permission', 'starts_on' => $start, 'ends_on' => $start, 'reason' => 'Keperluan keluarga mendesak']);
        config(['database.connections.leave_race' => config('database.connections.mysql')]);
        $other = DB::connection('leave_race');
        $armed = false;
        $target = $e->id;
        DB::listen(function (QueryExecuted $q) use (&$armed, &$target, $other, $boss, $e) {
            // A separate committed lifecycle update after actor() has created its RR snapshot,
            // before decide() obtains its current employee lock. No parallel test runner needed.
            if ($armed && $q->connectionName === 'mysql' && str_contains($q->sql, 'from `users`') && ($q->bindings[0] ?? null) === $boss->id) {
                $armed = false;
                $other->table('employees')->where('id', $target)->update($target === $e->id ? ['deleted_at' => now()] : ['employment_status' => 'inactive']);
            }
        });
        try {
            foreach ([$e->id, $m->id] as $targetId) {
                DB::table('employees')->where('id', $e->id)->update(['deleted_at' => null]);
                $target = $targetId;
                $armed = true;
                try {
                    $s->decide($boss, $l, ['version' => 1, 'action' => 'approve', 'reason' => 'Disetujui sesuai kebutuhan']);
                    $this->fail('Lifecycle change must prevent supervisor decision');
                } catch (HttpException $ex) {
                    $this->assertSame(404, $ex->getStatusCode());
                }
                $this->assertFalse($armed, 'Concurrent update hook must actually run');
                $this->assertDatabaseHas('leave_requests', ['id' => $l->id, 'status' => 'pending_supervisor', 'version' => 1]);
            }
        } finally {
            $armed = false;
            DB::disconnect('leave_race');
            DB::table('leave_events')->where('leave_request_id', $l->id)->delete();
            DB::table('leave_requests')->where('id', $l->id)->delete();
            DB::table('employees')->where('id', $e->id)->update(['manager_employee_id' => null]);
            DB::table('users')->whereIn('id', [$worker->id, $boss->id])->delete();
            DB::table('employees')->whereIn('id', [$e->id, $m->id])->delete();
            $p->delete();
            $d->delete();
        }
    }
}
