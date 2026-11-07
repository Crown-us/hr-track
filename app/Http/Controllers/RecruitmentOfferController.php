<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Http\Requests\ApplicantConversionRequest;
use App\Http\Requests\ApplicationRequest;
use App\Http\Requests\OfferRequest;
use App\Models\Application;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\RecruitmentOffer;
use App\Services\RecruitmentOffers;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RecruitmentOfferController
{
    public function show(Application $application)
    {
        Gate::authorize('update', $application);
        $application->load(['applicant', 'vacancy']);
        abort_unless($application->applicant, 404);
        $offer = RecruitmentOffer::where('application_id', $application->id)->first();
        $employee = Employee::withTrashed()->where('applicant_id', $application->applicant_id)->first();

        return Inertia::render('offering', [
            'application' => $application->forDisplay(true), 'offer' => $offer ? [...$offer->only(['department_id', 'position_id', 'employment_status', 'terms', 'status', 'response_note']),
                'join_date' => $offer->join_date->toDateString(), 'response_deadline' => $offer->response_deadline->toDateString(), 'response_date' => $offer->response_date?->toDateString(), 'has_evidence' => (bool) $offer->evidence_path] : null,
            'identity' => ['full_name' => $application->applicant->full_name, 'phone' => $application->applicant->phone],
            'employee' => $employee ? ['id' => $employee->id, 'employee_number' => $employee->employee_number, 'archived' => $employee->trashed()] : null,
            'events' => $offer ? DB::table('recruitment_offer_events')->join('users', 'users.id', '=', 'recruitment_offer_events.actor_id')->where('recruitment_offer_id', $offer->id)->orderByDesc('recruitment_offer_events.id')->get(['recruitment_offer_events.id', 'action', 'users.name as actor', 'recruitment_offer_events.created_at'])->map(fn ($e) => [...(array) $e, 'created_at' => CarbonImmutable::parse($e->created_at, 'UTC')->toIso8601String()]) : [],
            'departments' => Department::orderBy('name')->get(['id', 'name']), 'positions' => Position::orderBy('name')->get(['id', 'department_id', 'name']),
            'statuses' => array_map(fn ($s) => ['value' => $s->value, 'label' => $s->label()], array_filter(EmploymentStatus::cases(), fn ($s) => $s !== EmploymentStatus::Inactive)),
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function save(OfferRequest $request, Application $application, RecruitmentOffers $offers)
    {
        $offers->save($application, $request->validated(), $request->user());

        return back()->with('success', 'Penawaran disimpan. Periksa ringkasan sebelum mencatat respons.');
    }

    public function respond(Request $request, Application $application, RecruitmentOffers $offers)
    {
        Gate::authorize('update', $application);
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'], 'response' => ['required', Rule::in(['accepted', 'declined', 'withdrawn'])],
            'response_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'response_note' => ['required', 'string', 'min:10', 'max:2000'], 'evidence' => ['required', ...ApplicationRequest::cvRules()],
        ], ['evidence.required' => 'Lampirkan PDF bukti respons atau penarikan penawaran.', 'response_note.min' => 'Catatan respons minimal 10 karakter.']);
        $offers->respond($application, $data, $request->file('evidence'), $request->user());

        return back()->with('success', 'Respons dan bukti tersimpan; tahap lamaran telah diperbarui.');
    }

    public function convert(ApplicantConversionRequest $request, Application $application, RecruitmentOffers $offers)
    {
        $employee = $offers->convert($application, $request->validated(), $request->user());

        return to_route('employees.show', $employee)->with('success', 'Pegawai dibuat dari penawaran yang diterima. Akun login tidak dibuat otomatis. Catat kontrak kerja pegawai dari halaman detail ini.');
    }

    private function offer(Application $application): RecruitmentOffer
    {
        Gate::authorize('update', $application);
        abort_unless($application->applicant, 404);

        return RecruitmentOffer::with(['application.applicant', 'application.vacancy', 'department', 'position'])->where('application_id', $application->id)->firstOrFail();
    }

    public function summary(Application $application)
    {
        $offer = $this->offer($application);

        return Pdf::loadView('pdf.offer-summary', ['offer' => $offer])->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->download('ringkasan-penawaran-'.$application->id.'.pdf')->withHeaders(['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function evidence(Application $application)
    {
        $offer = $this->offer($application);
        $disk = Storage::disk('employee_records');
        abort_unless($offer->evidence_path && $disk->exists($offer->evidence_path), 404);

        return $disk->download($offer->evidence_path, 'bukti-respons-'.$application->id.'.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
