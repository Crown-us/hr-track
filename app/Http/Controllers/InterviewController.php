<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Interview;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InterviewController
{
    public function index(Request $r)
    {
        $data = $r->validate(['scope' => ['nullable', Rule::in(['upcoming', 'all', 'cancelled'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $q = Interview::with(['application.applicant', 'application.vacancy', 'interviewer'])->whereHas('application.applicant');
        if (($data['scope'] ?? 'upcoming') === 'upcoming') {
            $q->whereNull('cancelled_at')->where('ends_at', '>', now());
        }if (($data['scope'] ?? '') === 'cancelled') {
            $q->whereNotNull('cancelled_at');
        }$manage = $r->user()->can('create', Application::class);

        return Inertia::render('interviews', ['mode' => 'index', 'canManage' => $manage, 'scope' => $data['scope'] ?? 'upcoming', 'interviews' => $q->orderBy('starts_at')->orderBy('id')->paginate(15)->withQueryString()->through(fn ($i) => $i->forDisplay($manage))]);
    }

    public function create(Request $r)
    {
        Gate::authorize('create', Application::class);
        $data = $r->validate(['q' => ['nullable', 'string', 'max:100'], 'application_id' => ['nullable', 'integer', 'exists:applications,id']]);
        $q = Application::with(['applicant', 'vacancy'])->where('status', 'interview')->whereHas('applicant');
        if ($term = trim($data['q'] ?? '')) {
            $q->whereHas('applicant', fn ($p) => $p->where('full_name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }$options = $q->latest('id')->limit(30)->get();
        $selected = isset($data['application_id']) ? Application::with(['applicant', 'vacancy'])->where('status', 'interview')->whereHas('applicant')->findOrFail($data['application_id']) : null;
        if ($selected && ! $options->contains('id', $selected->id)) {
            $options->prepend($selected);
        }

        return Inertia::render('interviews', ['mode' => 'create', 'canManage' => true, 'q' => $data['q'] ?? '', 'defaultStart' => now('Asia/Jakarta')->addDay()->setTime(9, 0)->format('Y-m-d\TH:i'), 'selectedId' => $selected?->id, 'applications' => $options->map(fn ($a) => $a->forDisplay()), 'interviewers' => User::whereIn('role', ['admin', 'hr_staff'])->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $r)
    {
        Gate::authorize('create', Application::class);
        $d = $r->validate(['application_id' => ['required', 'integer', 'exists:applications,id'], 'interviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'hr_staff'])], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'duration' => ['required', 'integer', 'min:15', 'max:480'], 'location' => ['required_without:meeting_url', 'nullable', 'string', 'max:255'], 'meeting_url' => ['required_without:location', 'nullable', 'url:https', 'max:2048'], 'notes' => ['nullable', 'string', 'max:3000']], ['location.required_without' => 'Isi lokasi atau tautan meeting HTTPS.', 'meeting_url.required_without' => 'Isi lokasi atau tautan meeting HTTPS.', 'meeting_url.url' => 'Gunakan tautan HTTPS yang valid.', 'duration.min' => 'Durasi minimal 15 menit.', 'duration.max' => 'Durasi maksimal 480 menit.']);
        $start = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $d['starts_at'], 'Asia/Jakarta')->utc();
        if (! $start->isFuture()) {
            throw ValidationException::withMessages(['starts_at' => 'Jadwal harus berada di masa depan (WIB).']);
        }$end = $start->addMinutes((int) $d['duration']);
        DB::transaction(function () use ($d, $r, $start, $end) {
            $a = Application::whereKey($d['application_id'])->lockForUpdate()->firstOrFail();
            if ($a->status !== ApplicationStatus::Interview || ! $a->applicant) {
                throw ValidationException::withMessages(['application_id' => 'Lamaran harus berada di tahap Interview.']);
            }Applicant::whereKey($a->applicant_id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($d['interviewer_id'])->whereIn('role', ['admin', 'hr_staff'])->lockForUpdate()->first();
            if (! $user) {
                throw ValidationException::withMessages(['interviewer_id' => 'Pewawancara tidak tersedia.']);
            }$conflict = Interview::whereNull('cancelled_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->where(fn ($q) => $q->where('interviewer_id', $user->id)->orWhereHas('application', fn ($q) => $q->where('applicant_id', $a->applicant_id)))->lockForUpdate()->first();
            if ($conflict) {
                throw ValidationException::withMessages(['starts_at' => 'Jadwal bentrok dengan interview pewawancara atau pelamar.']);
            }Interview::create(['application_id' => $a->id, 'interviewer_id' => $user->id, 'created_by' => $r->user()->id, 'starts_at' => $start, 'ends_at' => $end, 'location' => $d['location'] ?? null, 'meeting_url' => $d['meeting_url'] ?? null, 'notes' => $d['notes'] ?? null]);
        });

        return to_route('interviews.index')->with('success', 'Interview berhasil dijadwalkan. Undangan tersedia untuk diunduh; email tidak dikirim.');
    }

    public function cancel(Request $r, Interview $interview)
    {
        Gate::authorize('create', Application::class);
        $d = $r->validate(['reason' => ['required', 'string', 'min:10', 'max:1000'], 'version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($interview, $d, $r) {
            Application::whereKey($interview->application_id)->lockForUpdate()->firstOrFail();
            $i = Interview::whereKey($interview->id)->lockForUpdate()->firstOrFail();
            if ($i->version !== (int) $d['version'] || $i->cancelled_at || ! $i->ends_at->isFuture()) {
                throw ValidationException::withMessages(['version' => 'Jadwal sudah berubah/dibatalkan/selesai. Muat ulang halaman.']);
            }$i->update(['cancelled_at' => now(), 'cancelled_by' => $r->user()->id, 'cancel_reason' => $d['reason'], 'version' => $i->version + 1]);
        });

        return to_route('interviews.index')->with('success', 'Interview dibatalkan; riwayatnya tetap tersimpan.');
    }

    public function invitation(Interview $interview)
    {
        Gate::authorize('create', Application::class);
        $interview->load(['application.applicant', 'application.vacancy', 'interviewer']);
        abort_if($interview->cancelled_at || ! $interview->application?->applicant || $interview->application->status !== ApplicationStatus::Interview, 404);

        return Pdf::loadView('pdf.interview-invitation', ['interview' => $interview])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->download('undangan-interview-'.$interview->id.'.pdf')->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
