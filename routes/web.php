<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationEmailVerificationController;
use App\Http\Controllers\CareerController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\InterviewController;
use App\Http\Controllers\InvitationRegistrationController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\RecruitmentOfferController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\VacancyController;
use App\Models\Employee;
use App\Models\Vacancy;
use App\Services\ApplicationWorkflow;
use App\Services\ContractRecords;
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
    Route::get('/reset-password/{token}', [AccountPasswordController::class, 'show'])->middleware('throttle:20,1')->name('account-password.form');
    Route::post('/reset-password', [AccountPasswordController::class, 'store'])->middleware('throttle:6,1')->name('account-password.store');
    Route::get('/register', [InvitationRegistrationController::class, 'create'])->name('register');
    Route::post('/register', [InvitationRegistrationController::class, 'store'])->middleware('throttle:signup')->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/me', [EmployeePortalController::class, 'me'])->name('portal.me');
    Route::get('/team', [EmployeePortalController::class, 'team'])->name('portal.team');
    Route::get('/team/{employee}', [EmployeePortalController::class, 'member'])->name('portal.member');
});

Route::middleware(['auth', 'can:manage-accounts'])->group(function () {
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/reporting', [AccountController::class, 'reporting'])->name('accounts.reporting');
    Route::get('/accounts/reporting/{employee}', [AccountController::class, 'reportingEdit'])->name('accounts.reporting.edit');
    Route::post('/accounts/reporting', [AccountController::class, 'reportingSave'])->name('accounts.reporting.save');
    Route::post('/accounts/invitations', [AccountController::class, 'invite'])->name('accounts.invite');
    Route::post('/accounts/invitations/{invitation}/revoke', [AccountController::class, 'revoke'])->name('accounts.revoke');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->whereNumber('account')->name('accounts.show');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->whereNumber('account')->name('accounts.update');
    Route::post('/accounts/{account}/reset-link', [AccountController::class, 'resetLink'])->whereNumber('account')->name('accounts.reset-link');
});

Route::middleware(['auth', 'can:access-hr'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/discipline', [DisciplineController::class, 'index'])->name('discipline.index');
    Route::get('/discipline/create', [DisciplineController::class, 'create'])->name('discipline.create');
    Route::post('/discipline', [DisciplineController::class, 'store'])->name('discipline.store');
    Route::get('/discipline/{case}', [DisciplineController::class, 'show'])->name('discipline.show');
    Route::get('/discipline/{case}/edit', [DisciplineController::class, 'edit'])->name('discipline.edit');
    Route::put('/discipline/{case}', [DisciplineController::class, 'update'])->name('discipline.update');
    Route::post('/discipline/{case}/status', [DisciplineController::class, 'status'])->name('discipline.status');
    Route::post('/discipline/{case}/documents', [DisciplineController::class, 'upload'])->name('discipline.upload');
    Route::get('/discipline/{case}/documents/{document}', [DisciplineController::class, 'download'])->name('discipline.document');
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/{contract}/edit', [ContractController::class, 'edit'])->name('contracts.edit');
    Route::put('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
    Route::post('/contracts/{contract}/void', [ContractController::class, 'void'])->name('contracts.void');
    Route::post('/contracts/{contract}/documents', [ContractController::class, 'upload'])->name('contracts.upload');
    Route::get('/contracts/{contract}/documents/{document}', [ContractController::class, 'download'])->name('contracts.document');
    Route::get('/trainings', [TrainingController::class, 'index'])->name('trainings.index');
    Route::get('/trainings/create', [TrainingController::class, 'create'])->name('trainings.create');
    Route::post('/trainings', [TrainingController::class, 'store'])->name('trainings.store');
    Route::get('/trainings/{training}', [TrainingController::class, 'show'])->name('trainings.show');
    Route::get('/trainings/{training}/edit', [TrainingController::class, 'edit'])->name('trainings.edit');
    Route::put('/trainings/{training}', [TrainingController::class, 'update'])->name('trainings.update');
    Route::post('/trainings/{training}/status', [TrainingController::class, 'status'])->name('trainings.status');
    Route::post('/trainings/{training}/participants', [TrainingController::class, 'enroll'])->name('trainings.enroll');
    Route::get('/trainings/{training}/participants/{participant}/edit', [TrainingController::class, 'evaluation'])->name('trainings.evaluation');
    Route::put('/trainings/{training}/participants/{participant}', [TrainingController::class, 'evaluate'])->name('trainings.evaluate');
    Route::post('/trainings/{training}/materials', [TrainingController::class, 'upload'])->name('trainings.upload');
    Route::get('/trainings/{training}/materials/{material}', [TrainingController::class, 'download'])->name('trainings.material');
    Route::get('/hr', fn (Request $request) => $request->query('view') === 'recruitment' ? redirect('/applications') : Inertia::render('hr', [
        'initialView' => $request->query('view') === 'recruitment' ? 'recruitment' : 'dashboard',
        'employeeSummary' => ['count' => Employee::count(), 'recent' => Employee::with(['department', 'position'])->latest('id')->limit(4)->get()->map(fn ($employee) => $employee->forDisplay())],
        'vacancySummary' => ['count' => Vacancy::open()->count(), 'recent' => Vacancy::open()->with('department')->latest('id')->limit(4)->get()->map(fn ($vacancy) => $vacancy->forDisplay())],
        'recruitmentSummary' => app(ApplicationWorkflow::class)->summary(),
        'contractSummary' => app(ContractRecords::class)->summary(),
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

    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

    Route::get('/positions', [PositionController::class, 'index'])->name('positions.index');
    Route::get('/positions/create', [PositionController::class, 'create'])->name('positions.create');
    Route::post('/positions', [PositionController::class, 'store'])->name('positions.store');
    Route::get('/positions/{position}/edit', [PositionController::class, 'edit'])->name('positions.edit');
    Route::put('/positions/{position}', [PositionController::class, 'update'])->name('positions.update');

    Route::get('/applicants', [ApplicantController::class, 'index'])->name('applicants.index');
    Route::get('/applicants/create', [ApplicantController::class, 'create'])->name('applicants.create');
    Route::post('/applicants', [ApplicantController::class, 'store'])->name('applicants.store');
    Route::get('/applicants/{applicant}', [ApplicantController::class, 'show'])->name('applicants.show');
});
