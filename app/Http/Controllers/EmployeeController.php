<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Http\Requests\EmployeeArchiveRequest;
use App\Http\Requests\EmployeeRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeEvent;
use App\Models\Position;
use App\Services\EmployeeRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Employee::class);
        $canSeeContact = $request->user()->can('create', Employee::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::enum(EmploymentStatus::class)], 'archived' => ['sometimes', 'boolean'],
            'sort' => ['nullable', Rule::in(['name_asc', 'name_desc', 'joined_desc'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        if ($request->boolean('archived')) {
            Gate::authorize('viewEvidence', Employee::class);
        }
        $query = Employee::with(['department', 'position']);
        if ($request->boolean('archived')) {
            $query->onlyTrashed();
        }
        if ($q = trim($filters['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$q.'%')->orWhere('employee_number', 'like', '%'.$q.'%')
                ->when($canSeeContact, fn ($query) => $query->orWhere('work_email', 'like', '%'.$q.'%')));
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('employment_status', $filters['status']);
        }
        [$column, $direction] = match ($filters['sort'] ?? 'name_asc') {
            'name_desc' => ['full_name', 'desc'], 'joined_desc' => ['join_date', 'desc'], default => ['full_name', 'asc']
        };
        $employees = $query->orderBy($column, $direction)->orderBy('id')->paginate(15)->withQueryString()->through(fn (Employee $employee) => $employee->forDisplay($canSeeContact));

        return Inertia::render('employees', [...$this->base($request), 'mode' => 'index', 'filters' => [...$filters, 'archived' => $request->boolean('archived')], 'employees' => $employees, 'employee' => null, 'events' => []]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Employee::class);

        return $this->page($request, 'create');
    }

    public function show(Request $request, Employee $employee): Response
    {
        Gate::authorize('view', $employee);

        return $this->page($request, 'show', $employee);
    }

    public function edit(Request $request, Employee $employee): Response
    {
        Gate::authorize('update', $employee);

        return $this->page($request, 'edit', $employee);
    }

    public function archiveForm(Request $request, Employee $employee): Response
    {
        Gate::authorize('delete', $employee);

        return $this->page($request, 'archive', $employee);
    }

    public function store(EmployeeRequest $request, EmployeeRecords $records)
    {
        $employee = $records->save(null, $request->validated(), $request->user());

        return to_route('employees.show', $employee)->with('success', 'Pegawai berhasil ditambahkan.');
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeRecords $records)
    {
        $records->save($employee, $request->validated(), $request->user());

        return to_route('employees.show', $employee)->with('success', 'Perubahan pegawai berhasil disimpan.');
    }

    public function archive(EmployeeArchiveRequest $request, Employee $employee, EmployeeRecords $records)
    {
        $records->archive($employee, $request->validated(), $request->file('document'), $request->user());

        return to_route('employees.index')->with('success', 'Pegawai diarsipkan. Riwayat dan berkas pendukung tetap tersimpan.');
    }

    public function restore(EmployeeArchiveRequest $request, Employee $employee, EmployeeRecords $records)
    {
        $records->restore($employee, $request->validated(), $request->user());

        return to_route('employees.show', $employee)->with('success', 'Pegawai berhasil dipulihkan ke direktori aktif.');
    }

    public function document(Employee $employee, EmployeeEvent $event)
    {
        Gate::authorize('viewEvidence', Employee::class);
        abort_unless($event->employee_id === $employee->id && $event->document_path, 404);
        $disk = Storage::disk('employee_records');
        abort_unless($disk->exists($event->document_path), 404);

        return $disk->download($event->document_path, 'bukti-pengarsipan-'.$employee->id.'-'.$event->id.'.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }

    private function base(Request $request): array
    {
        return ['departments' => Department::orderBy('name')->get(['id', 'name']), 'positions' => Position::orderBy('name')->get(['id', 'department_id', 'name']),
            'statuses' => array_map(fn ($status) => ['value' => $status->value, 'label' => $status->label()], EmploymentStatus::cases()),
            'can' => ['create' => $request->user()->can('create', Employee::class), 'admin' => $request->user()->role === 'admin']];
    }

    private function page(Request $request, string $mode, ?Employee $employee = null): Response
    {
        $events = $employee && $request->user()->role === 'admin' ? $employee->events()->with('actor:id,name')->orderByDesc('id')->get()
            ->map(fn ($event) => ['id' => $event->id, 'action' => $event->action, 'reason' => $event->reason, 'actor' => $event->actor->name,
                'created_at' => $event->created_at->toIso8601String(), 'has_document' => (bool) $event->document_path, 'changes' => $event->changes]) : [];

        return Inertia::render('employees', [...$this->base($request), 'mode' => $mode, 'employee' => $employee?->load(['department', 'position'])->forDisplay($request->user()->can('create', Employee::class)), 'events' => $events,
            'needsContract' => $mode === 'show' && $employee && Employee::withoutCurrentContract()->whereKey($employee->id)->exists()]);
    }
}
