<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Http\Requests\ContractActionRequest;
use App\Http\Requests\ContractRequest;
use App\Models\ContractDocument;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Services\ContractRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ContractController
{
    private function run(callable $action, string $redirect): mixed
    {
        try {
            return $action();
        } catch (ValidationException $e) {
            throw $e->redirectTo($redirect);
        }
    }

    public function index(Request $r)
    {
        Gate::authorize('viewAny', EmployeeContract::class);
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'attention' => ['nullable', Rule::in(['due', 'expired'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = EmployeeContract::with('employee');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('contract_number', 'like', '%'.$term.'%')->orWhereHas('employee', fn ($e) => $e->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%')));
        }
        if (! empty($f['attention'])) {
            $q->attention($f['attention']);
        }

        return Inertia::render('contracts', ['mode' => 'index', 'canManage' => $r->user()->can('create', EmployeeContract::class), 'filters' => $f, 'missingCount' => Employee::withoutCurrentContract()->count(),
            'contracts' => $q->orderByRaw('ends_on IS NULL')->orderBy('ends_on')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($c) => $c->forDisplay())]);
    }

    public function missing(Request $r)
    {
        Gate::authorize('viewAny', EmployeeContract::class);
        $f = $r->validate(['status' => ['nullable', Rule::in(['contract', 'probation', 'intern', 'permanent'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = Employee::withoutCurrentContract()->with('department')->when(! empty($f['status']), fn ($e) => $e->where('employment_status', $f['status']));

        return Inertia::render('contracts', ['mode' => 'missing', 'canManage' => $r->user()->can('create', EmployeeContract::class), 'filters' => $f,
            'missing' => $q->orderByRaw("FIELD(employment_status, 'contract', 'probation', 'intern', 'permanent')")->orderBy('join_date')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($e) => [
                ...$e->only('id', 'employee_number', 'full_name'), 'status' => $e->employment_status->value, 'status_label' => $e->employment_status->label(),
                'department' => $e->department->name, 'join_date' => $e->join_date->format('Y-m-d')])]);
    }

    public function create(Request $r)
    {
        Gate::authorize('create', EmployeeContract::class);
        $f = $r->validate(['employee_q' => ['nullable', 'string', 'max:100'], 'employee_id' => ['nullable', 'integer'], 'starts_on' => ['nullable', 'date_format:Y-m-d']]);
        $q = Employee::where('employment_status', '!=', 'inactive');
        if ($term = trim($f['employee_q'] ?? '')) {
            $q->where(fn ($e) => $e->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }
        $employees = $q->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'employee_number'])->map->only(['id', 'full_name', 'employee_number']);
        $selected = isset($f['employee_id']) ? Employee::where('employment_status', '!=', 'inactive')->find($f['employee_id']) : null;
        if ($selected && ! $employees->contains('id', $selected->id)) {
            $employees->prepend($selected->only(['id', 'full_name', 'employee_number']));
        }
        $lastEnd = $selected ? EmployeeContract::where('employee_id', $selected->id)->whereNull('voided_at')->where('ends_on', '<', now('Asia/Jakarta')->toDateString())->max('ends_on') : null;

        return Inertia::render('contracts', ['mode' => 'create', 'canManage' => true, 'filters' => $f, 'employees' => $employees->values(),
            'selectedId' => $selected?->id, 'defaultStart' => $lastEnd ? CarbonImmutable::parse($lastEnd)->addDay()->format('Y-m-d') : ($selected ? ($f['starts_on'] ?? null) : null),
            'defaultType' => $selected?->employment_status === EmploymentStatus::Permanent ? 'pkwtt' : 'pkwt']);
    }

    public function store(ContractRequest $r, ContractRecords $records)
    {
        $c = $this->run(fn () => $records->save(null, $r->validated(), $r->user()), route('contracts.create'));

        return to_route('contracts.show', $c)->with('success', 'Kontrak tersimpan. Unggah berkas pendukung pada detail kontrak.');
    }

    public function show(Request $r, EmployeeContract $contract)
    {
        Gate::authorize('view', $contract);
        $private = $r->user()->can('update', $contract);

        return Inertia::render('contracts', ['mode' => 'show', 'canManage' => $private, 'contract' => $contract->load('employee')->forDisplay($private),
            'documents' => $private ? ContractDocument::where('employee_contract_id', $contract->id)->orderByDesc('version')->get()->map(fn ($d) => [...$d->only('id', 'version', 'size_bytes'), 'created_at' => $d->created_at->toIso8601String()]) : [],
            'events' => $private ? DB::table('contract_events')->join('users', 'users.id', '=', 'contract_events.actor_id')->where('employee_contract_id', $contract->id)->orderByDesc('contract_events.id')->limit(30)->get(['contract_events.id', 'action', 'changes', 'users.name as actor', 'contract_events.created_at'])->map(fn ($e) => ['id' => $e->id, 'action' => $e->action, 'reason' => json_decode($e->changes, true)['reason'] ?? null, 'actor' => $e->actor, 'created_at' => CarbonImmutable::parse($e->created_at, 'UTC')->toIso8601String()]) : []]);
    }

    public function edit(EmployeeContract $contract)
    {
        Gate::authorize('update', $contract);

        return Inertia::render('contracts', ['mode' => 'edit', 'canManage' => true, 'contract' => $contract->load('employee')->forDisplay(true)]);
    }

    public function update(ContractRequest $r, EmployeeContract $contract, ContractRecords $records)
    {
        $this->run(fn () => $records->save($contract, $r->validated(), $r->user()), route('contracts.edit', $contract));

        return to_route('contracts.show', $contract)->with('success', 'Koreksi kontrak tersimpan beserta alasannya.');
    }

    public function void(ContractActionRequest $r, EmployeeContract $contract, ContractRecords $records)
    {
        Gate::authorize('update', $contract);
        $this->run(fn () => $records->void($contract, $r->validated(), $r->user()), route('contracts.show', $contract));

        return to_route('contracts.show', $contract)->with('success', 'Catatan kontrak dibatalkan. Seluruh riwayat tetap tersimpan.');
    }

    public function upload(ContractActionRequest $r, EmployeeContract $contract, ContractRecords $records)
    {
        Gate::authorize('update', $contract);
        $this->run(fn () => $records->upload($contract, $r->file('document'), $r->user()), route('contracts.show', $contract));

        return to_route('contracts.show', $contract)->with('success', 'Versi berkas kontrak tersimpan.');
    }

    public function download(EmployeeContract $contract, ContractDocument $document)
    {
        Gate::authorize('update', $contract);
        abort_unless($document->employee_contract_id === $contract->id, 404);
        $disk = Storage::disk('contract_documents');
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, 'kontrak-'.$contract->id.'-v'.$document->version.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
