<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\Interview;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\ApplicationWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InterviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjacent_slots_are_allowed_but_same_applicant_other_interviewer_is_blocked(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $a = $this->application();
        $data = $this->data($a, $user);
        $this->actingAs($user)->post('/interviews', $data)->assertRedirect();
        $other = User::factory()->create(['role' => 'hr_staff']);
        $this->post('/interviews', [...$data, 'interviewer_id' => $other->id])->assertSessionHasErrors('starts_at');
        $after = CarbonImmutable::parse($data['starts_at'], 'Asia/Jakarta')->addMinutes(45)->format('Y-m-d\TH:i');
        $this->post('/interviews', [...$data, 'starts_at' => $after])->assertRedirect();
        $this->post('/interviews', [...$data, 'duration' => 14])->assertSessionHasErrors('duration');
        $this->post('/interviews', [...$data, 'starts_at' => now('Asia/Jakarta')->subMinute()->format('Y-m-d\TH:i')])->assertSessionHasErrors('starts_at');
        $i = Interview::firstOrFail();
        $this->post('/interviews/'.$i->id.'/cancel', ['version' => 1])->assertSessionHasErrors('reason');
        $this->post('/interviews/'.$i->id.'/cancel', ['version' => 99, 'reason' => 'Penyesuaian jadwal interview.'])->assertSessionHasErrors('version');
        $this->assertDatabaseCount('interviews', 2);
    }

    private function application(): Application
    {
        $dept = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);
        $v = Vacancy::create(['department_id' => $dept->id, 'job_title' => 'HR Staff', 'employment_type' => 'full_time', 'location' => 'Jakarta', 'description' => 'Deskripsi pengujian interview.', 'requirements' => 'Persyaratan pengujian interview.', 'opening_date' => '2026-01-01', 'closing_date' => '2027-12-31', 'status' => 'published']);
        $p = Applicant::create(['full_name' => 'Pelamar Fiktif', 'email' => uniqid().'@example.test']);
        $a = Application::create(['applicant_id' => $p->id, 'vacancy_id' => $v->id, 'application_date' => now()->toDateString()]);
        $a->status = 'interview';
        $a->save();

        return $a;
    }

    private function data(Application $app, User $user): array
    {
        return ['application_id' => $app->id, 'interviewer_id' => $user->id, 'starts_at' => now('Asia/Jakarta')->addDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'), 'duration' => 45, 'location' => 'Ruang HR', 'meeting_url' => '', 'notes' => 'Catatan rahasia'];
    }

    public function test_create_converts_wib_and_pdf_is_private(): void
    {
        $user = User::factory()->create(['role' => 'hr_staff']);
        $app = $this->application();
        $data = $this->data($app, $user);
        $this->actingAs($user)->post('/interviews', $data)->assertRedirect('/interviews');
        $interview = Interview::firstOrFail();
        $this->assertSame('02:00', $interview->starts_at->format('H:i'));
        $this->view('pdf.interview-invitation', ['interview' => $interview->load(['application.applicant', 'application.vacancy', 'interviewer'])])->assertSee('09:00')->assertDontSee('Catatan rahasia');
        $response = $this->get('/interviews/'.$interview->id.'/invitation')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->withoutVite()->get('/interviews')->assertInertia(fn (Assert $p) => $p->missing('interviews.data.0.notes')->missing('interviews.data.0.meeting_url'));
        $this->get('/interviews/'.$interview->id.'/invitation')->assertForbidden();
        $this->post('/interviews', $data)->assertForbidden();
        $this->post('/logout');
        $this->get('/interviews')->assertRedirect('/login');
    }

    public function test_overlap_invalid_status_and_invalid_location_are_rejected(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $a = $this->application();
        $data = $this->data($a, $user);
        $this->actingAs($user)->post('/interviews', $data)->assertRedirect();
        $this->post('/interviews', [...$data, 'application_id' => $this->application()->id])->assertSessionHasErrors('starts_at');
        $this->post('/interviews', [...$data, 'location' => '', 'meeting_url' => 'http://invalid.test'])->assertSessionHasErrors('meeting_url');
        $a->status = 'offering';
        $a->save();
        $this->post('/interviews', [...$data, 'starts_at' => now('Asia/Jakarta')->addDays(3)->format('Y-m-d\TH:i')])->assertSessionHasErrors('application_id');
    }

    public function test_cancellation_and_stage_change_preserve_and_cancel_future_schedule(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $a = $this->application();
        $data = $this->data($a, $user);
        $this->actingAs($user)->post('/interviews', $data);
        $i = Interview::firstOrFail();
        $this->post('/interviews/'.$i->id.'/cancel', ['reason' => 'Jadwal perlu disesuaikan kembali.', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('interviews', ['id' => $i->id, 'cancelled_by' => $user->id]);
        $this->post('/interviews', $data)->assertRedirect();
        app(ApplicationWorkflow::class)->transition($a, ['status' => 'offering', 'version' => 1], $user);
        $this->assertSame(0, Interview::whereNull('cancelled_at')->count());
        $this->get('/interviews/'.$i->id.'/invitation')->assertNotFound();
    }
}
