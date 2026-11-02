<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationEmailVerificationController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\InvitationRegistrationController;
use App\Http\Controllers\RecruitmentOfferController;
use App\Http\Controllers\VacancyController;
use App\Models\Employee;
use App\Models\Vacancy;
use App\Services\ApplicationWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome', ['careerVacancies' => Vacancy::open()->with('department')->latest('id')->limit(3)->get()->map(fn ($v) => CareerController::job($v))]);
})->name('home');

Route::get('/karier', [CareerController::class, 'index'])->name('careers.index');
Route::get('/karier/terima-kasih', [CareerController::class, 'thanks'])->name('careers.thanks');
Route::get('/karier/email-terverifikasi', fn () => Inertia::render('careers', ['mode' => 'verified']))->name('careers.verified');
Route::get('/karier/verifikasi/kirim-ulang', fn () => Inertia::render('careers', ['mode' => 'resend']))->name('careers.resend.form');
Route::post('/karier/verifikasi/kirim-ulang', [ApplicationEmailVerificationController::class, 'resend'])->middleware('throttle:email-resend')->name('careers.resend');
Route::get('/karier/verifikasi/{application}/{hash}', [ApplicationEmailVerificationController::class, 'show'])->middleware(['signed', 'throttle:30,1,email-view'])->name('careers.verify');
Route::post('/karier/verifikasi/{application}/{hash}', [ApplicationEmailVerificationController::class, 'store'])->middleware(['signed', 'throttle:6,1,email-confirm'])->name('careers.verify.store');
Route::get('/karier/{vacancy}', [CareerController::class, 'show'])->name('careers.show');
Route::get('/karier/{vacancy}/lamar', [CareerController::class, 'form'])->name('careers.apply');
Route::post('/karier/{vacancy}/lamar', [CareerController::class, 'store'])->middleware('throttle:public-application')->name('careers.store');

Route::get('/lab', function () {
    return Inertia::render('lab');
})->name('lab');

Route::get('/preview/hr', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return Inertia::render('hr');
})->name('preview.hr');

Route::middleware('guest')->group(function () {
    Route::get('/register', [InvitationRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [InvitationRegistrationController::class, 'store'])->middleware('throttle:signup')->name('register.store');
});

Route::middleware(['auth', 'can:access-hr'])->group(function () {
    Route::get('/hr', fn (Request $request) => $request->query('view') === 'recruitment' ? redirect('/applications') : Inertia::render('hr', [
        'initialView' => $request->query('view') === 'recruitment' ? 'recruitment' : 'dashboard',
        'employeeSummary' => ['count' => Employee::count(), 'recent' => Employee::with(['department', 'position'])->latest('id')->limit(4)->get()->map(fn ($employee) => $employee->forDisplay())],
        'vacancySummary' => ['count' => Vacancy::open()->count(), 'recent' => Vacancy::open()->with('department')->latest('id')->limit(4)->get()->map(fn ($vacancy) => $vacancy->forDisplay())],
        'recruitmentSummary' => app(ApplicationWorkflow::class)->summary(),
    ]))->name('hr');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/interviews', [InterviewController::class, 'index'])->name('interviews.index');
    Route::get('/interviews/create', [InterviewController::class, 'create'])->name('interviews.create');
    Route::post('/interviews', [InterviewController::class, 'store'])->name('interviews.store');
    Route::post('/interviews/{interview}/cancel', [InterviewController::class, 'cancel'])->name('interviews.cancel');
    Route::get('/interviews/{interview}/invitation', [InterviewController::class, 'invitation'])->name('interviews.invitation');
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::get('/applications/{application}/offering', [RecruitmentOfferController::class, 'show'])->name('offering.show');
    Route::post('/applications/{application}/offering', [RecruitmentOfferController::class, 'save'])->name('offering.save');
    Route::post('/applications/{application}/offering/response', [RecruitmentOfferController::class, 'respond'])->name('offering.response');
    Route::post('/applications/{application}/offering/employee', [RecruitmentOfferController::class, 'convert'])->name('offering.convert');
    Route::get('/applications/{application}/offering/summary', [RecruitmentOfferController::class, 'summary'])->name('offering.summary');
    Route::get('/applications/{application}/offering/evidence', [RecruitmentOfferController::class, 'evidence'])->name('offering.evidence');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::post('/applications/{application}/status', [ApplicationController::class, 'transition'])->name('applications.status');
    Route::post('/applications/{application}/cv', [ApplicationController::class, 'upload'])->name('applications.cv');
    Route::get('/applications/{application}/documents/{document}', [ApplicationController::class, 'download'])->name('applications.document');
    Route::get('/vacancies', [VacancyController::class, 'index'])->name('vacancies.index');
    Route::get('/vacancies/create', [VacancyController::class, 'create'])->name('vacancies.create');
    Route::post('/vacancies', [VacancyController::class, 'store'])->name('vacancies.store');
    Route::get('/vacancies/{vacancy}', [VacancyController::class, 'show'])->name('vacancies.show');
    Route::get('/vacancies/{vacancy}/edit', [VacancyController::class, 'edit'])->name('vacancies.edit');
    Route::put('/vacancies/{vacancy}', [VacancyController::class, 'update'])->name('vacancies.update');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->withTrashed()->name('employees.show');
    Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/employees/{employee}/archive', [EmployeeController::class, 'archiveForm'])->name('employees.archive-form');
    Route::post('/employees/{employee}/archive', [EmployeeController::class, 'archive'])->name('employees.archive');
    Route::post('/employees/{employee}/restore', [EmployeeController::class, 'restore'])->withTrashed()->name('employees.restore');
    Route::get('/employees/{employee}/events/{event}/document', [EmployeeController::class, 'document'])->withTrashed()->name('employees.document');
});
