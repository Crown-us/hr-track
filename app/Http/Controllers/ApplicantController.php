<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicantRequest;
use App\Models\Applicant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantController
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Applicant::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $canSeeContact = $request->user()->can('create', Applicant::class);
        $query = Applicant::query()->withCount('applications');
        if ($q = trim($filters['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$q.'%')
                ->when($canSeeContact, fn ($query) => $query->orWhere('email', 'like', '%'.$q.'%')));
        }

        $applicants = $query->orderBy('full_name')->paginate(15)->withQueryString()
            ->through(fn (Applicant $a) => $a->forDisplay($canSeeContact));

        return Inertia::render('applicants', [
            'mode' => 'index',
            'applicants' => $applicants,
            'filters' => $filters,
            'canCreate' => $request->user()->can('create', Applicant::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Applicant::class);

        return Inertia::render('applicants', ['mode' => 'create', 'applicant' => null]);
    }

    public function store(ApplicantRequest $request)
    {
        $applicant = Applicant::create($request->validated());

        return to_route('applicants.show', $applicant)->with('success', 'Profil pelamar berhasil ditambahkan.');
    }

    public function show(Request $request, Applicant $applicant): Response
    {
        Gate::authorize('view', $applicant);

        $private = $request->user()->can('update', $applicant);
        $applicant->load(['applications.vacancy.department', 'documents']);

        return Inertia::render('applicants', [
            'mode' => 'show',
            'applicant' => [
                ...$applicant->forDisplay($private),
                'applications' => $applicant->applications->map(fn ($app) => [
                    'id' => $app->id,
                    'vacancy_id' => $app->vacancy_id,
                    'job_title' => $app->vacancy->job_title,
                    'department' => $app->vacancy->department?->name ?? '—',
                    'status' => $app->status->value,
                    'status_label' => $app->status->label(),
                    'application_date' => $app->application_date->format('Y-m-d'),
                ]),
                'documents' => $private ? $applicant->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'size_bytes' => $d->size_bytes,
                    'created_at' => $d->created_at->toIso8601String(),
                    'download_url' => ($applicationId = $d->application_id ?? $applicant->applications->first()?->id) && $applicant->applications->contains('id', $applicationId)
                        ? route('applications.document', ['application' => $applicationId, 'document' => $d->id]) : null,
                ]) : [],
            ],
            'canManage' => $private,
        ]);
    }
}
