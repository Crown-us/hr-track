<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\LeaveRecords;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    private User $worker;

    private User $boss;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTimezone('Asia/Jakarta')->setDate(2026, 11, 3)->setTime(10, 0));
        $d = Department::create(['code' => 'OPS', 'name' => 'Operasional']);
        $p = Position::create(['code' => 'STF', 'name' => 'Staf', 'department_id' => $d->id]);
        $make = fn ($i) => Employee::create(['employee_number' => 'EMP-'.$i, 'full_name' => 'Pegawai '.$i, 'work_email' => 'staff'.$i.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2025-01-01']);
        $this->employee = $make(1);
        $manager = $make(2);
        $this->employee->manager_employee_id = $manager->id;
        $this->employee->save();
        $this->worker = User::factory()->create(['role' => 'employee']);
        $this->worker->employee_id = $this->employee->id;
        $this->worker->save();
        $this->boss = User::factory()->create(['role' => 'supervisor']);
        $this->boss->employee_id = $manager->id;
        $this->boss->save();
        $this->hr = User::factory()->create(['role' => 'hr_staff']);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function as(User $user): static
    {
        return $this->actingAs($user)->withSession(['account_auth_version' => $user->auth_version]);
    }

    private function allocation(int $days = 12, int $version = 0)
    {
        return $this->as($this->hr)->post('/leave/settings/balances', ['employee_id' => $this->employee->id, 'year' => 2026, 'days' => $days, 'version' => $version, 'reason' => 'Alokasi sesuai kebijakan kantor']);
    }

    private function submit(array $data = [])
    {
        return $this->as($this->worker)->post('/leave', [...['type' => 'annual', 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-09', 'reason' => 'Keperluan keluarga di luar kota'], ...$data]);
    }

    private function latest(): int
    {
        return (int) DB::table('leave_requests')->max('id');
    }

    private function action(User $u, int $id, string $action, int $version = 1)
    {
        return $this->as($u)->post('/leave/'.$id.'/action', ['action' => $action, 'version' => $version, 'reason' => 'Keputusan sesuai kebutuhan operasional']);
    }

    public function test_roles_and_private_pages_are_enforced(): void
    {
        $this->get('/leave')->assertRedirect('/login');
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->as($viewer)->get('/leave')->assertForbidden();
        $this->as($this->worker)->get('/leave/settings')->assertForbidden();
        $this->as($this->hr)->get('/leave/create')->assertForbidden();
        $this->as($this->worker)->get('/leave')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get('/leave/create')->assertOk();
        $this->as($this->hr)->get('/leave/settings')->assertOk();
    }

    public function test_workday_snapshot_reservation_two_stage_approval_and_balance(): void
    {
        $this->allocation()->assertSessionHasNoErrors();
        $this->as($this->hr)->post('/leave/settings/holidays', ['date' => '2026-11-06', 'name' => 'Libur kantor', 'reason' => 'Penetapan libur kantor tahunan'])->assertSessionHasNoErrors();
        $this->submit()->assertSessionHasNoErrors();
        $id = $this->latest();
        $row = DB::table('leave_requests')->find($id);
        $this->assertSame(['2026-11-05', '2026-11-09'], json_decode($row->work_dates, true));
        $this->assertSame('pending_supervisor', $row->status);
        $this->as($this->worker)->get('/leave')->assertInertia(fn (Assert $p) => $p->component('leaves')->where('balance.available', 10)->where('balance.reserved', 2)->where('balance.used', 0));
        $this->action($this->hr, $id, 'approve')->assertForbidden();
        $this->action($this->boss, $id, 'approve')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'pending_hr', 'version' => 2]);
        $this->action($this->hr, $id, 'approve', 2)->assertSessionHasNoErrors();
        $this->as($this->worker)->get('/leave')->assertInertia(fn (Assert $p) => $p->where('balance.available', 10)->where('balance.reserved', 0)->where('balance.used', 2));
        $this->assertSame(3, DB::table('leave_events')->where('leave_request_id', $id)->count());
    }

    public function test_overlaps_insufficient_balance_and_stale_updates_do_not_mutate(): void
    {
        $this->allocation(3)->assertSessionHasNoErrors();
        $this->submit()->assertSessionHasNoErrors();
        $id = $this->latest();
        $this->submit(['type' => 'permission'])->assertSessionHasErrors('starts_on');
        $this->submit(['starts_on' => '2026-11-10', 'ends_on' => '2026-11-10'])->assertSessionHasErrors('balance');
        $this->allocation(2, 1)->assertSessionHasErrors('days');
        $this->action($this->boss, $id, 'approve')->assertSessionHasNoErrors();
        $this->action($this->hr, $id, 'reject')->assertSessionHasErrors('version');
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'pending_hr', 'version' => 2]);
        $this->action($this->hr, $id, 'reject', 2)->assertSessionHasNoErrors();
        $this->submit()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('leave_requests', 2);
    }

    public function test_cancellation_keeps_reservation_until_hr_accepts_and_cannot_replay(): void
    {
        $this->allocation();
        $this->submit();
        $id = $this->latest();
        $this->action($this->boss, $id, 'approve');
        $this->action($this->hr, $id, 'approve', 2);
        $this->action($this->worker, $id, 'cancel', 3)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'cancel_pending']);
        $this->submit(['type' => 'permission'])->assertSessionHasErrors('starts_on');
        $this->action($this->hr, $id, 'reject_cancel', 4)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'approved']);
        $this->action($this->worker, $id, 'cancel', 5);
        $this->action($this->hr, $id, 'approve_cancel', 6)->assertSessionHasNoErrors();
        $this->action($this->hr, $id, 'approve_cancel', 7)->assertSessionHasErrors('leave');
        $this->submit()->assertSessionHasNoErrors();
    }

    public function test_current_manager_only_self_approval_denied_and_orphan_can_go_to_hr(): void
    {
        $this->submit(['type' => 'permission']);
        $id = $this->latest();
        $outsider = User::factory()->create(['role' => 'supervisor']);
        $this->as($outsider)->get('/leave/'.$id)->assertNotFound();
        $this->action($outsider, $id, 'approve')->assertNotFound();
        $this->as($outsider)->get('/leave?scope=team')->assertForbidden();
        $this->hr->employee_id = $this->employee->id;
        $this->worker->employee_id = null;
        $this->worker->save();
        $this->hr->save();
        $this->action($this->hr, $id, 'approve')->assertForbidden();
        $this->hr->employee_id = null;
        $this->hr->save();
        $this->boss->is_active = false;
        $this->boss->save();
        $this->action($this->hr, $id, 'approve')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'approved']);
    }

    public function test_dates_wib_year_boundaries_weekends_and_unfunded_requests(): void
    {
        $this->submit()->assertSessionHasErrors('balance');
        foreach ([['starts_on' => '2026-11-07', 'ends_on' => '2026-11-08'], ['starts_on' => '2026-12-31', 'ends_on' => '2027-01-04'], ['starts_on' => '2026-10-20', 'ends_on' => '2026-10-20'], ['starts_on' => '2026-11-04', 'ends_on' => '2027-01-04']] as $dates) {
            $this->submit(['type' => 'permission', ...$dates])->assertSessionHasErrors('starts_on');
        }
        $this->submit(['type' => 'sick', 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-02'])->assertSessionHasNoErrors();
        $this->action($this->worker, $this->latest(), 'cancel')->assertSessionHasNoErrors();
        $this->submit(['starts_on' => '2026-11-02', 'ends_on' => '2026-11-02'])->assertSessionHasErrors('starts_on');
    }

    public function test_evidence_is_private_scoped_validated_and_not_exposed_in_props(): void
    {
        Storage::fake('leave_documents');
        $this->submit(['type' => 'permission', 'document' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors();
        $id = $this->latest();
        $this->as($this->worker)->get('/leave/'.$id.'/document')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->as($this->hr)->get('/leave/'.$id.'/document')->assertOk();
        $this->as($this->boss)->get('/leave/'.$id.'/document')->assertForbidden();
        $this->get('/leave/'.$id)->assertInertia(fn (Assert $p) => $p->where('leave.has_document', false)->missing('leave.document_path')->missing('leave.document_bytes'));
        $outsider = User::factory()->create(['role' => 'employee']);
        $this->as($outsider)->get('/leave/'.$id.'/document')->assertNotFound();
        $this->submit(['type' => 'sick', 'document' => UploadedFile::fake()->createWithContent('bukti.pdf', 'not a PDF')])->assertSessionHasErrors('document')->assertRedirect('/leave/create');
        $this->submit(['type' => 'sick', 'document' => UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('document');
        $this->assertCount(1, Storage::disk('leave_documents')->allFiles());
    }

    public function test_holiday_changes_never_recalculate_old_requests_and_allocation_is_versioned(): void
    {
        $this->allocation();
        $this->allocation(15)->assertSessionHasErrors('version');
        $this->submit();
        $id = $this->latest();
        $this->as($this->hr)->post('/leave/settings/holidays', ['date' => '2026-11-06', 'name' => 'Libur kantor', 'reason' => 'Penetapan kalender tahunan'])->assertSessionHasNoErrors();
        $holiday = DB::table('leave_holidays')->value('id');
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'days' => 3]);
        $this->as($this->hr)->post('/leave/settings/holidays', ['date' => '2026-11-06', 'name' => 'Duplikat', 'reason' => 'Penetapan kalender tahunan'])->assertSessionHasErrors('date');
        $this->as($this->worker)->post('/leave/settings/holidays/'.$holiday.'/remove', ['reason' => 'Penetapan kalender tahunan'])->assertForbidden();
        $this->as($this->hr)->post('/leave/settings/holidays/'.$holiday.'/remove', ['reason' => 'Koreksi kalender tahunan'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'days' => 3]);
        $this->assertDatabaseCount('leave_holidays', 0);
    }

    public function test_scopes_follow_manager_changes_and_archiving_and_no_client_identity_is_used(): void
    {
        $this->submit(['type' => 'permission', 'employee_id' => $this->boss->employee_id, 'days' => 1, 'status' => 'approved'])->assertSessionHasNoErrors();
        $id = $this->latest();
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'employee_id' => $this->employee->id, 'days' => 3, 'status' => 'pending_supervisor']);
        $this->as($this->boss)->get('/leave?scope=team')->assertInertia(fn (Assert $p) => $p->where('requests.total', 1));
        $this->employee->manager_employee_id = null;
        $this->employee->save();
        $this->action($this->boss, $id, 'approve')->assertNotFound();
        $this->action($this->hr, $id, 'approve')->assertSessionHasNoErrors();
        $this->employee->delete();
        $this->as($this->worker)->get('/leave')->assertForbidden();
        $this->as($this->worker)->get('/leave/'.$id)->assertForbidden();
        $this->as($this->hr)->get('/leave/'.$id)->assertOk();
        $this->action($this->hr, $id, 'cancel', 2)->assertSessionHasNoErrors();
    }

    public function test_started_approved_leave_cannot_be_cancelled_and_pending_can_be_withdrawn(): void
    {
        $this->employee->manager_employee_id = null;
        $this->employee->save();
        $this->submit(['type' => 'permission', 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-03']);
        $id = $this->latest();
        $this->action($this->hr, $id, 'approve')->assertSessionHasNoErrors();
        $this->action($this->worker, $id, 'cancel', 2)->assertForbidden();
        $this->action($this->hr, $id, 'cancel', 2)->assertForbidden();
        $this->submit(['type' => 'permission'])->assertSessionHasNoErrors();
        $pending = $this->latest();
        $this->action($this->worker, $pending, 'cancel')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('leave_requests', ['id' => $pending, 'status' => 'cancelled']);
    }

    public function test_audit_failures_roll_back_reservations_decisions_allocations_and_documents(): void
    {
        Storage::fake('leave_documents');
        $s = app(LeaveRecords::class);
        $fail = false;
        DB::listen(function (QueryExecuted $q) use (&$fail) {
            if ($fail && str_contains($q->sql, 'insert into `leave_events`')) {
                throw new \RuntimeException('audit failed');
            }
        });
        $d = ['type' => 'permission', 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-09', 'reason' => 'Kebutuhan keluarga mendesak'];
        $fail = true;
        try {
            $s->submit($this->worker, $d, UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n%%EOF"));
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failed', $e->getMessage());
        }
        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertCount(0, Storage::disk('leave_documents')->allFiles());
        $fail = false;
        $l = $s->submit($this->worker, $d);
        $fail = true;
        foreach ([fn () => $s->decide($this->boss, $l, ['action' => 'approve', 'version' => 1, 'reason' => 'Disetujui sesuai kebutuhan']), fn () => $s->allocate($this->hr, ['employee_id' => $this->employee->id, 'year' => 2026, 'days' => 12, 'version' => 0, 'reason' => 'Alokasi tahunan pegawai']), fn () => $s->holiday($this->hr, ['date' => '2026-11-06', 'name' => 'Libur', 'reason' => 'Penetapan libur kantor'])] as $fn) {
            try {
                $fn();
                $this->fail('Expected audit failure');
            } catch (\RuntimeException $e) {
                $this->assertSame('audit failed', $e->getMessage());
            }
        }
        $this->assertDatabaseHas('leave_requests', ['id' => $l->id, 'status' => 'pending_supervisor', 'version' => 1]);
        $this->assertDatabaseCount('leave_balances', 0);
        $this->assertDatabaseCount('leave_holidays', 0);
        $this->assertDatabaseCount('leave_events', 1);
    }
}
