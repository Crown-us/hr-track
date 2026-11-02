<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ApplicationWorkflow;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_and_history_failures_roll_back_without_orphan_files(): void
    {
        Storage::fake('applicant_documents');
        $actor = $this->actor();
        $this->actingAs($actor)->post('/applications', $this->data($this->vacancy()))->assertRedirect();
        $application = Application::firstOrFail();
        $actor->id = 999999;
        try {
            app(ApplicationWorkflow::class)->upload($application, $this->pdf(), $actor);
            $this->fail('Invalid actor must fail the document transaction.');
        } catch (QueryException) {
            $this->assertDatabaseCount('applicant_documents', 0);
            $this->assertCount(0, Storage::disk('applicant_documents')->allFiles());
        }
        try {
            app(ApplicationWorkflow::class)->transition($application, ['status' => 'interview', 'version' => 1], $actor);
            $this->fail('Invalid actor must fail the history transaction.');
        } catch (QueryException) {
            $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'screening', 'version' => 1]);
            $this->assertDatabaseCount('application_status_histories', 1);
        }
    }

    private function vacancy(array $extra = []): Vacancy
    {
        $department = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);

        return Vacancy::create([...['job_title' => 'HR Staff', 'department_id' => $department->id, 'employment_type' => 'full_time', 'location' => 'Jakarta', 'description' => 'Deskripsi pekerjaan fiktif untuk pengujian.', 'requirements' => 'Persyaratan fiktif untuk pengujian aplikasi.', 'opening_date' => now('Asia/Jakarta')->subDay()->toDateString(), 'closing_date' => now('Asia/Jakarta')->addDays(30)->toDateString(), 'status' => 'published'], ...$extra]);
    }

    private function data(Vacancy $vacancy): array
    {
        return ['mode' => 'new', 'full_name' => 'Nadia Fiktif', 'email' => 'nadia@example.test', 'phone' => '08123456789', 'vacancy_id' => $vacancy->id, 'application_date' => now('Asia/Jakarta')->toDateString(), 'notes' => 'Catatan internal HR'];
    }

    private function actor(string $role = 'hr_staff'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    public function test_create_starts_screening_with_private_cv_and_session_actor(): void
    {
        Storage::fake('applicant_documents');
        $actor = $this->actor();
        $this->actingAs($actor)->post('/applications', [...$this->data($this->vacancy()), 'cv' => $this->pdf(), 'status' => 'accepted', 'actor_id' => 999])->assertRedirect();
        $application = Application::firstOrFail();
        $this->assertSame('screening', $application->status->value);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $application->id, 'actor_id' => $actor->id, 'old_status' => null, 'new_status' => 'screening']);
        $document = DB::table('applicant_documents')->first();
        Storage::disk('applicant_documents')->assertExists($document->path);
        $this->get('/applications/'.$application->id.'/documents/'.$document->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_existing_profile_can_apply_to_another_job_without_duplicate_identity(): void
    {
        $this->actingAs($this->actor())->post('/applications', $this->data($this->vacancy()))->assertRedirect();
        $applicant = Applicant::firstOrFail();
        $second = $this->vacancy(['job_title' => 'Posisi lain']);
        $this->post('/applications', [...$this->data($second), 'mode' => 'existing', 'applicant_id' => $applicant->id, 'full_name' => 'Forged name'])->assertRedirect();
        $this->assertDatabaseCount('applicants', 1);
        $this->assertDatabaseCount('applications', 2);
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'full_name' => 'Nadia Fiktif']);
        $this->post('/applications', [...$this->data($second), 'mode' => 'existing', 'applicant_id' => $applicant->id])->assertSessionHasErrors('vacancy_id');
    }

    public function test_closed_scheduled_and_out_of_window_applications_are_rejected(): void
    {
        $this->actingAs($this->actor())->post('/applications', $this->data($this->vacancy(['status' => 'closed'])))->assertSessionHasErrors('vacancy_id');
        $this->post('/applications', $this->data($this->vacancy(['opening_date' => now('Asia/Jakarta')->addDays(2)->toDateString()])))->assertSessionHasErrors('vacancy_id');
        $this->post('/applications', [...$this->data($this->vacancy()), 'application_date' => '2000-01-01'])->assertSessionHasErrors('application_date');
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_legal_transitions_history_terminal_and_stale_requests(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor)->post('/applications', $this->data($this->vacancy()));
        $app = Application::firstOrFail();
        $url = '/applications/'.$app->id.'/status';
        $this->post($url, ['status' => 'accepted', 'version' => 1])->assertSessionHasErrors('status');
        $this->post($url, ['status' => 'interview', 'version' => 1, 'note' => 'Wawancara disiapkan'])->assertRedirect();
        $this->post($url, ['status' => 'rejected', 'version' => 1])->assertSessionHasErrors('version');
        $this->post($url, ['status' => 'offering', 'version' => 2])->assertRedirect();
        $this->post($url, ['status' => 'accepted', 'version' => 3])->assertRedirect();
        $this->post($url, ['status' => 'rejected', 'version' => 4])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('application_status_histories', 4);
        $this->assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'accepted', 'version' => 4]);
    }

    public function test_viewer_and_guest_cannot_mutate_or_see_notes_or_documents(): void
    {
        Storage::fake('applicant_documents');
        $this->actingAs($this->actor())->post('/applications', [...$this->data($this->vacancy()), 'cv' => $this->pdf()]);
        $app = Application::firstOrFail();
        $doc = DB::table('applicant_documents')->first();
        $this->actingAs($this->actor('viewer'))->withoutVite()->get('/applications/'.$app->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('applications', false)->missing('application.notes')->missing('application.phone')->has('documents', 0));
        $this->post('/applications', $this->data($this->vacancy()))->assertForbidden();
        $this->post('/applications/'.$app->id.'/status', ['status' => 'interview', 'version' => 1])->assertForbidden();
        $this->post('/applications/'.$app->id.'/cv', ['cv' => $this->pdf()])->assertForbidden();
        $this->get('/applications/'.$app->id.'/documents/'.$doc->id)->assertForbidden();
        $this->post('/logout');
        $this->get('/applications')->assertRedirect('/login');
    }

    public function test_fake_and_oversized_cvs_are_rejected_without_creating_records(): void
    {
        Storage::fake('applicant_documents');
        $vacancy = $this->vacancy();
        $this->actingAs($this->actor());
        $this->post('/applications', [...$this->data($vacancy), 'cv' => UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo 1;')])->assertSessionHasErrors('cv');
        $this->post('/applications', [...$this->data($vacancy), 'cv' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('cv');
        $this->assertDatabaseCount('applicants', 0);
        $this->assertCount(0, Storage::disk('applicant_documents')->allFiles());
    }

    public function test_cv_versions_ownership_and_missing_file(): void
    {
        Storage::fake('applicant_documents');
        $this->actingAs($this->actor());
        $this->post('/applications', [...$this->data($this->vacancy()), 'cv' => $this->pdf()]);
        $first = Application::firstOrFail();
        $this->post('/applications/'.$first->id.'/cv', ['cv' => $this->pdf()])->assertRedirect();
        $this->assertDatabaseCount('applicant_documents', 2);
        $this->post('/applications', [...$this->data($this->vacancy()), 'email' => 'other@example.test']);
        $other = Application::latest('id')->first();
        $doc = DB::table('applicant_documents')->first();
        $this->get('/applications/'.$other->id.'/documents/'.$doc->id)->assertNotFound();
        Storage::disk('applicant_documents')->delete($doc->path);
        $this->get('/applications/'.$first->id.'/documents/'.$doc->id)->assertNotFound();
    }

    public function test_filters_counts_and_dashboard_use_real_applications(): void
    {
        $this->actingAs($this->actor())->post('/applications', $this->data($this->vacancy()));
        $this->withoutVite()->get('/applications?q=Nadia')->assertInertia(fn (Assert $page) => $page->where('applications.total', 1)->where('counts.screening', 1));
        $this->get('/applications?status=rejected')->assertInertia(fn (Assert $page) => $page->where('applications.total', 0));
        $this->get('/hr')->assertInertia(fn (Assert $page) => $page->where('recruitmentSummary.applicants', 1)->where('recruitmentSummary.total', 1)->where('recruitmentSummary.counts.screening', 1));
    }
}
