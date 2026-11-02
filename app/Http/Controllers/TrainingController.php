<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Http\Requests\TrainingRequest;
use App\Models\Employee;
use App\Models\Training;
use App\Models\TrainingMaterial;
use App\Models\TrainingParticipant;
use App\Services\TrainingRecords;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TrainingController
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Training::class);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['scheduled', 'completed', 'cancelled'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = Training::withCount('participants');
        if ($term = trim($filters['q'] ?? '')) {
            $q->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('trainer', 'like', '%'.$term.'%'));
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        return Inertia::render('trainings', ['mode' => 'index', 'canManage' => $request->user()->can('create', Training::class), 'filters' => $filters,
            'trainings' => $q->orderByDesc('starts_at')->orderByDesc('id')->paginate(15)->withQueryString()->through(fn ($t) => $t->forDisplay())]);
    }

    public function create()
    {
        Gate::authorize('create', Training::class);

        return Inertia::render('trainings', ['mode' => 'create', 'canManage' => true, 'initial' => ['title' => '', 'trainer' => '', 'location' => '', 'notes' => '', 'starts_at' => now('Asia/Jakarta')->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addDay()->setTime(11, 0)->format('Y-m-d\TH:i')]]);
    }

    public function store(TrainingRequest $request, TrainingRecords $records)
    {
        $training = $records->save(null, $request->validated(), $request->user());

        return to_route('trainings.show', $training)->with('success', 'Pelatihan berhasil dibuat. Tambahkan peserta dan materi.');
    }

    public function edit(Training $training)
    {
        Gate::authorize('update', $training);

        return Inertia::render('trainings', ['mode' => 'edit', 'canManage' => true, 'training' => $training->forDisplay(true), 'initial' => [...$training->only('title', 'trainer', 'location', 'notes'), 'starts_at' => $training->starts_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'ends_at' => $training->ends_at->timezone('Asia/Jakarta')->format('Y-m-d\TH:i')]]);
    }

    public function update(TrainingRequest $request, Training $training, TrainingRecords $records)
    {
        $records->save($training, $request->validated(), $request->user());

        return to_route('trainings.show', $training)->with('success', 'Jadwal pelatihan diperbarui.');
    }

    public function show(Request $request, Training $training, TrainingRecords $records)
    {
        Gate::authorize('view', $training);
        $filters = $request->validate(['employee_q' => ['nullable', 'string', 'max:100'], 'participants_page' => ['sometimes', 'integer', 'min:1']]);
        $private = $request->user()->can('update', $training);
        $employees = Employee::where('employment_status', '!=', 'inactive')->whereNotIn('id', $training->participants()->select('employee_id'));
        if ($term = trim($filters['employee_q'] ?? '')) {
            $employees->where(fn ($q) => $q->where('full_name', 'like', '%'.$term.'%')->orWhere('employee_number', 'like', '%'.$term.'%'));
        }

        return Inertia::render('trainings', [
            'mode' => 'show', 'canManage' => $private, 'training' => $training->loadCount('participants')->forDisplay($private), 'filters' => $filters,
            'summary' => $records->summary($training, $private),
            'participants' => $private ? $training->participants()->with('employee')->orderBy('id')->paginate(15, ['*'], 'participants_page')->withQueryString()->through(fn ($p) => $p->forDisplay()) : ['data' => [], 'total' => 0, 'links' => []],
            'employees' => $private ? $employees->orderBy('full_name')->limit(20)->get(['id', 'full_name', 'employee_number']) : [],
            'materials' => $private ? TrainingMaterial::where('training_id', $training->id)->orderByDesc('version')->get()->map(fn ($m) => [...$m->only('id', 'version', 'size_bytes'), 'created_at' => $m->created_at->toIso8601String()]) : [],
            'events' => $private ? DB::table('training_events')->join('users', 'users.id', '=', 'training_events.actor_id')->where('training_id', $training->id)->orderByDesc('training_events.id')->limit(30)->get(['training_events.id', 'action', 'users.name as actor', 'training_events.created_at'])->map(fn ($e) => [...(array) $e, 'created_at' => CarbonImmutable::parse($e->created_at, 'UTC')->toIso8601String()]) : [],
        ]);
    }

    public function enroll(Request $request, Training $training, TrainingRecords $records)
    {
        Gate::authorize('update', $training);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('employment_status', '!=', 'inactive'))]], ['employee_id.exists' => 'Pilih pegawai aktif yang belum diarsipkan.', 'employee_id.required' => 'Pilih peserta.']);
        $records->enroll($training, $data, $request->user());

        return back()->with('success', 'Peserta ditambahkan.');
    }

    public function evaluation(Training $training, TrainingParticipant $participant)
    {
        Gate::authorize('update', $training);
        abort_unless($participant->training_id === $training->id, 404);

        return Inertia::render('trainings', ['mode' => 'evaluation', 'canManage' => true, 'training' => $training->forDisplay(true), 'participant' => $participant->load('employee')->forDisplay()]);
    }

    public function evaluate(Request $request, Training $training, TrainingParticipant $participant, TrainingRecords $records)
    {
        Gate::authorize('update', $training);
        abort_unless($participant->training_id === $training->id, 404);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'attendance' => ['required', Rule::in(['registered', 'attended', 'absent'])],
            'pre_test' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'], 'post_test' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'], 'notes' => ['nullable', 'string', 'max:2000']],
            ['pre_test.between' => 'Nilai pre-test harus 0–100.', 'post_test.between' => 'Nilai post-test harus 0–100.', 'pre_test.numeric' => 'Gunakan angka untuk nilai pre-test.', 'post_test.numeric' => 'Gunakan angka untuk nilai post-test.']);
        $records->evaluate($training, $participant, $data, $request->user());

        return to_route('trainings.show', $training)->with('success', 'Kehadiran dan evaluasi tersimpan.');
    }

    public function status(Request $request, Training $training, TrainingRecords $records)
    {
        Gate::authorize('update', $training);
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(['completed', 'cancelled'])], 'reason' => ['required_if:status,cancelled', 'nullable', 'string', 'min:10', 'max:1000']], ['reason.required_if' => 'Isi alasan pembatalan.', 'reason.min' => 'Alasan minimal 10 karakter.']);
        $records->status($training, $data, $request->user());

        return back()->with('success', 'Status pelatihan diperbarui.');
    }

    public function upload(Request $request, Training $training, TrainingRecords $records)
    {
        Gate::authorize('update', $training);
        $request->validate(['material' => ['required', ...ApplicationRequest::cvRules()]], ['material.required' => 'Pilih materi PDF.', 'material.max' => 'Materi maksimal 5 MiB.']);
        $records->upload($training, $request->file('material'), $request->user());

        return back()->with('success', 'Versi materi tersimpan.');
    }

    public function download(Training $training, TrainingMaterial $material)
    {
        Gate::authorize('update', $training);
        abort_unless($material->training_id === $training->id, 404);
        $disk = Storage::disk('training_materials');
        abort_unless($disk->exists($material->path), 404);

        return $disk->download($material->path, 'materi-pelatihan-'.$training->id.'-v'.$material->version.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
