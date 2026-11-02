<?php

namespace App\Http\Requests;

use App\Models\Employee;

class ApplicantConversionRequest extends EmployeeRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class) && $this->user()->can('update', $this->route('application'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        foreach (['department_id', 'position_id', 'employment_status', 'join_date'] as $key) {
            unset($rules[$key]);
        }

        return [...$rules, 'version' => ['required', 'integer', 'min:1'], 'identity_confirmed' => ['accepted']];
    }
}
