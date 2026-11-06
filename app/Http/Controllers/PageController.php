<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Interview;
use App\Models\Vacancy;
use App\Services\ApplicationWorkflow;
use App\Services\ContractRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController
{
    private const SUMMARY_LIMIT = 4;

    private const INTERVIEW_LIMIT = 5;

    public function home(): Response
    {
        $vacancies = Vacancy::open()->with('department')->latest('id')->limit(3)->get();

        return Inertia::render('welcome', [
            'careerVacancies' => $vacancies->map(fn (Vacancy $vacancy) => CareerController::job($vacancy)),
        ]);
    }

    public function careerVerified(): Response
    {
        return Inertia::render('careers', ['mode' => 'verified']);
    }

    public function careerResendForm(): Response
    {
        return Inertia::render('careers', ['mode' => 'resend']);
    }

    public function lab(): Response
    {
        $this->abortUnlessLocalOrTesting();

        return Inertia::render('lab');
    }

    public function previewHr(): Response
    {
        $this->abortUnlessLocalOrTesting();

        return Inertia::render('hr');
    }

    public function hr(Request $request): Response|RedirectResponse
    {
        if ($request->query('view') === 'recruitment') {
            return redirect('/applications');
        }

        return Inertia::render('hr', [
            'initialView' => 'dashboard',
            'employeeSummary' => $this->employeeSummary(),
            'vacancySummary' => $this->vacancySummary(),
            'recruitmentSummary' => app(ApplicationWorkflow::class)->summary(),
            'contractSummary' => app(ContractRecords::class)->summary(),
            'interviewSummary' => $this->interviewSummary(),
        ]);
    }

    private function abortUnlessLocalOrTesting(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
    }

    private function employeeSummary(): array
    {
        $recent = Employee::with(['department', 'position'])->latest('id')->limit(self::SUMMARY_LIMIT)->get();

        return [
            'count' => Employee::count(),
            'recent' => $recent->map(fn (Employee $employee) => $employee->forDisplay()),
        ];
    }

    private function vacancySummary(): array
    {
        $recent = Vacancy::open()->with('department')->latest('id')->limit(self::SUMMARY_LIMIT)->get();

        return [
            'count' => Vacancy::open()->count(),
            'recent' => $recent->map(fn (Vacancy $vacancy) => $vacancy->forDisplay()),
        ];
    }

    private function interviewSummary(): array
    {
        $upcoming = fn () => Interview::whereNull('cancelled_at')->where('starts_at', '>=', now());
        $next = $upcoming()
            ->with(['application.applicant', 'application.vacancy', 'interviewer'])
            ->orderBy('starts_at')
            ->limit(self::INTERVIEW_LIMIT)
            ->get();

        return [
            'count' => $upcoming()->count(),
            'next' => $next->map(fn (Interview $interview) => [
                'id' => $interview->id,
                'name' => $interview->application?->applicant?->full_name ?? 'Pelamar',
                'job_title' => $interview->application?->vacancy?->job_title ?? 'Lowongan',
                'interviewer' => $interview->interviewer?->name ?? 'Pewawancara',
                'starts_at' => $interview->starts_at->toIso8601String(),
            ]),
        ];
    }
}
