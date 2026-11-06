<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_the_audit_trail(): void
    {
        $this->get('/audit')->assertRedirect('/login');
        foreach (['hr_staff', 'viewer', 'employee', 'supervisor'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/audit')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->withoutVite()->get('/audit')->assertOk();
    }

    public function test_events_from_different_modules_are_merged_newest_first_without_payloads(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedEvents($admin);
        $this->actingAs($admin)->withoutVite()->get('/audit')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('audit', false)
                ->where('events.total', 2)
                ->where('events.data.0.module', 'Akun')
                ->where('events.data.0.actor', 'Sistem')
                ->where('events.data.0.subject_url', '/accounts/'.$admin->id)
                ->where('events.data.1.module', 'Pegawai')
                ->where('events.data.1.actor', $admin->name)
                ->where('events.data.1.action', 'created')
                ->has('events.data.0', fn (Assert $row) => $row->missing('changes')->missing('reason')->missing('note')->etc())
                ->has('events.data.1', fn (Assert $row) => $row->missing('changes')->missing('reason')->missing('note')->etc())
                ->where('actions', ['created', 'role_changed']));
    }

    public function test_filters_narrow_by_module_action_and_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedEvents($admin);
        $this->actingAs($admin)->withoutVite();
        $this->get('/audit?source=employee')->assertInertia(fn (Assert $page) => $page->where('events.total', 1)->where('events.data.0.module', 'Pegawai')->where('actions', ['created']));
        $this->get('/audit?action=role_changed')->assertInertia(fn (Assert $page) => $page->where('events.total', 1)->where('events.data.0.module', 'Akun'));
        $tomorrow = now('Asia/Jakarta')->addDay()->toDateString();
        $this->get('/audit?from='.$tomorrow)->assertInertia(fn (Assert $page) => $page->where('events.total', 0));
        $today = now('Asia/Jakarta')->toDateString();
        $this->get('/audit?from='.$today.'&to='.$today)->assertInertia(fn (Assert $page) => $page->where('events.total', 2));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/audit?source=users;drop')->assertSessionHasErrors('source');
        $this->get('/audit?from=2026-02-10&to=2026-02-01')->assertSessionHasErrors('to');
        $this->get('/audit?from=10-02-2026')->assertSessionHasErrors('from');
    }

    private function seedEvents(User $admin): void
    {
        $department = Department::create(['code' => 'HR', 'name' => 'Human Resources']);
        $position = Position::create(['department_id' => $department->id, 'name' => 'HR Staff']);
        app(EmployeeRecords::class)->save(null, ['employee_number' => 'EMP-001', 'full_name' => 'Nadia Fiktif', 'work_email' => 'nadia@example.test',
            'phone' => null, 'department_id' => $department->id, 'position_id' => $position->id, 'employment_status' => 'permanent', 'join_date' => '2026-01-01'], $admin);
        DB::table('employee_events')->update(['created_at' => now()->subMinute()]);
        DB::table('account_events')->insert(['actor_id' => null, 'target_user_id' => $admin->id, 'action' => 'role_changed',
            'changes' => json_encode(['secret' => 'must not be exposed']), 'created_at' => now()]);
    }
}
