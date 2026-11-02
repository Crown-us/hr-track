<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountAdministration
{
    private function lock(?User $actor = null): void
    {
        DB::table('account_administration_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
        if ($actor) {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($fresh->is_active && $fresh->role === 'admin' && $fresh->auth_version === $actor->auth_version, 403);
        }
    }

    private function audit(?User $actor, string $action, array $changes, ?int $target = null, ?int $employee = null, ?int $invitation = null): void
    {
        DB::table('account_events')->insert(['actor_id' => $actor?->id, 'target_user_id' => $target, 'employee_id' => $employee, 'invitation_id' => $invitation, 'action' => $action, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    private function employee(?int $id, string $role, ?int $user = null, ?string $invitationEmail = null): ?Employee
    {
        if (! $id) {
            if (in_array($role, ['employee', 'supervisor'], true)) {
                throw ValidationException::withMessages(['employee_id' => 'Role Pegawai/Atasan wajib dihubungkan ke pegawai.']);
            }

            return null;
        }
        $e = Employee::whereKey($id)->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
        if (! $e) {
            throw ValidationException::withMessages(['employee_id' => 'Pilih pegawai aktif yang belum diarsipkan.']);
        }
        if (User::where('employee_id', $id)->when($user, fn ($q) => $q->where('id', '!=', $user))->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Pegawai sudah terhubung ke akun lain.']);
        }
        $pending = DB::table('account_invitations')->where('employee_id', $id)->whereNull('used_at')->where('expires_at', '>', now());
        if ($invitationEmail) {
            $pending->where('email', '!=', $invitationEmail);
        }
        if ($pending->exists()) {
            throw ValidationException::withMessages(['employee_id' => 'Pegawai sudah memiliki undangan aktif. Cabut atau tunggu undangan berakhir.']);
        }

        return $e;
    }

    public function invite(array $d, ?User $actor = null): string
    {
        return DB::transaction(function () use ($d, $actor) {
            $this->lock($actor);
            $email = Str::lower(trim($d['email']));
            if (User::where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Akun sudah tersedia. Gunakan pengaturan akun atau pemulihan password.']);
            }
            $e = $this->employee(empty($d['employee_id']) ? null : (int) $d['employee_id'], $d['role'], null, $email);
            $old = DB::table('account_invitations')->where('email', $email)->lockForUpdate()->first();
            if ($actor && $old && ! $old->used_at && now()->lt($old->expires_at)) {
                throw ValidationException::withMessages(['email' => 'Undangan masih aktif. Cabut undangan tersebut sebelum membuat pengganti.']);
            }
            $token = Str::random(64);
            $values = ['role' => $d['role'], 'employee_id' => $e?->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'used_at' => null, 'version' => $old ? $old->version + 1 : 1, 'updated_at' => now()];
            if ($old) {
                DB::table('account_invitations')->where('id', $old->id)->update($values);
                $id = $old->id;
            } else {
                $id = DB::table('account_invitations')->insertGetId([...$values, 'email' => $email, 'created_at' => now()]);
            }
            $this->audit($actor, 'invitation_created', ['email' => $email, 'role' => $d['role'], 'reason' => $d['reason'] ?? null], null, $e?->id, $id);

            return url('/register').'?token='.$token;
        });
    }

    public function activate(array $d): User
    {
        return DB::transaction(function () use ($d) {
            $this->lock();
            $i = DB::table('account_invitations')->where('token_hash', hash('sha256', $d['token']))->lockForUpdate()->first();
            if (! $i || $i->used_at || now()->gte($i->expires_at) || ! array_key_exists($i->role, User::ROLES) || User::where('email', $i->email)->exists()) {
                throw ValidationException::withMessages(['token' => 'Undangan tidak valid, sudah digunakan, atau kedaluwarsa. Minta undangan baru kepada pengelola.']);
            }
            try {
                $e = $this->employee($i->employee_id, $i->role, null, $i->email);
            } catch (ValidationException) {
                throw ValidationException::withMessages(['token' => 'Pegawai pada undangan tidak tersedia. Hubungi Admin untuk memperbarui undangan.']);
            }
            $u = new User;
            $u->name = $d['name'];
            $u->email = $i->email;
            $u->password = $d['password'];
            $u->role = $i->role;
            $u->employee_id = $e?->id;
            $u->save();
            DB::table('account_invitations')->where('id', $i->id)->update(['used_at' => now(), 'version' => $i->version + 1, 'updated_at' => now()]);
            $this->audit($u, 'account_activated', ['role' => $u->role], $u->id, $e?->id, $i->id);

            return $u;
        });
    }

    public function revoke(int $id, array $d, User $actor): void
    {
        DB::transaction(function () use ($id, $d, $actor) {
            $this->lock($actor);
            $i = DB::table('account_invitations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($i, 404);
            if ($i->version !== (int) $d['version']) {
                throw ValidationException::withMessages(['version' => 'Undangan sudah berubah. Muat ulang halaman.']);
            }
            if ($i->used_at) {
                throw ValidationException::withMessages(['invitation' => 'Undangan sudah digunakan. Nonaktifkan akun melalui pengaturan akun.']);
            }
            DB::table('account_invitations')->where('id', $id)->update(['expires_at' => now(), 'version' => $i->version + 1, 'updated_at' => now()]);
            $this->audit($actor, 'invitation_revoked', ['reason' => $d['reason']], null, $i->employee_id, $id);
        });
    }

    public function update(User $account, array $d, User $actor): User
    {
        return DB::transaction(function () use ($account, $d, $actor) {
            $this->lock($actor);
            $u = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($u->version !== (int) $d['version']) {
                throw ValidationException::withMessages(['version' => 'Akun sudah berubah. Muat ulang halaman.']);
            }
            if ($u->id === $actor->id && ($d['role'] !== 'admin' || ! $d['is_active'])) {
                throw ValidationException::withMessages(['account' => 'Anda tidak dapat menonaktifkan atau menurunkan role akun sendiri.']);
            }
            if ($u->role === 'admin' && $u->is_active && ($d['role'] !== 'admin' || ! $d['is_active']) && User::where('role', 'admin')->where('is_active', true)->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'Minimal satu Admin aktif harus tersedia.']);
            }
            $before = $u->only('role', 'is_active', 'employee_id');
            $requested = empty($d['employee_id']) ? null : (int) $d['employee_id'];
            // A disabled account may retain an archived/inactive employee for historical linkage.
            if (! $d['is_active'] && $requested === $u->employee_id) {
                $employeeId = $requested;
            } else {
                $employeeId = $this->employee($requested, $d['role'], $u->id)?->id;
            }
            $u->role = $d['role'];
            $u->is_active = (bool) $d['is_active'];
            $u->employee_id = $employeeId;
            $u->version++;
            $u->auth_version++;
            $u->remember_token = Str::random(60);
            $u->save();
            DB::table('password_reset_tokens')->where('email', $u->email)->delete();
            $this->audit($actor, 'account_updated', ['before' => $before, 'after' => $u->only('role', 'is_active', 'employee_id'), 'reason' => $d['reason']], $u->id, $u->employee_id);

            return $u;
        });
    }

    public function resetLink(User $account, array $d, User $actor): string
    {
        return DB::transaction(function () use ($account, $d, $actor) {
            $this->lock($actor);
            $u = User::whereKey($account->id)->lockForUpdate()->firstOrFail();
            if ($u->version !== (int) $d['version']) {
                throw ValidationException::withMessages(['version' => 'Akun sudah berubah. Muat ulang halaman.']);
            }
            if (! $u->is_active) {
                throw ValidationException::withMessages(['account' => 'Aktifkan akun sebelum membuat tautan pemulihan.']);
            }
            $token = Password::broker()->createToken($u);
            $u->version++;
            $u->save();
            $this->audit($actor, 'reset_requested', ['reason' => $d['reason']], $u->id, $u->employee_id);

            return url('/reset-password/'.$token).'?'.http_build_query(['email' => $u->email]);
        });
    }

    public function resetValid(string $email, string $token): bool
    {
        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        return strlen($token) === 64 && $row && CarbonImmutable::parse($row->created_at, 'UTC')->addMinutes((int) config('auth.passwords.users.expire', 60))->isFuture() && Hash::check($token, $row->token) && User::where('email', $email)->where('is_active', true)->exists();
    }

    public function resetPassword(array $d): void
    {
        DB::transaction(function () use ($d) {
            $this->lock();
            $u = User::where('email', $d['email'])->lockForUpdate()->first();
            $row = DB::table('password_reset_tokens')->where('email', $d['email'])->lockForUpdate()->first();
            if (! $u || ! $u->is_active || ! $row || ! $this->resetValid($d['email'], $d['token'])) {
                throw ValidationException::withMessages(['token' => 'Tautan pemulihan tidak valid atau sudah kedaluwarsa. Minta tautan baru kepada Admin.']);
            }
            $u->password = $d['password'];
            $u->remember_token = Str::random(60);
            $u->auth_version++;
            $u->version++;
            $u->save();
            DB::table('password_reset_tokens')->where('email', $u->email)->delete();
            $this->audit($u, 'password_reset', [], $u->id, $u->employee_id);
        });
    }

    public function reporting(array $d, User $actor): void
    {
        DB::transaction(function () use ($d, $actor) {
            $this->lock($actor);
            $e = Employee::whereKey($d['employee_id'])->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
            if (! $e) {
                throw ValidationException::withMessages(['employee_id' => 'Pegawai tidak tersedia.']);
            }
            if ($e->version !== (int) $d['version']) {
                throw ValidationException::withMessages(['version' => 'Data pegawai sudah berubah. Muat ulang halaman.']);
            }
            $id = empty($d['manager_employee_id']) ? null : (int) $d['manager_employee_id'];
            if ($id) {
                $manager = Employee::whereKey($id)->where('employment_status', '!=', 'inactive')->lockForUpdate()->first();
                if (! $manager || ! User::where('employee_id', $id)->where('role', 'supervisor')->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['manager_employee_id' => 'Pilih atasan dengan akun Atasan aktif.']);
                }
                $cursor = $manager;
                $seen = [$e->id => true];
                while ($cursor) {
                    if (isset($seen[$cursor->id])) {
                        throw ValidationException::withMessages(['manager_employee_id' => 'Relasi atasan tidak boleh membentuk lingkaran.']);
                    }
                    $seen[$cursor->id] = true;
                    $cursor = $cursor->manager_employee_id ? Employee::withTrashed()->find($cursor->manager_employee_id) : null;
                }
            }
            $before = $e->manager_employee_id;
            $e->manager_employee_id = $id;
            $e->version++;
            $e->save();
            $this->audit($actor, 'manager_updated', ['before' => $before, 'after' => $id, 'reason' => $d['reason']], null, $e->id);
        });
    }
}
