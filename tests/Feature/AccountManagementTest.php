<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\AccountAdministration;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_invitation_releases_employee_reservation_and_old_token_stays_invalid(): void
    {
        $this->admin();
        $e = $this->employee();
        [$old,$token] = $this->invite($e);
        $this->post('/accounts/invitations', $this->proof(['email' => $e->work_email, 'role' => 'employee', 'employee_id' => $e->id]))->assertSessionHasErrors('email');
        $this->travel(49)->hours();
        $this->post('/accounts/invitations', $this->proof(['email' => 'replacement@example.test', 'role' => 'employee', 'employee_id' => $e->id]))->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->post('/register', ['token' => $token, 'name' => 'Pegawai', 'password' => 'EmployeePassword123!', 'password_confirmation' => 'EmployeePassword123!'])->assertSessionHasErrors('token');
        $this->assertDatabaseMissing('users', ['email' => $e->work_email]);
        $this->travelBack();
    }

    public function test_reset_expiry_and_missing_legacy_session_version_deny_access_but_fresh_login_works(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'viewer', 'password' => self::PASSWORD]);
        $this->post('/accounts/'.$target->id.'/reset-link', $this->proof(['version' => 1]))->assertSessionHasNoErrors();
        $url = session('account_access_link');
        $token = basename(parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->post('/logout');
        $this->travel(61)->minutes();
        $this->post('/reset-password', ['token' => $token, 'email' => $query['email'], 'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!'])->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check(self::PASSWORD, $target->fresh()->password));
        $this->travelBack();
        $target->auth_version = 2;
        $target->save();
        $this->actingAs($target)->withSession(['account_auth_version' => null]);
        $this->get('/hr')->assertRedirect('/login');
        $this->post('/login', ['email' => $target->email, 'password' => self::PASSWORD])->assertRedirect('/hr');
        $this->get('/hr')->assertOk();
        $this->assertSame(2, session('account_auth_version'));
    }

    public function test_audit_failure_rolls_back_account_invitation_reset_and_reporting_changes(): void
    {
        $admin = $this->admin();
        $e = $this->employee();
        $manager = $this->employee(2);
        $target = User::factory()->create(['role' => 'viewer']);
        $boss = User::factory()->create(['role' => 'supervisor']);
        $boss->employee_id = $manager->id;
        $boss->save();
        $records = app(AccountAdministration::class);
        DB::listen(function (QueryExecuted $query) {
            if (str_contains($query->sql, 'insert into `account_events`')) {
                throw new \RuntimeException('audit failed');
            }
        });
        $actions = [fn () => $records->invite(['email' => 'rollback@example.test', 'role' => 'employee', 'employee_id' => $e->id], $admin),
            fn () => $records->update($target, $this->proof(['version' => 1, 'role' => 'hr_staff', 'is_active' => true, 'employee_id' => null]), $admin),
            fn () => $records->resetLink($target, $this->proof(['version' => 1]), $admin),
            fn () => $records->reporting($this->proof(['employee_id' => $e->id, 'manager_employee_id' => $manager->id, 'version' => 1]), $admin)];
        foreach ($actions as $action) {
            try {
                $action();
                $this->fail('Expected failure');
            } catch (\RuntimeException $ex) {
                $this->assertSame('audit failed', $ex->getMessage());
            }
        }
        $this->assertDatabaseMissing('account_invitations', ['email' => 'rollback@example.test']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'viewer', 'version' => 1, 'auth_version' => 1]);
        $this->assertDatabaseHas('employees', ['id' => $e->id, 'manager_employee_id' => null, 'version' => 1]);
    }

    public function test_access_change_revokes_outstanding_password_reset_link(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'viewer']);
        $this->post('/accounts/'.$target->id.'/reset-link', $this->proof(['version' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $target->email]);
        $this->put('/accounts/'.$target->id, $this->proof(['version' => $target->fresh()->version, 'role' => 'hr_staff', 'is_active' => true, 'employee_id' => null]))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_reset_issuance_rejects_stale_form_and_validation_keeps_reset_form(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'viewer']);
        $this->post('/accounts/'.$target->id.'/reset-link', $this->proof(['version' => 1]))->assertSessionHasNoErrors();
        $url = session('account_access_link');
        $token = basename(parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $hash = DB::table('password_reset_tokens')->where('email', $target->email)->value('token');
        $this->post('/accounts/'.$target->id.'/reset-link', $this->proof(['version' => 1]))->assertSessionHasErrors('version');
        $this->assertSame($hash, DB::table('password_reset_tokens')->where('email', $target->email)->value('token'));
        $this->post('/logout');
        $this->post('/reset-password', ['token' => $token, 'email' => $query['email'], 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password')->assertRedirect($url);
    }

    public function test_invited_employee_must_still_be_active_at_activation_and_audit_contains_no_secrets(): void
    {
        $this->admin();
        $e = $this->employee();
        [$inv,$token] = $this->invite($e);
        $audit = DB::table('account_events')->pluck('changes')->implode(' ');
        $this->assertStringNotContainsString($token, $audit);
        $this->assertStringNotContainsString(self::PASSWORD, $audit);
        $e->update(['employment_status' => 'inactive']);
        $this->post('/logout');
        $this->post('/register', ['token' => $token, 'name' => 'Pegawai', 'password' => 'EmployeePassword123!', 'password_confirmation' => 'EmployeePassword123!'])->assertSessionHasErrors('token');
        $this->assertDatabaseMissing('users', ['email' => $e->work_email]);
        $this->assertDatabaseHas('account_invitations', ['id' => $inv->id, 'used_at' => null]);
    }

    private const PASSWORD = 'AdminPassword123!';

    private function admin(): User
    {
        $this->withoutVite();
        $u = User::factory()->create(['role' => 'admin', 'password' => self::PASSWORD]);
        $this->actingAs($u);

        return $u;
    }

    private function employee(int $n = 1): Employee
    {
        $d = Department::firstOrCreate(['code' => 'ACC'], ['name' => 'Operasional']);
        $p = Position::firstOrCreate(['department_id' => $d->id, 'name' => 'Staf']);

        return Employee::create(['employee_number' => 'ACC-'.$n, 'full_name' => 'Pegawai '.$n, 'work_email' => 'acc'.$n.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2026-01-01']);
    }

    private function proof(array $extra = []): array
    {
        return [...['current_password' => self::PASSWORD, 'reason' => 'Penyesuaian akses sesuai tugas'], ...$extra];
    }

    private function invite(Employee $e, string $role = 'employee'): array
    {
        $this->post('/accounts/invitations', $this->proof(['email' => $e->work_email, 'role' => $role, 'employee_id' => $e->id]))->assertSessionHasNoErrors()->assertRedirect('/accounts');
        $url = session('account_access_link');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return [DB::table('account_invitations')->where('email', $e->work_email)->first(), $query['token']];
    }

    public function test_only_admin_can_manage_accounts_and_password_confirmation_is_required(): void
    {
        $this->admin();
        $this->get('/accounts')->assertOk();
        $e = $this->employee();
        $this->post('/accounts/invitations', ['email' => $e->work_email, 'role' => 'employee', 'employee_id' => $e->id])->assertSessionHasErrors('current_password');
        foreach (['hr_staff', 'viewer', 'employee', 'supervisor'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            if (in_array($role, ['employee', 'supervisor'])) {
                $u->employee_id = $e->id;
                $u->save();
            }
            $this->actingAs($u);
            $this->get('/accounts')->assertForbidden();
            $this->post('/accounts/invitations', $this->proof(['email' => 'x@example.test', 'role' => 'admin']))->assertForbidden();
            $u->employee_id = null;
            $u->save();
        }
    }

    public function test_employee_invitation_is_reserved_activated_once_and_role_comes_from_server(): void
    {
        $admin = $this->admin();
        $e = $this->employee();
        [$inv,$token] = $this->invite($e);
        $this->assertNotSame($token, $inv->token_hash);
        $this->post('/accounts/invitations', $this->proof(['email' => 'other@example.test', 'role' => 'employee', 'employee_id' => $e->id]))->assertSessionHasErrors('employee_id');
        $this->post('/logout');
        $payload = ['token' => $token, 'name' => 'Pegawai Satu', 'password' => 'EmployeePassword123!', 'password_confirmation' => 'EmployeePassword123!', 'role' => 'admin', 'employee_id' => 999];
        $this->post('/register', $payload)->assertSessionHasNoErrors()->assertRedirect('/me');
        $u = User::where('email', $e->work_email)->firstOrFail();
        $this->assertSame('employee', $u->role);
        $this->assertSame($e->id, $u->employee_id);
        $this->get('/me')->assertInertia(fn (Assert $p) => $p->where('employee.id', $e->id));
        foreach (['/hr', '/employees', '/applications', '/discipline', '/reports', '/accounts'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/logout');
        $this->post('/register', $payload)->assertSessionHasErrors('token');
    }

    public function test_admin_cannot_disable_self_and_changes_require_current_version_and_reason(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'viewer']);
        $this->put('/accounts/'.$admin->id, $this->proof(['version' => 1, 'role' => 'viewer', 'is_active' => true, 'employee_id' => null]))->assertSessionHasErrors('account');
        $this->put('/accounts/'.$target->id, $this->proof(['version' => 1, 'role' => 'hr_staff', 'is_active' => true, 'employee_id' => null]))->assertSessionHasNoErrors();
        $this->put('/accounts/'.$target->id, $this->proof(['version' => 1, 'role' => 'viewer', 'is_active' => true, 'employee_id' => null]))->assertSessionHasErrors('version');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'hr_staff', 'version' => 2]);
        $this->assertDatabaseHas('account_events', ['actor_id' => $admin->id, 'target_user_id' => $target->id, 'action' => 'account_updated']);
        $this->actingAs($target->fresh())->withSession(['account_auth_version' => 1]);
        $this->get('/hr')->assertRedirect('/login');
    }

    public function test_disabled_accounts_cannot_use_old_sessions_or_login_and_archived_portal_employee_is_denied(): void
    {
        $admin = $this->admin();
        $e = $this->employee();
        $target = User::factory()->create(['role' => 'employee', 'password' => self::PASSWORD]);
        $target->employee_id = $e->id;
        $target->save();
        $this->put('/accounts/'.$target->id, $this->proof(['version' => 1, 'role' => 'employee', 'employee_id' => $e->id, 'is_active' => false]))->assertSessionHasNoErrors();
        $this->actingAs($target->fresh())->withSession(['account_auth_version' => 1]);
        $this->get('/me')->assertRedirect('/login');
        $this->post('/login', ['email' => $target->email, 'password' => self::PASSWORD])->assertSessionHasErrors('email');
        $this->actingAs($admin);
        $this->withSession(['account_auth_version' => 1]);
        $this->put('/accounts/'.$target->id, $this->proof(['version' => 2, 'role' => 'employee', 'employee_id' => $e->id, 'is_active' => true]))->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->post('/login', ['email' => $target->email, 'password' => self::PASSWORD])->assertRedirect('/me');
        $e->delete();
        $this->get('/me')->assertForbidden();
    }

    public function test_supervisor_sees_only_direct_team_and_manager_cycles_are_rejected(): void
    {
        $this->admin();
        $manager = $this->employee();
        $member = $this->employee(2);
        $outside = $this->employee(3);
        $boss = User::factory()->create(['role' => 'supervisor']);
        $boss->employee_id = $manager->id;
        $boss->save();
        $memberAccount = User::factory()->create(['role' => 'supervisor']);
        $memberAccount->employee_id = $member->id;
        $memberAccount->save();
        $this->post('/accounts/reporting', $this->proof(['employee_id' => $member->id, 'manager_employee_id' => $manager->id, 'version' => 1]))->assertSessionHasNoErrors();
        $this->post('/accounts/reporting', $this->proof(['employee_id' => $manager->id, 'manager_employee_id' => $member->id, 'version' => 1]))->assertSessionHasErrors('manager_employee_id');
        $this->post('/accounts/reporting', $this->proof(['employee_id' => $manager->id, 'manager_employee_id' => $manager->id, 'version' => 1]))->assertSessionHasErrors('manager_employee_id');
        $this->actingAs($boss);
        $this->get('/team')->assertInertia(fn (Assert $p) => $p->where('team.total', 1)->where('team.data.0.id', $member->id)->missing('team.data.0.phone')->missing('team.data.0.work_email'));
        $this->get('/team/'.$member->id)->assertOk();
        $this->get('/team/'.$outside->id)->assertNotFound();
        $this->get('/me')->assertInertia(fn (Assert $p) => $p->where('employee.id', $manager->id));
    }

    public function test_admin_reset_link_is_single_use_and_revokes_existing_sessions(): void
    {
        $this->admin();
        $target = User::factory()->create(['role' => 'viewer']);
        $this->post('/accounts/'.$target->id.'/reset-link', $this->proof(['version' => 1]))->assertSessionHasNoErrors();
        $url = session('account_access_link');
        $token = basename(parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->post('/logout');
        $this->get($url)->assertOk();
        $payload = ['email' => $query['email'], 'token' => $token, 'password' => 'ChangedPassword123!', 'password_confirmation' => 'ChangedPassword123!'];
        $this->post('/reset-password', $payload)->assertSessionHasNoErrors()->assertRedirect('/login');
        $this->assertTrue(Hash::check($payload['password'], $target->fresh()->password));
        $this->post('/reset-password', $payload)->assertSessionHasErrors('token');
        $this->actingAs($target->fresh())->withSession(['account_auth_version' => 1]);
        $this->get('/hr')->assertRedirect('/login');
    }

    public function test_invitation_revocation_and_inactive_employee_prevent_activation(): void
    {
        $this->admin();
        $e = $this->employee();
        [$inv,$token] = $this->invite($e);
        $this->post('/accounts/invitations/'.$inv->id.'/revoke', $this->proof(['version' => 1]))->assertSessionHasNoErrors();
        $this->post('/logout');
        $this->post('/register', ['token' => $token, 'name' => 'Pegawai', 'password' => 'EmployeePassword123!', 'password_confirmation' => 'EmployeePassword123!'])->assertSessionHasErrors('token');
    }
}
