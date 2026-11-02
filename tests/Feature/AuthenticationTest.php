<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_open_login_but_not_hr(): void
    {
        $this->withoutVite()->get('/login')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth', false)->where('mode', 'login'));
        $this->get('/hr')->assertRedirect('/login');
    }

    public function test_login_and_logout_use_a_real_session(): void
    {
        $user = User::factory()->create(['role' => 'hr_staff']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/hr');
        $this->assertAuthenticatedAs($user);
        $this->withoutVite()->get('/hr')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('hr', false)
                ->where('auth.user.email', $user->email)->missing('auth.user.password'));
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/hr')->assertRedirect('/login');
    }

    public function test_wrong_password_is_rejected_and_login_is_limited(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk. Tunggu satu menit lalu coba lagi.']);
        $this->assertGuest();
    }

    public function test_public_signup_cannot_create_an_account(): void
    {
        $this->withoutVite()->get('/register')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth', false)->where('invitation', null));
        $this->post('/register', ['name' => 'Outsider', 'email' => 'outsider@example.test', 'password' => 'Password123456!', 'role' => 'admin'])
            ->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invitation_is_single_use_and_cannot_escalate_role(): void
    {
        $token = $this->invite();
        $this->withoutVite()->get('/register?token='.$token)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('invitation.email', 'new@example.test'));
        $payload = ['token' => $token, 'name' => 'New HR', 'email' => 'attacker@example.test', 'role' => 'admin',
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!'];
        $this->post('/register', $payload)->assertRedirect('/hr');
        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('hr_staff', $user->role);
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->post('/register', $payload)->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_expired_invitation_and_weak_password_are_rejected(): void
    {
        $token = $this->invite();
        $this->post('/register', ['token' => $token, 'name' => 'New HR', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->travel(3)->days();
        $this->post('/register', ['token' => $token, 'name' => 'New HR', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!'])
            ->assertSessionHasErrors('token');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_roles_cannot_access_hr(): void
    {
        $user = User::factory()->create(['role' => 'invalid']);
        $this->actingAs($user)->get('/hr')->assertForbidden();
    }

    public function test_passwords_beyond_bcrypt_byte_limit_are_rejected_before_hashing(): void
    {
        $token = $this->invite();
        $password = 'Aa1'.str_repeat('é', 36);
        $this->post('/register', ['token' => $token, 'name' => 'New HR', 'password' => $password, 'password_confirmation' => $password])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_csrf_is_enforced_outside_the_testing_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/login', ['email' => 'hr@example.test', 'password' => 'incorrect'])->assertStatus(419);
        $this->post('/register', ['name' => 'Outsider'])->assertStatus(419);
    }

    public function test_authenticated_users_cannot_use_signup_and_auth_pages_are_not_cached(): void
    {
        $user = User::factory()->create();
        $this->withoutVite()->get('/login')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($user)->get('/register')->assertRedirect('/hr');
    }

    public function test_operator_can_create_first_admin_but_cannot_repeat_bootstrap(): void
    {
        $this->artisan('hr:create-admin')->expectsQuestion('Nama admin', 'Admin Fiktif')
            ->expectsQuestion('Email admin', 'admin@example.test')
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)', 'StrongPassword123!')
            ->expectsQuestion('Ulangi kata sandi', 'StrongPassword123!')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'admin@example.test', 'role' => 'admin']);
        $this->artisan('hr:create-admin')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invitation_command_hashes_tokens_and_reissuing_invalidates_the_old_token(): void
    {
        $this->artisan('hr:invite', ['email' => 'invite@example.test', '--role' => 'viewer'])->assertSuccessful();
        $old = DB::table('account_invitations')->where('email', 'invite@example.test')->first();
        $this->assertSame(64, strlen($old->token_hash));
        $this->assertSame('viewer', $old->role);
        $this->artisan('hr:invite', ['email' => 'invite@example.test'])->assertSuccessful();
        $this->assertNotSame($old->token_hash, DB::table('account_invitations')->where('email', 'invite@example.test')->value('token_hash'));
        $this->assertDatabaseCount('account_invitations', 1);
        $this->artisan('hr:invite', ['email' => 'invite@example.test', '--role' => 'superuser'])->assertFailed();
    }

    private function invite(): string
    {
        $token = Str::random(64);
        DB::table('account_invitations')->insert(['email' => 'new@example.test', 'role' => 'hr_staff',
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(2), 'created_at' => now(), 'updated_at' => now()]);

        return $token;
    }
}
