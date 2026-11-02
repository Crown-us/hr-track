<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Department;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\ApplicationEmailVerification;
use App\Notifications\ApplicationReceived;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ApplicationEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypted_database_jobs_deliver_verification_then_receipt_without_external_mail(): void
    {
        config(['mail.default' => 'array']);
        $this->withoutVite();
        $a = $this->submit();
        $payload = json_decode(DB::table('jobs')->sole()->payload, true);
        $this->assertIsString($payload['data']['command']);
        $this->assertStringNotContainsString('verify@example.test', $payload['data']['command']);
        $this->assertStringNotContainsString('App\\Notifications', $payload['data']['command']);
        $job = Queue::connection('recruitment')->pop('recruitment-mail');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->last()->getOriginalMessage();
        $this->assertSame('verify@example.test', $message->getTo()[0]->getAddress());
        $this->assertStringContainsString('24 jam', $message->getTextBody());
        $this->post($this->link($a))->assertRedirect('/karier/email-terverifikasi');
        $job = Queue::connection('recruitment')->pop('recruitment-mail');
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();
        $this->assertCount(2, $transport->messages());
        $this->assertStringContainsString('bukan pernyataan lolos', $transport->messages()->last()->getOriginalMessage()->getTextBody());
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_queue_failure_rolls_back_public_records_and_cleans_cv(): void
    {
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $query) {
            if (str_starts_with(strtolower($query->sql), 'insert into `jobs`')) {
                throw new \RuntimeException('Simulated queue insert failure');
            }
        });
        try {
            $this->submit();
            $this->fail('Queue insert must fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated queue insert failure', $e->getMessage());
        }
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('applicants', 0);
        $this->assertDatabaseCount('applicant_documents', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(0, Storage::disk('applicant_documents')->allFiles());
    }

    private function submit(): Application
    {
        Storage::fake('applicant_documents');
        $job = Vacancy::create([
            'department_id' => Department::create(['code' => 'EMAIL', 'name' => 'HR Uji Email'])->id,
            'job_title' => 'HR Uji Email', 'employment_type' => 'full_time', 'location' => 'Jakarta',
            'description' => 'Deskripsi fiktif untuk pengujian verifikasi email.',
            'requirements' => 'Persyaratan fiktif untuk pengujian verifikasi email.',
            'opening_date' => now('Asia/Jakarta')->toDateString(),
            'closing_date' => now('Asia/Jakarta')->addDay()->toDateString(), 'status' => 'published',
        ]);
        $this->post('/karier/'.$job->id.'/lamar', [
            'full_name' => 'Pelamar Uji Email', 'email' => 'verify@example.test', 'consent' => true,
            'cv' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertSessionHasNoErrors()->assertRedirect('/karier/terima-kasih');

        return Application::sole();
    }

    private function link(Application $a): string
    {
        return URL::temporarySignedRoute('careers.verify', now()->addDay(), [
            'application' => $a->id, 'hash' => hash('sha256', $a->applicant->email),
        ]);
    }

    public function test_signed_confirmation_verifies_once_and_unblocks_hr_workflow(): void
    {
        Notification::fake();
        $this->withoutVite();
        $a = $this->submit();
        Notification::assertSentOnDemand(ApplicationEmailVerification::class, fn ($n, $channels, $recipient) => $recipient->routes['mail'] === 'verify@example.test');
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($hr)->post('/applications/'.$a->id.'/status', ['status' => 'interview', 'version' => 1])
            ->assertSessionHasErrors('status');
        $this->post('/logout');
        $link = $this->link($a);
        $this->get($link)->assertOk();
        $this->assertNull($a->fresh()->email_verified_at);
        $this->post($link)->assertRedirect('/karier/email-terverifikasi');
        $this->assertNotNull($a->fresh()->email_verified_at);
        $this->post($link)->assertRedirect('/karier/email-terverifikasi');
        Notification::assertSentOnDemandTimes(ApplicationReceived::class, 1);
        $this->actingAs($hr)->post('/applications/'.$a->id.'/status', ['status' => 'interview', 'version' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('interview', $a->fresh()->status->value);
    }

    public function test_tampered_expired_or_changed_email_links_cannot_verify(): void
    {
        Notification::fake();
        $a = $this->submit();
        $link = $this->link($a);
        $this->post($link.'&tamper=1')->assertForbidden();
        $oldEmail = $a->applicant->email;
        $a->applicant->update(['email' => 'changed@example.test']);
        $this->post($link)->assertNotFound();
        $a->applicant->update(['email' => $oldEmail]);
        $this->travel(25)->hours();
        $this->post($link)->assertForbidden();
        $this->assertNull($a->fresh()->email_verified_at);
        Notification::assertSentOnDemandTimes(ApplicationReceived::class, 0);
    }

    public function test_resend_is_generic_and_has_per_application_cooldown(): void
    {
        Notification::fake();
        $a = $this->submit();
        $data = ['email' => 'verify@example.test', 'vacancy_id' => $a->vacancy_id];
        $this->post('/karier/verifikasi/kirim-ulang', $data)->assertRedirect('/karier/terima-kasih');
        Notification::assertSentOnDemandTimes(ApplicationEmailVerification::class, 1);
        $this->travel(11)->minutes();
        $this->post('/karier/verifikasi/kirim-ulang', $data)->assertRedirect('/karier/terima-kasih');
        Notification::assertSentOnDemandTimes(ApplicationEmailVerification::class, 2);
        $this->post('/karier/verifikasi/kirim-ulang', [...$data, 'email' => 'unknown@example.test'])
            ->assertRedirect('/karier/terima-kasih');
        Notification::assertSentOnDemandTimes(ApplicationEmailVerification::class, 2);
    }

    public function test_confirmation_post_requires_csrf(): void
    {
        Notification::fake();
        $a = $this->submit();
        $link = $this->link($a);
        $this->app->detectEnvironment(fn () => 'local');
        $this->post($link)->assertStatus(419);
        $this->assertNull($a->fresh()->email_verified_at);
    }

    public function test_verified_archived_or_changed_email_suppresses_stale_jobs_and_resend_is_limited(): void
    {
        Notification::fake();
        $this->withoutVite();
        $a = $this->submit();
        $n = new ApplicationEmailVerification($a->id, hash('sha256', $a->applicant->email));
        $recipient = Notification::route('mail', $a->applicant->email);
        $this->assertTrue($n->shouldSend($recipient, 'mail'));
        $this->post($this->link($a))->assertRedirect('/karier/email-terverifikasi');
        $this->assertFalse($n->shouldSend($recipient, 'mail'));
        $receipt = new ApplicationReceived($a->id, $n->emailHash);
        $this->assertTrue($receipt->shouldSend($recipient, 'mail'));
        $this->travel(11)->minutes();
        for ($i = 0; $i < 3; $i++) {
            $this->post('/karier/verifikasi/kirim-ulang', ['email' => $a->applicant->email])->assertRedirect('/karier/terima-kasih');
        }
        $this->post('/karier/verifikasi/kirim-ulang', ['email' => $a->applicant->email])->assertStatus(429);
        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        $this->post('/karier/verifikasi/kirim-ulang', ['email' => $a->applicant->email])->assertStatus(429);
        Notification::assertSentOnDemandTimes(ApplicationEmailVerification::class, 1);
        $a->applicant->update(['email' => 'changed@example.test']);
        $this->assertFalse($receipt->shouldSend($recipient, 'mail'));
        $a->delete();
        $this->assertFalse($receipt->shouldSend($recipient, 'mail'));
    }
}
