<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::orderBy('name')->paginate(15)->withQueryString()
            ->through(fn (Department $d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name]);

        return Inertia::render('departments', [
            'mode' => 'index',
            'departments' => $departments,
            'canCreate' => $request->user()->can('create', Department::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Department::class);

        return Inertia::render('departments', ['mode' => 'create', 'department' => null]);
    }

    public function store(DepartmentRequest $request)
    {
        Department::create($request->validated());

        return to_route('departments.index')->with('success', 'Departemen berhasil ditambahkan.');
    }

    public function edit(Request $request, Department $department): Response
    {
        Gate::authorize('update', $department);

        return Inertia::render('departments', [
            'mode' => 'edit',
            'department' => ['id' => $department->id, 'code' => $department->code, 'name' => $department->name],
        ]);
    }

    public function update(DepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());

        return to_route('departments.index')->with('success', 'Departemen berhasil diubah.');
    }
}
