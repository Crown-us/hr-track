<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OpenCodeRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $type): Vacancy
    {
        $d = Department::firstOrCreate(['code' => 'REG'], ['name' => 'Departemen Pengujian']);

        return Vacancy::create(['department_id' => $d->id, 'job_title' => 'Staf HR', 'employment_type' => $type, 'location' => 'Jakarta', 'description' => 'Deskripsi pengujian lowongan kerja.', 'requirements' => 'Persyaratan pengujian lowongan kerja.', 'opening_date' => now()->toDateString(), 'closing_date' => now()->addMonth()->toDateString(), 'status' => 'draft']);
    }

    public function test_applicant_directory_and_detail_render_without_exposing_phone_to_viewer(): void
    {
        $this->withoutVite();
        $person = Applicant::create(['full_name' => 'Nadia Putri', 'email' => 'regression@example.test', 'phone' => '08123456789']);
        Application::create(['applicant_id' => $person->id, 'vacancy_id' => $this->job('full_time')->id, 'application_date' => now()->toDateString()]);
        $this->actingAs(User::factory()->create(['role' => 'hr_staff']));
        $this->get('/applicants')->assertOk()->assertInertia(fn (Assert $p) => $p->where('applicants.data.0.application_count', 1)->where('applicants.data.0.phone', '08123456789'));
        $this->get('/applicants/'.$person->id)->assertOk()->assertInertia(fn (Assert $p) => $p->where('applicant.application_count', 1));
        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        $this->get('/applicants')->assertOk()->assertInertia(fn (Assert $p) => $p->missing('applicants.data.0.phone'));
        $this->get('/applicants/'.$person->id)->assertOk()->assertInertia(fn (Assert $p) => $p->missing('applicant.phone')->has('applicant.documents', 0));
        $this->post('/applicants', ['full_name' => 'Nama', 'email' => 'new@example.test'])->assertForbidden();
    }

    public function test_vacancy_employment_filter_is_applied_and_retained(): void
    {
        $this->withoutVite();
        $this->job('full_time');
        $wanted = $this->job('contract');
        $this->actingAs(User::factory()->create(['role' => 'hr_staff']))->get('/vacancies?employment_type=contract&status=draft')
            ->assertInertia(fn (Assert $p) => $p->where('vacancies.total', 1)->where('vacancies.data.0.id', $wanted->id)->where('filters.employment_type', 'contract'));
    }

    public function test_referenced_position_department_change_is_a_validation_error_and_master_pages_work(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'hr_staff']));
        $department = $this->job('contract')->department;
        $position = Position::create(['department_id' => $department->id, 'name' => 'HR Staff']);
        Employee::create(['employee_number' => 'MASTER-001', 'full_name' => 'Nadia Putri', 'work_email' => 'master@example.test', 'department_id' => $department->id, 'position_id' => $position->id, 'employment_status' => 'contract', 'join_date' => now()->toDateString()]);
        $other = Department::create(['code' => 'OTHER', 'name' => 'Finance']);
        foreach (['/departments', '/departments/create', '/departments/'.$department->id.'/edit', '/positions', '/positions/create', '/positions/'.$position->id.'/edit'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->put('/positions/'.$position->id, ['department_id' => $other->id, 'name' => 'HR Staff'])->assertSessionHasErrors('department_id');
        $this->assertDatabaseHas('positions', ['id' => $position->id, 'department_id' => $department->id]);
    }

    public function test_profile_documents_link_to_their_own_application_not_the_profile_id(): void
    {
        $this->withoutVite();
        $person = Applicant::create(['full_name' => 'Nadia Putri', 'email' => 'cv-link@example.test']);
        $a = Application::create(['applicant_id' => $person->id, 'vacancy_id' => $this->job('contract')->id, 'application_date' => now()->toDateString()]);
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $doc = ApplicantDocument::create(['applicant_id' => $person->id, 'application_id' => $a->id, 'uploaded_by' => $hr->id, 'path' => 'regression/cv.pdf', 'size_bytes' => 100]);
        $this->actingAs($hr)->get('/applicants/'.$person->id)->assertInertia(fn (Assert $p) => $p->where('applicant.documents.0.download_url', route('applications.document', ['application' => $a->id, 'document' => $doc->id])));
    }
}
