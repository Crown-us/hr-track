<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountAdminRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\AccountAdministration;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountController
{
    private function run(callable $fn, string $redirect): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e->redirectTo($redirect);
        }
    }

    private function user(User $u): array
    {
        return [...$u->only('id', 'name', 'email', 'role', 'is_active', 'employee_id', 'version'), 'employee_name' => $u->employee?->full_name, 'employee_number' => $u->employee?->employee_number];
    }

    private function employees(Request $r, ?User $account = null): array
    {
        $q = Employee::where('employment_status', '!=', 'inactive')->whereNotIn('id', User::whereNotNull('employee_id')->when($account, fn ($q) => $q->where('id', '!=', $account->id))->select('employee_id'));
        if ($term = trim((string) $r->query('employee_q', ''))) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }
        $rows = $q->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'employee_number']);
        if ($account?->employee_id && ! $rows->contains('id', $account->employee_id)) {
            $rows->push(Employee::withTrashed()->findOrFail($account->employee_id)->only('id', 'full_name', 'employee_number'));
        }

        return $rows->toArray();
    }

    public function index(Request $r)
    {
        Gate::authorize('manage-accounts');
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'role' => ['nullable', Rule::in(array_keys(User::ROLES))], 'page' => ['sometimes', 'integer', 'min:1'], 'employee_q' => ['nullable', 'string', 'max:100'], 'invitations_page' => ['sometimes', 'integer', 'min:1']]);
        $q = User::with('employee');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }
        if (! empty($f['role'])) {
            $q->where('role', $f['role']);
        }

        return Inertia::render('accounts', ['mode' => 'index', 'roles' => User::ROLES, 'filters' => $f, 'accounts' => $q->orderBy('name')->paginate(15)->withQueryString()->through(fn ($u) => $this->user($u)), 'employees' => $this->employees($r),
            'accessLink' => $r->session()->get('account_access_link'),
            'invitations' => DB::table('account_invitations')->leftJoin('employees', 'employees.id', '=', 'account_invitations.employee_id')->orderByDesc('account_invitations.updated_at')->paginate(15, ['account_invitations.id', 'email', 'role', 'account_invitations.version', 'used_at', 'expires_at', 'employees.full_name as employee_name'], 'invitations_page')->withQueryString()->through(fn ($i) => [...collect($i)->except(['used_at', 'expires_at'])->all(), 'status' => $i->used_at ? 'used' : (now()->gte($i->expires_at) ? 'expired' : 'pending'), 'expires_at' => CarbonImmutable::parse($i->expires_at, 'UTC')->toIso8601String()])]);
    }

    public function show(Request $r, User $account)
    {
        Gate::authorize('manage-accounts');
        $r->validate(['employee_q' => ['nullable', 'string', 'max:100']]);

        return Inertia::render('accounts', ['mode' => 'show', 'roles' => User::ROLES, 'account' => $this->user($account->load('employee')), 'employees' => $this->employees($r, $account), 'accessLink' => $r->session()->get('account_access_link'),
            'events' => DB::table('account_events')->leftJoin('users', 'users.id', '=', 'account_events.actor_id')->where('target_user_id', $account->id)->orderByDesc('account_events.id')->limit(30)->get(['account_events.id', 'action', 'changes', 'users.name as actor', 'account_events.created_at'])->map(fn ($e) => ['id' => $e->id, 'action' => $e->action, 'actor' => $e->actor ?? 'Sistem', 'reason' => json_decode($e->changes, true)['reason'] ?? null, 'created_at' => CarbonImmutable::parse($e->created_at, 'UTC')->toIso8601String()])]);
    }

    public function invite(AccountAdminRequest $r, AccountAdministration $records)
    {
        $url = $this->run(fn () => $records->invite($r->validated(), $r->user()), route('accounts.index'));

        return to_route('accounts.index')->with('account_access_link', $url)->with('success', 'Undangan dibuat. Salurkan tautan secara privat kepada pemilik akun.');
    }

    public function revoke(AccountAdminRequest $r, int $invitation, AccountAdministration $records)
    {
        $this->run(fn () => $records->revoke($invitation, $r->validated(), $r->user()), route('accounts.index'));

        return to_route('accounts.index')->with('success', 'Undangan dicabut.');
    }

    public function update(AccountAdminRequest $r, User $account, AccountAdministration $records)
    {
        $u = $this->run(fn () => $records->update($account, $r->validated(), $r->user()), route('accounts.show', $account));
        if ($u->id === $r->user()->id) {
            $r->session()->put('account_auth_version', $u->auth_version);
        }

        return to_route('accounts.show', $u)->with('success', 'Akses akun diperbarui. Sesi lama pemilik akun akan diminta masuk kembali.');
    }

    public function resetLink(AccountAdminRequest $r, User $account, AccountAdministration $records)
    {
        $url = $this->run(fn () => $records->resetLink($account, $r->validated(), $r->user()), route('accounts.show', $account));

        return to_route('accounts.show', $account)->with('account_access_link', $url)->with('success', 'Tautan pemulihan dibuat, berlaku 60 menit. Tautan sebelumnya tidak berlaku.');
    }

    public function reporting(Request $r)
    {
        Gate::authorize('manage-accounts');
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = Employee::where('employment_status', '!=', 'inactive');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }

        return Inertia::render('accounts', ['mode' => 'reporting', 'filters' => $f, 'employeesPage' => $q->with('manager')->orderBy('full_name')->paginate(15)->withQueryString()->through(fn ($e) => [...$e->only('id', 'full_name', 'employee_number', 'version', 'manager_employee_id'), 'manager_name' => $e->manager?->full_name])]);
    }

    public function reportingEdit(Request $r, Employee $employee)
    {
        Gate::authorize('manage-accounts');
        $f = $r->validate(['manager_q' => ['nullable', 'string', 'max:100']]);
        $q = Employee::where('id', '!=', $employee->id)->where('employment_status', '!=', 'inactive')->whereIn('id', User::where('role', 'supervisor')->where('is_active', true)->whereNotNull('employee_id')->select('employee_id'));
        if ($term = trim($f['manager_q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }
        $managers = $q->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'employee_number']);
        if ($employee->manager_employee_id && ! $managers->contains('id', $employee->manager_employee_id)) {
            $managers->push(Employee::withTrashed()->findOrFail($employee->manager_employee_id)->only('id', 'full_name', 'employee_number'));
        }

        return Inertia::render('accounts', ['mode' => 'reporting-edit', 'filters' => $f, 'employee' => $employee->only('id', 'full_name', 'employee_number', 'manager_employee_id', 'version'), 'managers' => $managers]);
    }

    public function reportingSave(AccountAdminRequest $r, AccountAdministration $records)
    {
        $this->run(fn () => $records->reporting($r->validated(), $r->user()), route('accounts.reporting.edit', $r->validated('employee_id')));

        return to_route('accounts.reporting')->with('success', 'Atasan langsung diperbarui.');
    }
}
