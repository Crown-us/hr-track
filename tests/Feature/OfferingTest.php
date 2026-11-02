<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Application;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class OfferingTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Storage::fake('employee_records');
        $this->withoutVite();
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $d = Department::create(['code' => 'OFFER', 'name' => 'Departemen Uji']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf Uji']);
        $v = Vacancy::create(['department_id' => $d->id, 'job_title' => 'Posisi Uji', 'employment_type' => 'full_time', 'location' => 'Jakarta', 'description' => 'Deskripsi pekerjaan fiktif.', 'requirements' => 'Persyaratan pekerjaan fiktif.', 'opening_date' => now()->toDateString(), 'closing_date' => now()->addMonth()->toDateString(), 'status' => 'published']);
        $person = Applicant::create(['full_name' => 'Kandidat Fiktif', 'email' => 'offer@example.test', 'phone' => '08123456789']);
        $a = Application::create(['applicant_id' => $person->id, 'vacancy_id' => $v->id, 'application_date' => now()->toDateString()]);
        $a->status = 'offering';
        $a->save();
        $this->actingAs($hr);

        return [$a, $hr, ['department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'contract', 'join_date' => now('Asia/Jakarta')->addDays(14)->toDateString(), 'response_deadline' => now('Asia/Jakarta')->addDays(7)->toDateString(), 'terms' => 'Ketentuan internal fiktif.', 'version' => 1]];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('respons.pdf', "%PDF-1.4\n%%EOF");
    }

    private function response(int $version = 2, string $status = 'accepted'): array
    {
        return ['version' => $version, 'response' => $status, 'response_date' => now('Asia/Jakarta')->toDateString(), 'response_note' => 'Respons kandidat telah dikonfirmasi oleh HR.', 'evidence' => $this->pdf()];
    }

    private function conversion(int $version = 3): array
    {
        return ['version' => $version, 'employee_number' => 'UAT-001', 'work_email' => 'pegawai@example.test', 'full_name' => 'Nama Dikonfirmasi HR', 'phone' => '08123456789', 'identity_confirmed' => true];
    }

    public function test_offer_revision_response_and_conversion_preserve_audit_and_prevent_duplicates(): void
    {
        [$a, $hr, $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $this->get($url)->assertInertia(fn (Assert $p) => $p->component('offering', false)->where('identity.full_name', 'Kandidat Fiktif'));
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, [...$data, 'terms' => 'Revisi ketentuan internal fiktif.', 'version' => 2])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $data)->assertSessionHasErrors('version');
        $this->assertDatabaseCount('recruitment_offers', 1);
        $this->assertDatabaseCount('recruitment_offer_events', 2);
        $pdf = $this->get($url.'/summary')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->post($url.'/response', $this->response(3))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('applications', ['id' => $a->id, 'status' => 'accepted', 'version' => 4]);
        $this->assertDatabaseHas('application_status_histories', ['application_id' => $a->id, 'actor_id' => $hr->id, 'old_status' => 'offering', 'new_status' => 'accepted']);
        $this->get($url.'/evidence')->assertOk();
        $this->post($url.'/response', $this->response(4))->assertSessionHasErrors('response');
        $this->post($url.'/employee', $this->conversion(4))->assertSessionHasNoErrors()->assertRedirect();
        $employee = Employee::sole();
        $this->assertSame('Nama Dikonfirmasi HR', $employee->full_name);
        $this->assertSame($a->applicant_id, $employee->applicant_id);
        $this->assertSame($a->id, $employee->source_application_id);
        $this->assertSame($data['position_id'], $employee->position_id);
        $this->assertSame('contract', $employee->employment_status->value);
        $this->assertDatabaseHas('employee_events', ['employee_id' => $employee->id, 'actor_id' => $hr->id, 'action' => 'created']);
        $this->post($url.'/employee', $this->conversion(4))->assertSessionHasErrors('employee_number');
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('recruitment_offer_events', 4);
        $this->assertDatabaseHas('applicants', ['id' => $a->applicant_id, 'full_name' => 'Kandidat Fiktif']);
    }

    public function test_invalid_unverified_stale_and_unauthorized_actions_are_blocked(): void
    {
        [$a, $hr, $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $a->source = 'public';
        $a->save();
        $this->post($url, $data)->assertSessionHasErrors('status');
        $a->email_verified_at = now();
        $a->save();
        $other = Department::create(['code' => 'OTHER', 'name' => 'Departemen Lain']);
        $this->post($url, [...$data, 'department_id' => $other->id])->assertSessionHasErrors('position_id');
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url.'/response', [...$this->response(), 'evidence' => UploadedFile::fake()->createWithContent('fake.pdf', 'not PDF')])->assertSessionHasErrors('evidence');
        $this->post($url.'/employee', $this->conversion(2))->assertSessionHasErrors('status');
        $this->post('/applications/'.$a->id.'/status', ['status' => 'accepted', 'version' => 2])->assertSessionHasErrors('status');
        $this->assertDatabaseCount('employees', 0);
        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        foreach ([$url, $url.'/summary', $url.'/evidence'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post($url, $data)->assertForbidden();
        $this->post($url.'/response', $this->response())->assertForbidden();
        $this->post($url.'/employee', $this->conversion())->assertForbidden();
        $this->get('/applications/'.$a->id)->assertInertia(fn (Assert $p) => $p->missing('offering'));
        $this->post('/logout');
        $this->get($url)->assertRedirect('/login');
    }

    public function test_declined_response_keeps_evidence_and_cannot_create_employee(): void
    {
        [$a, , $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url.'/response', $this->response(2, 'declined'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('applications', ['id' => $a->id, 'status' => 'rejected']);
        $this->assertDatabaseHas('recruitment_offers', ['application_id' => $a->id, 'status' => 'declined']);
        $this->post($url.'/employee', $this->conversion())->assertSessionHasErrors('status');
        $this->get($url.'/evidence')->assertOk();
        $this->assertCount(1, Storage::disk('employee_records')->allFiles());
    }

    public function test_response_failure_rolls_back_status_and_removes_evidence(): void
    {
        [$a, , $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $q) {
            if (str_starts_with($q->sql, 'insert into `recruitment_offer_events`')) {
                throw new RuntimeException('Simulated audit failure');
            }
        });
        try {
            $this->post($url.'/response', $this->response());
            $this->fail('Audit failure must abort transaction.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated audit failure', $e->getMessage());
        }
        $this->assertDatabaseHas('applications', ['id' => $a->id, 'status' => 'offering', 'version' => 2]);
        $this->assertDatabaseHas('recruitment_offers', ['application_id' => $a->id, 'status' => 'pending', 'evidence_path' => null]);
        $this->assertCount(0, Storage::disk('employee_records')->allFiles());
    }

    public function test_archived_employee_blocks_another_conversion_for_the_same_applicant(): void
    {
        [$a, , $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url.'/response', $this->response())->assertSessionHasNoErrors();
        $this->post($url.'/employee', [...$this->conversion(), 'identity_confirmed' => false])->assertSessionHasErrors('identity_confirmed');
        $this->post($url.'/employee', $this->conversion())->assertSessionHasNoErrors();
        Employee::sole()->delete();
        $vacancy = $a->vacancy->replicate();
        $vacancy->save();
        $other = Application::create(['applicant_id' => $a->applicant_id, 'vacancy_id' => $vacancy->id, 'application_date' => now()->toDateString()]);
        $other->status = 'offering';
        $other->save();
        $otherUrl = '/applications/'.$other->id.'/offering';
        $this->post($otherUrl, $data)->assertSessionHasNoErrors();
        $this->post($otherUrl.'/response', $this->response())->assertSessionHasNoErrors();
        $this->post($otherUrl.'/employee', [...$this->conversion(), 'employee_number' => 'UAT-002', 'work_email' => 'other@example.test'])->assertSessionHasErrors('employee_number');
        $this->assertSame(1, Employee::withTrashed()->count());
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_conversion_audit_failure_rolls_back_employee_and_employee_history(): void
    {
        [$a, , $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url.'/response', $this->response())->assertSessionHasNoErrors();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $q) {
            if (str_starts_with($q->sql, 'insert into `recruitment_offer_events`')) {
                throw new RuntimeException('Simulated conversion audit failure');
            }
        });
        try {
            $this->post($url.'/employee', $this->conversion());
            $this->fail('Failed conversion audit must abort employee creation.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated conversion audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('employee_events', 0);
        $this->assertDatabaseCount('recruitment_offer_events', 2);
        $this->assertDatabaseHas('applications', ['id' => $a->id, 'status' => 'accepted']);
    }

    public function test_response_must_match_latest_revision_and_withdrawal_records_rejection(): void
    {
        [$a, , $data] = $this->scenario();
        $url = '/applications/'.$a->id.'/offering';
        $this->post($url, $data)->assertSessionHasNoErrors();
        $originalDate = now('Asia/Jakarta')->toDateString();
        $this->travel(1)->days();
        $this->post($url, [...$data, 'version' => 2])->assertSessionHasNoErrors();
        $this->post($url.'/response', [...$this->response(3), 'response_date' => $originalDate])->assertSessionHasErrors('response_date');
        $this->post($url.'/response', $this->response(3, 'withdrawn'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recruitment_offers', ['application_id' => $a->id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('applications', ['id' => $a->id, 'status' => 'rejected']);
        $this->assertCount(1, Storage::disk('employee_records')->allFiles());
    }
}
