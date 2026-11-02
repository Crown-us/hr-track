<?php

namespace App\Http\Controllers;

use App\Http\Requests\PositionRequest;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PositionController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Position::class);

        $positions = Position::with('department')->orderBy('name')->paginate(15)->withQueryString()
            ->through(fn (Position $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'department' => $p->department->name,
                'department_id' => $p->department_id,
            ]);

        return Inertia::render('positions', [
            'mode' => 'index',
            'positions' => $positions,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'canCreate' => $request->user()->can('create', Position::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Position::class);

        return Inertia::render('positions', [
            'mode' => 'create',
            'position' => null,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(PositionRequest $request)
    {
        Position::create($request->validated());

        return to_route('positions.index')->with('success', 'Jabatan berhasil ditambahkan.');
    }

    public function edit(Request $request, Position $position): Response
    {
        Gate::authorize('update', $position);

        return Inertia::render('positions', [
            'mode' => 'edit',
            'position' => [
                'id' => $position->id,
                'department_id' => $position->department_id,
                'name' => $position->name,
            ],
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(PositionRequest $request, Position $position)
    {
        try {
            $position->update($request->validated());
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1451) {
                throw ValidationException::withMessages(['department_id' => 'Jabatan ini sudah digunakan pegawai atau penawaran. Buat jabatan baru untuk departemen lain.']);
            }
            throw $e;
        }

        return to_route('positions.index')->with('success', 'Jabatan berhasil diubah.');
    }
}
