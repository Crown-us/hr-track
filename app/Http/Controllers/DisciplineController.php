<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisciplineActionRequest;
use App\Http\Requests\DisciplineRequest;
use App\Models\DisciplinaryCase;
use App\Models\DisciplineDocument;
use App\Models\Employee;
use App\Services\DisciplineRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DisciplineController
{
    private function run(callable $fn, string $url): mixed
    {
        try {
            return $fn();
        } catch (ValidationException $e) {
            throw $e->redirectTo($url);
        }
    }

    public function index(Request $r)
    {
        Gate::authorize('viewAny', DisciplinaryCase::class);
        $f = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'kind' => ['nullable', Rule::in(array_keys(DisciplinaryCase::KINDS))], 'status' => ['nullable', Rule::in(array_keys(DisciplinaryCase::STATUSES))], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = DisciplinaryCase::with('employee');
        if ($term = trim($f['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('reference_number', 'like', '%'.$term.'%')->orWhere('subject', 'like', '%'.$term.'%')->orWhereHas('employee', fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%')));
        }
        foreach (['kind', 'status'] as $field) {
            if (! empty($f[$field])) {
                $q->where($field, $f[$field]);
            }
        }

        return Inertia::render('discipline', ['mode' => 'index', 'filters' => $f, 'cases' => $q->orderByDesc('incident_on')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($c) => array_diff_key($c->forDisplay(), ['description' => true]))]);
    }

    public function create(Request $r)
    {
        Gate::authorize('create', DisciplinaryCase::class);
        $f = $r->validate(['employee_q' => ['nullable', 'string', 'max:100']]);
        $q = Employee::where('employment_status', '!=', 'inactive');
        if ($term = trim($f['employee_q'] ?? '')) {
            $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }

        return Inertia::render('discipline', ['mode' => 'create', 'filters' => $f, 'today' => now('Asia/Jakarta')->toDateString(), 'employees' => $q->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'employee_number'])]);
    }

    public function store(DisciplineRequest $r, DisciplineRecords $records)
    {
        $case = $this->run(fn () => $records->save(null, $r->validated(), $r->file('document'), $r->user()), route('discipline.create'));

        return to_route('discipline.show', $case)->with('success', 'Catatan tersimpan beserta riwayatnya.');
    }

    public function show(DisciplinaryCase $case)
    {
        Gate::authorize('view', $case);

        return Inertia::render('discipline', ['mode' => 'show', 'case' => $case->load('employee')->forDisplay(),
            'documents' => DisciplineDocument::where('disciplinary_case_id', $case->id)->orderByDesc('version')->get()->map(fn ($d) => [...$d->only('id', 'version', 'size_bytes'), 'created_at' => $d->created_at->toIso8601String()]),
            'events' => DB::table('discipline_events')->join('users', 'users.id', '=', 'discipline_events.actor_id')->where('disciplinary_case_id', $case->id)->orderByDesc('discipline_events.id')->limit(30)->get(['discipline_events.id', 'action', 'changes', 'users.name as actor', 'discipline_events.created_at'])->map(fn ($e) => ['id' => $e->id, 'action' => $e->action, 'reason' => json_decode($e->changes, true)['reason'] ?? null, 'actor' => $e->actor, 'created_at' => CarbonImmutable::parse($e->created_at, 'UTC')->toIso8601String()])]);
    }

    public function edit(DisciplinaryCase $case)
    {
        Gate::authorize('update', $case);

        return Inertia::render('discipline', ['mode' => 'edit', 'today' => now('Asia/Jakarta')->toDateString(), 'case' => $case->load('employee')->forDisplay()]);
    }

    public function update(DisciplineRequest $r, DisciplinaryCase $case, DisciplineRecords $records)
    {
        $this->run(fn () => $records->save($case, $r->validated(), null, $r->user()), route('discipline.edit', $case));

        return to_route('discipline.show', $case)->with('success', 'Koreksi catatan tersimpan.');
    }

    public function status(DisciplineActionRequest $r, DisciplinaryCase $case, DisciplineRecords $records)
    {
        $this->run(fn () => $records->status($case, $r->validated(), $r->user()), route('discipline.show', $case));

        return to_route('discipline.show', $case)->with('success', 'Status dan hasil tindak lanjut tersimpan.');
    }

    public function upload(DisciplineActionRequest $r, DisciplinaryCase $case, DisciplineRecords $records)
    {
        $this->run(fn () => $records->upload($case, $r->file('document'), $r->user()), route('discipline.show', $case));

        return to_route('discipline.show', $case)->with('success', 'Versi berkas tersimpan.');
    }

    public function download(DisciplinaryCase $case, DisciplineDocument $document)
    {
        Gate::authorize('view', $case);
        abort_unless($document->disciplinary_case_id === $case->id, 404);
        $disk = Storage::disk('discipline_documents');
        abort_unless($disk->exists($document->path), 404);

        return $disk->download($document->path, 'berkas-catatan-'.$case->id.'-v'.$document->version.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
