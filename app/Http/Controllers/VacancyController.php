<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentType;
use App\Enums\VacancyStatus;
use App\Http\Requests\VacancyRequest;
use App\Models\Department;
use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VacancyController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vacancy::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'status' => ['nullable', Rule::enum(VacancyStatus::class)], 'employment_type' => ['nullable', Rule::enum(EmploymentType::class)], 'sort' => ['nullable', Rule::in(['latest', 'title', 'closing'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = Vacancy::with('department');
        if ($q = trim($filters['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('job_title', 'like', '%'.$q.'%')->orWhere('location', 'like', '%'.$q.'%'));
        }
        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }
        [$column, $direction] = match ($filters['sort'] ?? 'latest') {
            'title' => ['job_title', 'asc'], 'closing' => ['closing_date', 'asc'], default => ['created_at', 'desc']
        };
        $vacancies = $query->orderBy($column, $direction)->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($vacancy) => $vacancy->forDisplay());

        return Inertia::render('vacancies', [...$this->base($request), 'mode' => 'index', 'filters' => $filters, 'vacancies' => $vacancies, 'vacancy' => null]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Vacancy::class);

        return $this->page($request, 'create');
    }

    public function show(Request $request, Vacancy $vacancy): Response
    {
        Gate::authorize('view', $vacancy);

        return $this->page($request, 'show', $vacancy);
    }

    public function edit(Request $request, Vacancy $vacancy): Response
    {
        Gate::authorize('update', $vacancy);

        return $this->page($request, 'edit', $vacancy);
    }

    public function store(VacancyRequest $request)
    {
        $vacancy = Vacancy::create($request->validated());

        return to_route('vacancies.show', $vacancy)->with('success', 'Lowongan berhasil ditambahkan.');
    }

    public function update(VacancyRequest $request, Vacancy $vacancy)
    {
        DB::transaction(function () use ($request, $vacancy) {
            $record = Vacancy::whereKey($vacancy->id)->lockForUpdate()->firstOrFail();
            if ($record->version !== $request->integer('version')) {
                throw ValidationException::withMessages(['version' => 'Lowongan sudah diubah oleh pengguna lain. Muat ulang formulir sebelum melanjutkan.']);
            }
            $record->fill($request->validated());
            $record->version++;
            $record->save();
        });

        return to_route('vacancies.show', $vacancy)->with('success', 'Perubahan lowongan berhasil disimpan.');
    }

    private function base(Request $request): array
    {
        return ['today' => now('Asia/Jakarta')->toDateString(), 'departments' => Department::orderBy('name')->get(['id', 'name']), 'canManage' => $request->user()->can('create', Vacancy::class),
            'types' => array_map(fn ($type) => ['value' => $type->value, 'label' => $type->label()], EmploymentType::cases()),
            'statuses' => array_map(fn ($status) => ['value' => $status->value, 'label' => $status->label()], VacancyStatus::cases())];
    }

    private function page(Request $request, string $mode, ?Vacancy $vacancy = null): Response
    {
        return Inertia::render('vacancies', [...$this->base($request), 'mode' => $mode, 'vacancy' => $vacancy?->load('department')->forDisplay(true)]);
    }
}
