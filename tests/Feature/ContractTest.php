<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private function hr(): User
    {
        $this->withoutVite();
        Storage::fake('contract_documents');
        $u = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($u);

        return $u;
    }

    private function employee(int $n = 1): Employee
    {
        $d = Department::firstOrCreate(['code' => 'CTR'], ['name' => 'Operasional']);
        $p = Position::firstOrCreate(['department_id' => $d->id, 'name' => 'Staf']);

        return Employee::create(['employee_number' => 'CTR-'.$n, 'full_name' => 'Pegawai '.$n, 'work_email' => 'ctr'.$n.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'contract', 'join_date' => '2026-01-01']);
    }

    private function data(array $extra = []): array
    {
        return [...['employee_id' => Employee::first()->id, 'contract_number' => 'HR/2026/001', 'type' => 'pkwt', 'starts_on' => '2026-01-01', 'ends_on' => '2026-11-30', 'notes' => 'Catatan privat'], ...$extra];
    }

    private function create(array $extra = []): int
    {
        $this->post('/contracts', $this->data($extra))->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('employee_contracts')->max('id');
    }

    public function test_contract_cru_keeps_audit_and_rejects_stale_or_unexplained_changes(): void
    {
        $this->hr();
        $employee = $this->employee();
        $id = $this->create();
        $this->get('/contracts/create')->assertOk();
        $this->get('/contracts/'.$id.'/edit')->assertOk();
        $this->put('/contracts/'.$id, $this->data(['version' => 1, 'ends_on' => '2026-12-01']))->assertSessionHasErrors('reason');
        $this->put('/contracts/'.$id, $this->data(['version' => 1, 'ends_on' => '2026-12-01', 'reason' => 'Koreksi tanggal sesuai dokumen']))->assertSessionHasNoErrors();
        $this->put('/contracts/'.$id, $this->data(['version' => 1, 'reason' => 'Koreksi formulir yang lama']))->assertSessionHasErrors('version');
        $this->assertDatabaseHas('employee_contracts', ['id' => $id, 'employee_id' => $employee->id, 'ends_on' => '2026-12-01', 'version' => 2]);
        $this->assertDatabaseCount('contract_events', 2);
        $this->get('/contracts?q=HR%2F2026')->assertInertia(fn (Assert $p) => $p->where('contracts.total', 1));
        $this->get('/contracts?q=TidakAda')->assertInertia(fn (Assert $p) => $p->where('contracts.total', 0));
        $this->delete('/contracts/'.$id)->assertStatus(405);
    }

    public function test_period_rules_overlap_identity_and_employee_eligibility(): void
    {
        $this->hr();
        $e = $this->employee();
        $this->post('/contracts', $this->data(['ends_on' => null]))->assertSessionHasErrors('ends_on');
        $this->post('/contracts', $this->data(['ends_on' => '2025-12-31']))->assertSessionHasErrors('ends_on');
        $this->post('/contracts', $this->data(['type' => 'pkwtt']))->assertSessionHasErrors('ends_on');
        $id = $this->create();
        $this->post('/contracts', $this->data(['contract_number' => 'CTR-2', 'starts_on' => '2026-11-30', 'ends_on' => '2027-01-01']))->assertSessionHasErrors('starts_on');
        $this->create(['contract_number' => 'CTR-2', 'starts_on' => '2026-12-01', 'ends_on' => '2027-01-01']);
        $other = $this->employee(2);
        $this->put('/contracts/'.$id, $this->data(['employee_id' => $other->id, 'version' => 1, 'reason' => 'Pindah data ke pegawai lain']))->assertSessionHasErrors('employee_id');
        $this->post('/contracts', $this->data(['employee_id' => $other->id]))->assertSessionHasErrors('contract_number');
        $other->update(['employment_status' => 'inactive']);
        $this->post('/contracts', $this->data(['employee_id' => $other->id, 'contract_number' => 'CTR-3']))->assertSessionHasErrors('employee_id');
        $e->delete();
        $this->post('/contracts', $this->data(['employee_id' => $e->id, 'contract_number' => 'CTR-4']))->assertSessionHasErrors('employee_id');
        $this->get('/contracts/'.$id)->assertOk();
    }

    public function test_permanent_contract_and_cancellation_preserve_history(): void
    {
        $this->hr();
        $this->employee();
        $id = $this->create(['type' => 'pkwtt', 'ends_on' => null]);
        $this->post('/contracts', $this->data(['contract_number' => 'CTR-2', 'starts_on' => '2027-01-01', 'ends_on' => '2028-01-01']))->assertSessionHasErrors('starts_on');
        $this->post('/contracts/'.$id.'/void', ['version' => 1])->assertSessionHasErrors('reason');
        $this->post('/contracts/'.$id.'/void', ['version' => 1, 'reason' => 'Catatan kontrak salah dimasukkan'])->assertSessionHasNoErrors();
        $this->put('/contracts/'.$id, $this->data(['version' => 2, 'reason' => 'Koreksi setelah pembatalan']))->assertSessionHasErrors('contract');
        $this->assertDatabaseCount('employee_contracts', 1);
        $this->assertDatabaseCount('contract_events', 2);
        $this->create(['contract_number' => 'CTR-2']);
    }

    public function test_roles_and_private_versioned_documents(): void
    {
        $hr = $this->hr();
        $this->employee();
        $id = $this->create();
        $this->post('/contracts/'.$id.'/documents', ['document' => UploadedFile::fake()->createWithContent('x.pdf', 'not pdf')])->assertSessionHasErrors('document');
        $this->post('/contracts/'.$id.'/documents', ['document' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('document');
        foreach ([1, 2] as $v) {
            $this->post('/contracts/'.$id.'/documents', ['document' => UploadedFile::fake()->createWithContent('kontrak.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors();
        }
        $docs = DB::table('contract_documents')->orderBy('version')->get();
        $this->assertSame([1, 2], $docs->pluck('version')->all());
        $this->get('/contracts/'.$id.'/documents/'.$docs[0]->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->employee(2);
        $other = $this->create(['employee_id' => Employee::latest('id')->first()->id, 'contract_number' => 'CTR-2']);
        $this->get('/contracts/'.$other.'/documents/'.$docs[0]->id)->assertNotFound();
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);
        $this->get('/contracts/'.$id)->assertInertia(fn (Assert $p) => $p->missing('contract.notes')->has('documents', 0)->has('events', 0));
        $this->get('/contracts/create')->assertForbidden();
        $this->post('/contracts', $this->data())->assertForbidden();
        $this->put('/contracts/'.$id, $this->data())->assertForbidden();
        $this->post('/contracts/'.$id.'/void')->assertForbidden();
        $this->post('/contracts/'.$id.'/documents')->assertForbidden();
        $this->get('/contracts/'.$id.'/documents/'.$docs[0]->id)->assertForbidden();
        $this->actingAs($hr);
        $this->post('/logout');
        $this->get('/contracts')->assertRedirect('/login');
    }

    public function test_wib_alert_window_is_inclusive_and_excludes_void_future_and_inactive_records(): void
    {
        $this->hr();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(18, 30)); // 2 November WIB
        foreach (['2026-11-01', '2026-11-02', '2026-12-02', '2026-12-03'] as $n => $end) {
            $e = $this->employee($n + 1);
            $this->create(['employee_id' => $e->id, 'contract_number' => 'AL-'.$n, 'ends_on' => $end]);
        }
        $e = $this->employee(5);
        $id = $this->create(['employee_id' => $e->id, 'contract_number' => 'VOID', 'ends_on' => '2026-11-10']);
        $this->post('/contracts/'.$id.'/void', ['version' => 1, 'reason' => 'Salah input nomor kontrak']);
        $e = $this->employee(6);
        $this->create(['employee_id' => $e->id, 'contract_number' => 'INACTIVE', 'ends_on' => '2026-11-10']);
        $e->update(['employment_status' => 'inactive']);
        $e = $this->employee(7);
        $this->create(['employee_id' => $e->id, 'contract_number' => 'ARCHIVED', 'ends_on' => '2026-11-10']);
        $e->delete();
        $e = $this->employee(8);
        $this->create(['employee_id' => $e->id, 'contract_number' => 'FUTURE', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-30']);
        $this->get('/hr')->assertInertia(fn (Assert $p) => $p->where('contractSummary.due_count', 2)->where('contractSummary.expired_count', 1)->has('contractSummary.recent', 2));
        $this->get('/contracts?attention=due')->assertInertia(fn (Assert $p) => $p->where('contracts.total', 2));
        $this->get('/contracts?attention=expired')->assertInertia(fn (Assert $p) => $p->where('contracts.total', 1));
        $this->travelBack();
    }

    public function test_document_and_metadata_rollback_if_audit_fails(): void
    {
        $this->hr();
        $this->employee();
        $id = $this->create();
        DB::listen(function (QueryExecuted $q) {
            if (str_contains($q->sql, 'insert into `contract_events`')) {
                throw new \RuntimeException('audit failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/contracts/'.$id.'/documents', ['document' => UploadedFile::fake()->createWithContent('k.pdf', "%PDF-1.4\n%%EOF")]);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('contract_documents', 0);
        $this->assertSame([], Storage::disk('contract_documents')->allFiles());
    }

    public function test_correcting_to_permanent_clears_previous_end_date_even_when_field_is_omitted(): void
    {
        $this->hr();
        $this->employee();
        $id = $this->create();
        $data = $this->data(['type' => 'pkwtt', 'version' => 1, 'reason' => 'Koreksi jenis sesuai dokumen']);
        unset($data['ends_on']);
        $this->put('/contracts/'.$id, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('employee_contracts', ['id' => $id, 'type' => 'pkwtt', 'ends_on' => null]);
    }

    public function test_create_accepts_exact_browser_payload_with_empty_correction_reason(): void
    {
        $this->hr();
        $this->employee();
        $this->create(['reason' => '', 'version' => 1]);
    }

    public function test_validation_and_actions_redirect_to_contract_pages_without_referer(): void
    {
        $this->hr();
        $this->employee();
        $this->post('/contracts', $this->data(['starts_on' => '']))->assertSessionHasErrors('starts_on')->assertRedirect('/contracts/create');
        $id = $this->create();
        $this->put('/contracts/'.$id, $this->data(['version' => 1, 'reason' => '']))->assertRedirect('/contracts/'.$id.'/edit');
        $this->post('/contracts/'.$id.'/documents')->assertSessionHasErrors('document')->assertRedirect('/contracts/'.$id);
        $this->post('/contracts/'.$id.'/void', ['version' => 1, 'reason' => ''])->assertSessionHasErrors('reason')->assertRedirect('/contracts/'.$id);
        $this->post('/contracts/'.$id.'/documents', ['document' => UploadedFile::fake()->createWithContent('k.pdf', "%PDF-1.4\n%%EOF")])->assertRedirect('/contracts/'.$id);
        $this->post('/contracts/'.$id.'/void', ['version' => 1, 'reason' => 'Pembatalan catatan yang salah'])->assertRedirect('/contracts/'.$id);
    }

    public function test_renewed_contracts_do_not_keep_triggering_reminders(): void
    {
        $this->hr();
        $e = $this->employee();
        $today = now('Asia/Jakarta')->startOfDay();
        $end = $today->copy()->addDays(15);
        $this->create(['starts_on' => $today->copy()->subYear()->toDateString(), 'ends_on' => $end->toDateString()]);
        $this->create(['contract_number' => 'RENEWAL', 'starts_on' => $end->copy()->addDay()->toDateString(), 'ends_on' => $end->copy()->addYear()->toDateString()]);
        $this->get('/hr')->assertInertia(fn (Assert $p) => $p->where('contractSummary.due_count', 0));
        $this->travelTo($end->copy()->addDays(2)->utc());
        $this->get('/hr')->assertInertia(fn (Assert $p) => $p->where('contractSummary.expired_count', 0));
        $this->travelBack();
    }

    public function test_period_expansion_stale_void_and_voided_upload_are_rejected(): void
    {
        $this->hr();
        $this->employee();
        $id = $this->create();
        $this->create(['contract_number' => 'RENEWAL', 'starts_on' => '2026-12-01', 'ends_on' => '2027-12-01']);
        $this->put('/contracts/'.$id, $this->data(['ends_on' => '2026-12-02', 'version' => 1, 'reason' => 'Koreksi salah tanggal akhir']))->assertSessionHasErrors('starts_on');
        $this->post('/contracts/'.$id.'/void', ['version' => 2, 'reason' => 'Batalkan catatan yang salah'])->assertSessionHasErrors('version');
        $this->post('/contracts/'.$id.'/void', ['version' => 1, 'reason' => 'Batalkan catatan yang salah'])->assertSessionHasNoErrors();
        $this->post('/contracts/'.$id.'/documents', ['document' => UploadedFile::fake()->createWithContent('k.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasErrors('contract');
        $this->assertDatabaseCount('contract_documents', 0);
        $this->assertSame([], Storage::disk('contract_documents')->allFiles());
    }
}
