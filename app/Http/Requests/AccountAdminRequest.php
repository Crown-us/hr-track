<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-accounts');
    }

    protected function getRedirectUrl(): string
    {
        if ($this->route('account')) {
            return route('accounts.show', $this->route('account'));
        }
        if ($this->routeIs('accounts.reporting.save')) {
            return route('accounts.reporting');
        }

        return route('accounts.index');
    }

    public function rules(): array
    {
        $base = ['current_password' => ['required', 'string', 'current_password:web'], 'reason' => [$this->routeIs('accounts.invite') ? 'nullable' : 'required', 'string', 'min:10', 'max:1000']];
        if ($this->routeIs('accounts.invite')) {
            return [...$base, 'email' => ['required', 'email', 'max:255'], 'role' => ['required', Rule::in(array_keys(User::ROLES))], 'employee_id' => ['required_if:role,employee,supervisor', 'nullable', 'integer', Rule::exists('employees', 'id')]];
        }
        if ($this->routeIs('accounts.update')) {
            return [...$base, 'version' => ['required', 'integer', 'min:1'], 'role' => ['required', Rule::in(array_keys(User::ROLES))], 'is_active' => ['required', 'boolean'], 'employee_id' => ['required_if:role,employee,supervisor', 'nullable', 'integer', Rule::exists('employees', 'id')]];
        }
        if ($this->routeIs('accounts.reporting.save')) {
            return [...$base, 'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')], 'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')], 'version' => ['required', 'integer', 'min:1']];
        }

        return [...$base, 'version' => ['required', 'integer', 'min:1']];
    }

    public function messages(): array
    {
        return ['current_password.required' => 'Konfirmasikan kata sandi Admin.', 'current_password.current_password' => 'Kata sandi Admin tidak cocok.', 'reason.required' => 'Isi alasan perubahan.', 'reason.min' => 'Alasan minimal 10 karakter.', 'employee_id.required_if' => 'Pilih pegawai untuk role ini.'];
    }

    public function attributes(): array
    {
        return ['employee_id' => 'Pegawai', 'manager_employee_id' => 'Atasan', 'email' => 'Email', 'role' => 'Role'];
    }
}
