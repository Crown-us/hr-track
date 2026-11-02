<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Vacancy;
use App\Services\ApplicationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ApplicationController
{
    private function base(Request $request): array
    {
        return ['canManage' => $request->user()->can('create', Application::class), 'stages' => array_map(fn ($status) => ['value' => $status->value, 'label' => $status->label()], ApplicationStatus::cases())];
    }

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Application::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'vacancy_id' => ['nullable', 'integer', 'exists:vacancies,id'], 'status' => ['nullable', Rule::enum(ApplicationStatus::class)], 'view' => ['nullable', Rule::in(['board', 'list'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = Application::whereHas('applicant')->with(['applicant', 'vacancy']);
        if ($q = trim($filters['q'] ?? '')) {
            $query->where(fn ($query) => $query->whereHas('applicant', fn ($query) => $query->where('full_name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))->orWhereHas('vacancy', fn ($query) => $query->where('job_title', 'like', '%'.$q.'%')));
        }
        if (! empty($filters['vacancy_id'])) {
            $query->where('vacancy_id', $filters['vacancy_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $counts = array_fill_keys(array_column(ApplicationStatus::cases(), 'value'), 0);
        foreach ((clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get() as $group) {
            $counts[$group->status->value] = (int) $group->total;
        }

        return Inertia::render('applications', [...$this->base($request), 'mode' => 'index', 'filters' => $filters, 'counts' => $counts,
            'vacancies' => Vacancy::orderBy('job_title')->get(['id', 'job_title']),
            'applications' => $query->latest('id')->paginate(15)->withQueryString()->through(fn ($application) => $application->forDisplay())]);
    }

    public function create(Request $request)
    {
        Gate::authorize('create', Application::class);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'mode' => ['nullable', Rule::in(['existing'])], 'applicant_id' => ['nullable', 'integer', Rule::exists('applicants', 'id')->whereNull('deleted_at')]]);
        $query = Applicant::query();
        if ($q = trim($data['q'] ?? '')) {
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'));
        }

        return Inertia::render('applications', [...$this->base($request), 'mode' => 'create', 'today' => now('Asia/Jakarta')->toDateString(),
            'vacancies' => Vacancy::open()->orderBy('job_title')->get(['id', 'job_title']),
            'applicants' => $query->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'email']),
            'existingMode' => isset($data['applicant_id']) || ($data['mode'] ?? '') === 'existing',
            'applicantSearch' => $data['q'] ?? '', 'selectedApplicant' => isset($data['applicant_id']) ? Applicant::findOrFail($data['applicant_id'])->only('id', 'full_name', 'email') : null]);
    }

    public function store(ApplicationRequest $request, ApplicationWorkflow $workflow)
    {
        $application = $workflow->create($request->validated(), $request->file('cv'), $request->user());

        return to_route('applications.show', $application)->with('success', 'Lamaran dicatat pada tahap Screening.');
    }

    public function show(Request $request, Application $application)
    {
        Gate::authorize('view', $application);
        $private = $request->user()->can('update', $application);
        $histories = ApplicationStatusHistory::with('actor:id,name')->where('application_id', $application->id)->orderBy('id')->get()->map(fn ($history) => [
            'id' => $history->id, 'label' => ApplicationStatus::from($history->new_status)->label(), 'actor' => $history->actor->name, 'created_at' => $history->created_at->toIso8601String(), ...($private ? ['note' => $history->note] : [])]);
        $documents = $private ? ApplicantDocument::where('applicant_id', $application->applicant_id)->orderByDesc('id')->get()->map(fn ($doc) => ['id' => $doc->id, 'size_bytes' => $doc->size_bytes, 'created_at' => $doc->created_at->toIso8601String()]) : [];

        return Inertia::render('applications', [...$this->base($request), 'mode' => 'show', 'application' => $application->load(['applicant', 'vacancy'])->forDisplay($private), 'histories' => $histories, 'documents' => $documents,
            'nextStages' => array_map(fn ($status) => ['value' => $status->value, 'label' => $status->label()], $application->status->next())]);
    }

    public function transition(Request $request, Application $application, ApplicationWorkflow $workflow)
    {
        Gate::authorize('update', $application);
        $data = $request->validate(['status' => ['required', Rule::enum(ApplicationStatus::class)], 'version' => ['required', 'integer', 'min:1'], 'note' => ['nullable', 'string', 'max:1000']]);
        $workflow->transition($application, $data, $request->user());

        return to_route('applications.show', $application)->with('success', 'Tahap lamaran berhasil diperbarui.');
    }

    public function upload(Request $request, Application $application, ApplicationWorkflow $workflow)
    {
        Gate::authorize('update', $application);
        $request->validate(['cv' => ['required', ...ApplicationRequest::cvRules()]], ['cv.mimes' => 'CV harus berupa PDF.', 'cv.max' => 'CV maksimal 5 MiB.']);
        $workflow->upload($application, $request->file('cv'), $request->user());

        return to_route('applications.show', $application)->with('success', 'Versi CV berhasil ditambahkan.');
    }

    public function download(Request $request, Application $application, ApplicantDocument $document)
    {
        Gate::authorize('update', $application);
        abort_unless($document->applicant_id === $application->applicant_id, 404);
        $disk = Storage::disk('applicant_documents');
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, 'CV-'.$application->applicant_id.'-'.$document->id.'.pdf', ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store, private']);
    }
}
