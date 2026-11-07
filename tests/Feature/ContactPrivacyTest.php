<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const EMPLOYEE_EMAIL = 'nadia.pegawai@example.test';

    private const APPLICANT_EMAIL = 'budi.pelamar@example.test';

    public function test_viewer_does_not_receive_or_search_employee_contacts(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->user('viewer'))->withoutVite()->get('/employees')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employees', false)
                ->where('employees.total', 1)
                ->where('employees.data.0.id', $employee->id)
                ->missing('employees.data.0.work_email')
                ->missing('employees.data.0.phone'));
        $this->get('/employees/'.$employee->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employee.id', $employee->id)
                ->missing('employee.work_email')
                ->missing('employee.phone'));
        $this->get('/employees?q='.self::EMPLOYEE_EMAIL)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 0));
    }

    public function test_hr_staff_still_sees_and_searches_employee_contacts(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->user('hr_staff'))->withoutVite()->get('/employees?q='.self::EMPLOYEE_EMAIL)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employees.total', 1)
                ->where('employees.data.0.work_email', self::EMPLOYEE_EMAIL)
                ->where('employees.data.0.phone', '08123456789'));
        $this->get('/employees/'.$employee->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('employee.work_email', self::EMPLOYEE_EMAIL));
    }

    public function test_dashboard_summary_never_carries_employee_contacts(): void
    {
        $this->employee();

        foreach (['viewer', 'hr_staff'] as $role) {
            $this->actingAs($this->user($role))->withoutVite()->get('/hr')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has('employeeSummary.recent.0')
                    ->missing('employeeSummary.recent.0.work_email')
                    ->missing('employeeSummary.recent.0.phone'));
        }
    }

    public function test_viewer_does_not_receive_or_search_applicant_email(): void
    {
        $application = $this->application();

        $this->actingAs($this->user('viewer'))->withoutVite()->get('/applicants')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('applicants', false)
                ->where('applicants.total', 1)
                ->missing('applicants.data.0.email')
                ->missing('applicants.data.0.phone'));
        $this->get('/applicants?q='.self::APPLICANT_EMAIL)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('applicants.total', 0));
        $this->get('/applications')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)
                ->where('applications.data.0.name', 'Budi Pelamar')
                ->missing('applications.data.0.email'));
        $this->get('/applications?q='.self::APPLICANT_EMAIL)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('applications.total', 0));
        $this->get('/applications/'.$application->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('application.email'));
    }

    public function test_hr_staff_still_sees_and_searches_applicant_email(): void
    {
        $application = $this->application();

        $this->actingAs($this->user('hr_staff'))->withoutVite()->get('/applicants?q='.self::APPLICANT_EMAIL)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('applicants.total', 1)
                ->where('applicants.data.0.email', self::APPLICANT_EMAIL));
        $this->get('/applications?q='.self::APPLICANT_EMAIL)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)
                ->where('applications.data.0.email', self::APPLICANT_EMAIL));
        $this->get('/applications/'.$application->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('application.email', self::APPLICANT_EMAIL));
    }

    public function test_shared_session_user_carries_role_label_and_home_for_the_landing_header(): void
    {
        $this->withoutVite()->get('/')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));

        $expected = [
            'admin' => ['Admin', '/hr'],
            'hr_staff' => ['Staf HR', '/hr'],
            'viewer' => ['Pembaca', '/hr'],
            'employee' => ['Pegawai', '/me'],
            'supervisor' => ['Atasan', '/me'],
        ];
        foreach ($expected as $role => [$label, $home]) {
            $this->actingAs($this->user($role))->get('/')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('auth.user.role', $role)
                    ->where('auth.user.role_label', $label)
                    ->where('auth.user.home', $home)
                    ->missing('auth.user.password'));
        }
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function employee(): Employee
    {
        $department = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'name' => 'HR Staff']);

        return Employee::create(['employee_number' => 'EMP-001', 'full_name' => 'Nadia Fiktif', 'work_email' => self::EMPLOYEE_EMAIL,
            'phone' => '08123456789', 'department_id' => $department->id, 'position_id' => $position->id,
            'employment_status' => 'permanent', 'join_date' => '2026-01-01']);
    }

    private function application(): Application
    {
        Storage::fake('applicant_documents');
        $department = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);
        $vacancy = Vacancy::create(['job_title' => 'HR Staff', 'department_id' => $department->id, 'employment_type' => 'full_time',
            'location' => 'Jakarta', 'description' => 'Deskripsi pekerjaan fiktif untuk pengujian.',
            'requirements' => 'Persyaratan fiktif untuk pengujian aplikasi.', 'opening_date' => now('Asia/Jakarta')->subDay()->toDateString(),
            'closing_date' => now('Asia/Jakarta')->addDays(30)->toDateString(), 'status' => 'published']);
        $cv = UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");

        $this->actingAs($this->user('hr_staff'))->post('/applications', ['mode' => 'new', 'full_name' => 'Budi Pelamar',
            'email' => self::APPLICANT_EMAIL, 'phone' => '08123456789', 'vacancy_id' => $vacancy->id,
            'application_date' => now('Asia/Jakarta')->toDateString(), 'notes' => 'Catatan internal HR', 'cv' => $cv]);

        return Application::firstOrFail();
    }
}
