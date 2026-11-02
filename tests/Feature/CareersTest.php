<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CareersTest extends TestCase
{
    use RefreshDatabase;

    public function test_honeypot_and_csrf_do_not_create_public_records(): void
    {
        Storage::fake('applicant_documents');
        $job = $this->job();
        $this->post('/karier/'.$job->id.'/lamar', [...$this->data(), 'website' => 'https://bot.example.test'])->assertRedirect('/karier/terima-kasih');
        $this->assertDatabaseCount('applications', 0);
        $this->app->detectEnvironment(fn () => 'local');
        $this->post('/karier/'.$job->id.'/lamar', $this->data())->assertStatus(419);
        $this->assertCount(0, Storage::disk('applicant_documents')->allFiles());
    }

    private function job(array $extra = []): Vacancy
    {
        $d = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);

        return Vacancy::create([...['department_id' => $d->id, 'job_title' => 'HR Generalist', 'employment_type' => 'full_time', 'location' => 'Jakarta', 'description' => 'Mengelola administrasi dan kegiatan sumber daya manusia.', 'requirements' => 'Memahami administrasi sumber daya manusia dan komunikasi.', 'opening_date' => now('Asia/Jakarta')->subDay()->toDateString(), 'closing_date' => now('Asia/Jakarta')->addDays(10)->toDateString(), 'status' => 'published'], ...$extra]);
    }

    private function data(): array
    {
        return ['full_name' => 'Nadia Fiktif', 'email' => 'nadia@example.test', 'phone' => '08123456789', 'consent' => true, 'website' => '', 'cv' => UploadedFile::fake()->createWithContent('CV.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
    }

    public function test_only_open_jobs_are_public_and_home_has_at_most_three(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->job();
        }$draft = $this->job(['status' => 'draft']);
        $this->job(['status' => 'closed']);
        $future = $this->job(['opening_date' => now('Asia/Jakarta')->addDay()->toDateString()]);
        $this->withoutVite()->get('/')->assertInertia(fn (Assert $p) => $p->has('careerVacancies', 3)->missing('careerVacancies.0.version')->missing('careerVacancies.0.description'));
        $this->get('/karier')->assertInertia(fn (Assert $p) => $p->where('vacancies.total', 4)->missing('vacancies.data.0.version'));
        $this->get('/karier/'.$draft->id)->assertNotFound();
        $this->get('/karier/'.$future->id)->assertNotFound();
    }

    public function test_guest_submission_starts_screening_with_no_public_identity_or_storage_leak(): void
    {
        Storage::fake('applicant_documents');
        $job = $this->job();
        $this->withoutVite()->get('/karier/'.$job->id)->assertOk();
        $this->post('/karier/'.$job->id.'/lamar', $this->data())->assertRedirect('/karier/terima-kasih');
        $a = Application::firstOrFail();
        $this->assertSame('screening', $a->status->value);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $a->id, 'actor_id' => null, 'new_status' => 'screening']);
        $doc = DB::table('applicant_documents')->first();
        $this->assertSame($a->id, $doc->application_id);
        $this->assertNull($doc->uploaded_by);
        Storage::disk('applicant_documents')->assertExists($doc->path);
        $this->get('/karier/terima-kasih')->assertInertia(fn (Assert $p) => $p->missing('applicant')->missing('application')->missing('documents'));
        $this->get('/applications/'.$a->id.'/documents/'.$doc->id)->assertRedirect('/login');
    }

    public function test_duplicate_response_is_generic_and_existing_profile_is_not_overwritten(): void
    {
        Storage::fake('applicant_documents');
        $first = $this->job();
        $this->post('/karier/'.$first->id.'/lamar', $this->data())->assertRedirect('/karier/terima-kasih');
        $this->post('/karier/'.$first->id.'/lamar', [...$this->data(), 'full_name' => 'Nama lain'])->assertRedirect('/karier/terima-kasih');
        $this->assertDatabaseCount('applications', 1);
        $second = $this->job();
        $this->post('/karier/'.$second->id.'/lamar', [...$this->data(), 'full_name' => 'Nama diajukan'])->assertRedirect();
        $this->assertDatabaseCount('applicants', 1);
        $this->assertDatabaseCount('applications', 2);
        $this->assertDatabaseHas('applicants', ['email' => 'nadia@example.test', 'full_name' => 'Nadia Fiktif']);
        $this->assertDatabaseHas('applications', ['vacancy_id' => $second->id, 'submitted_name' => 'Nama diajukan', 'source' => 'public']);
        $this->assertCount(2, Storage::disk('applicant_documents')->allFiles());
        $a = Application::where('vacancy_id', $second->id)->first();
        $this->actingAs(User::factory()->create(['role' => 'hr_staff']))->withoutVite()->get('/applications/'.$a->id)->assertInertia(fn (Assert $p) => $p->has('documents', 1)->where('application.source', 'public'));
        $other = DB::table('applicant_documents')->where('application_id', '!=', $a->id)->first();
        $own = DB::table('applicant_documents')->where('application_id', $a->id)->first();
        $this->get('/applications/'.$a->id.'/documents/'.$other->id)->assertNotFound();
        $this->get('/applications/'.$a->id.'/documents/'.$own->id)->assertOk();
    }

    public function test_consent_pdf_status_and_rate_limit_are_enforced(): void
    {
        Storage::fake('applicant_documents');
        $job = $this->job();
        $this->post('/karier/'.$job->id.'/lamar', [...$this->data(), 'consent' => false])->assertSessionHasErrors('consent');
        $this->post('/karier/'.$job->id.'/lamar', [...$this->data(), 'cv' => UploadedFile::fake()->createWithContent('fake.pdf', 'not a PDF')])->assertSessionHasErrors('cv');
        $this->post('/karier/'.$job->id.'/lamar', $this->data())->assertRedirect();
        $this->post('/karier/'.$job->id.'/lamar', $this->data())->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('applications', 1);
    }
}
