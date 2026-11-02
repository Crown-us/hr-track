<?php

namespace Tests\Feature;

use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Department;
use App\Models\Interview;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecruitmentJourneyTest extends TestCase
{
    use RefreshDatabase;

    public static function outcomes(): array
    {
        return ['diterima' => ['accepted'], 'ditolak' => ['rejected']];
    }

    private function vacancyData(): array
    {
        return [
            'department_id' => Department::create(['code' => 'UAT', 'name' => 'HR Uji Alur'])->id,
            'job_title' => 'HR Generalist Uji Alur',
            'employment_type' => 'full_time',
            'location' => 'Jakarta',
            'description' => 'Posisi fiktif untuk pengujian alur rekrutmen menyeluruh.',
            'requirements' => 'Persyaratan fiktif untuk pengujian, bukan lowongan perusahaan.',
            'opening_date' => now('Asia/Jakarta')->toDateString(),
            'closing_date' => now('Asia/Jakarta')->addDays(7)->toDateString(),
            'status' => 'draft',
        ];
    }

    private function submission(): array
    {
        return [
            'full_name' => 'Pelamar Uji Alur', 'email' => 'journey@example.test',
            'phone' => '08123456789', 'consent' => true, 'website' => '',
            'cv' => UploadedFile::fake()->createWithContent('cv-uat.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
        ];
    }

    #[DataProvider('outcomes')]
    public function test_hr_public_applicant_and_interviewer_complete_the_same_journey(string $outcome): void
    {
        $this->withoutVite();
        Storage::fake('applicant_documents');
        Storage::fake('employee_records');
        $this->travelTo(CarbonImmutable::parse('2026-11-02 08:00', 'Asia/Jakarta'));
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $this->post('/login', ['email' => $hr->email, 'password' => 'password'])->assertRedirect('/hr');
        $data = $this->vacancyData();
        $this->post('/vacancies', $data)->assertSessionHasNoErrors()->assertRedirect();
        $job = Vacancy::sole();
        $this->get('/karier/'.$job->id)->assertNotFound();
        $this->put('/vacancies/'.$job->id, [...$data, 'status' => 'published', 'version' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('careerVacancies.0.id', $job->id));
        $this->get('/karier?q=Generalist')->assertInertia(fn (Assert $p) => $p->where('vacancies.total', 1));
        $this->get('/karier/'.$job->id)->assertInertia(fn (Assert $p) => $p->where('vacancy.id', $job->id));
        $this->get('/karier/'.$job->id.'/lamar')->assertOk();
        $this->post('/karier/'.$job->id.'/lamar', $this->submission())
            ->assertSessionHasNoErrors()->assertRedirect('/karier/terima-kasih');
        $application = Application::sole();
        $document = ApplicantDocument::sole();
        Storage::disk('applicant_documents')->assertExists($document->path);
        $this->assertSame('screening', $application->status->value);
        $this->assertSame($application->id, $document->application_id);
        $download = '/applications/'.$application->id.'/documents/'.$document->id;
        $this->get($download)->assertRedirect('/login');
        $verification = URL::temporarySignedRoute('careers.verify', now()->addDay(), [
            'application' => $application->id, 'hash' => hash('sha256', $application->applicant->email),
        ]);
        $this->get($verification)->assertOk();
        $this->post($verification)->assertRedirect('/karier/email-terverifikasi');

        // Login returns HR to the protected CV URL requested before authentication.
        $this->post('/login', ['email' => $hr->email, 'password' => 'password'])->assertRedirect($download);
        $this->get('/applications/'.$application->id)->assertInertia(fn (Assert $p) => $p
            ->where('application.source', 'public')->where('application.status', 'screening')
            ->where('histories.0.actor', 'Pelamar (publik)')->has('documents', 1));
        $this->get($download)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $statusUrl = '/applications/'.$application->id.'/status';
        $this->post($statusUrl, ['status' => 'interview', 'version' => 1, 'note' => 'Lolos screening uji alur.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/interviews', [
            'application_id' => $application->id, 'interviewer_id' => $hr->id,
            'starts_at' => '2026-11-03T09:00', 'duration' => 45,
            'location' => 'Ruang HR Uji Alur', 'notes' => 'Catatan privat uji alur.',
        ])->assertSessionHasNoErrors()->assertRedirect('/interviews');
        $interview = Interview::sole();
        $this->assertSame('02:00', $interview->starts_at->format('H:i'));
        $pdf = $this->get('/interviews/'.$interview->id.'/invitation')->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        // Closing recruitment hides the public vacancy without interrupting existing applications.
        $this->put('/vacancies/'.$job->id, [...$data, 'status' => 'closed', 'version' => 2])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/karier/'.$job->id)->assertNotFound();
        $this->get('/karier')->assertInertia(fn (Assert $p) => $p->where('vacancies.total', 0));
        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', 'Asia/Jakarta'));
        $this->post($statusUrl, ['status' => 'offering', 'version' => 2])
            ->assertSessionHasNoErrors()->assertRedirect();
        $position = Position::create(['department_id' => $data['department_id'], 'name' => 'HR Generalist']);
        $offeringUrl = '/applications/'.$application->id.'/offering';
        $this->post($offeringUrl, ['version' => 3, 'department_id' => $data['department_id'], 'position_id' => $position->id, 'employment_status' => 'contract', 'response_deadline' => '2026-11-06', 'join_date' => '2026-11-09'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post($offeringUrl.'/response', ['version' => 4, 'response' => $outcome === 'accepted' ? 'accepted' : 'declined', 'response_date' => '2026-11-03', 'response_note' => 'Respons kandidat fiktif telah diperiksa.', 'evidence' => UploadedFile::fake()->createWithContent('respons.pdf', "%PDF-1.4\n%%EOF")])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => $outcome, 'version' => 5]);
        $history = ApplicationStatusHistory::where('application_id', $application->id)->orderBy('id')->get();
        $this->assertSame(['screening', 'interview', 'offering', $outcome], $history->pluck('new_status')->all());
        $this->assertSame([null, $hr->id, $hr->id, $hr->id], $history->pluck('actor_id')->all());
        $this->assertNull($interview->fresh()->cancelled_at); // Completed interview stays in the history.
        $this->get('/interviews/'.$interview->id.'/invitation')->assertNotFound();
        $this->get('/hr')->assertInertia(fn (Assert $p) => $p
            ->where('recruitmentSummary.total', 1)->where('recruitmentSummary.counts.'.$outcome, 1)
            ->where('recruitmentSummary.counts.screening', 0)->where('vacancySummary.count', 0));
        $this->post($statusUrl, ['status' => 'screening', 'version' => 5])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('application_status_histories', 4);

        if ($outcome === 'accepted') {
            $this->post($offeringUrl.'/employee', ['version' => 5, 'employee_number' => 'HR-UAT-001', 'work_email' => 'hr-uat@example.test', 'full_name' => 'Pelamar Uji Alur', 'phone' => '08123456789', 'identity_confirmed' => true])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('employees', ['applicant_id' => $application->applicant_id, 'source_application_id' => $application->id, 'position_id' => $position->id]);
        } else {
            $this->assertDatabaseCount('employees', 0);
        }

        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        $this->get('/applications/'.$application->id)->assertInertia(fn (Assert $p) => $p
            ->has('documents', 0)->missing('application.phone')->missing('histories.1.note'));
        $this->get($download)->assertForbidden();
        $this->post($statusUrl, ['status' => 'screening', 'version' => 4])->assertForbidden();
    }

    public function test_public_visibility_and_submission_end_at_midnight_wib(): void
    {
        $this->withoutVite();
        Storage::fake('applicant_documents');
        // UTC is still 1 November when the WIB opening day starts.
        $this->travelTo(CarbonImmutable::parse('2026-11-01 17:00:00', 'UTC'));
        $job = Vacancy::create([...$this->vacancyData(), 'status' => 'published', 'closing_date' => '2026-11-02']);
        $this->get('/karier/'.$job->id)->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 16:59:59', 'UTC'));
        $this->get('/karier/'.$job->id.'/lamar')->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 17:00:00', 'UTC'));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('careerVacancies', 0));
        $this->get('/karier/'.$job->id)->assertNotFound();
        $this->post('/karier/'.$job->id.'/lamar', $this->submission())->assertNotFound();
        $this->assertDatabaseCount('applications', 0);
        $this->assertCount(0, Storage::disk('applicant_documents')->allFiles());
    }
}
