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

class DisciplineTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_be_reassigned_and_inactive_employee_cannot_receive_new_case(): void
    {
        $this->setupHr();
        $id = $this->record();
        $other = Employee::first()->replicate();
        $other->employee_number = 'DSP-002';
        $other->work_email = 'dsp2@example.test';
        $other->save();
        $this->put('/discipline/'.$id, $this->data(['employee_id' => $other->id, 'reason' => 'Mengalihkan catatan pegawai']))->assertSessionHasErrors('employee_id');
        $other->update(['employment_status' => 'inactive']);
        $this->post('/discipline', $this->data(['employee_id' => $other->id, 'reference_number' => 'HR/002']))->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('disciplinary_cases', 1);
    }

    private function setupHr(): User
    {
        $this->withoutVite();
        Storage::fake('discipline_documents');
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($hr);
        $d = Department::create(['code' => 'DSP', 'name' => 'Operasional']);
        $p = Position::create(['department_id' => $d->id, 'name' => 'Staf']);
        Employee::create(['employee_number' => 'DSP-001', 'full_name' => 'Pegawai Satu', 'work_email' => 'dsp@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'permanent', 'join_date' => '2026-01-01']);

        return $hr;
    }

    private function data(array $extra = []): array
    {
        return [...['employee_id' => Employee::withTrashed()->first()->id, 'reference_number' => 'HR/SP/001', 'kind' => 'violation', 'incident_on' => now('Asia/Jakarta')->subDay()->toDateString(), 'issued_on' => now('Asia/Jakarta')->subDay()->toDateString(), 'subject' => 'Ketidaksesuaian prosedur', 'description' => 'Catatan kejadian yang ditinjau tim HR.', 'follow_up_on' => now('Asia/Jakarta')->addWeek()->toDateString(), 'reason' => '', 'version' => 1], ...$extra];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('surat.pdf', "%PDF-1.4\n%%EOF");
    }

    private function record(array $extra = []): int
    {
        $this->post('/discipline', $this->data($extra))->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('disciplinary_cases')->max('id');
    }

    public function test_cru_requires_reason_version_and_preserves_actor_audit(): void
    {
        $hr = $this->setupHr();
        $id = $this->record();
        $this->get('/discipline/create')->assertOk();
        $this->get('/discipline/'.$id.'/edit')->assertOk();
        $this->put('/discipline/'.$id, $this->data(['subject' => 'Koreksi judul']))->assertSessionHasErrors('reason')->assertRedirect('/discipline/'.$id.'/edit');
        $this->put('/discipline/'.$id, $this->data(['subject' => 'Koreksi judul', 'reason' => 'Memperbaiki catatan awal']))->assertSessionHasNoErrors();
        $this->put('/discipline/'.$id, $this->data(['reason' => 'Formulir versi sebelumnya']))->assertSessionHasErrors('version');
        $this->assertDatabaseHas('disciplinary_cases', ['id' => $id, 'subject' => 'Koreksi judul', 'version' => 2]);
        $this->assertDatabaseCount('discipline_events', 2);
        $this->assertDatabaseHas('discipline_events', ['actor_id' => $hr->id, 'action' => 'updated']);
        $this->get('/discipline?q=Koreksi')->assertInertia(fn (Assert $p) => $p->where('cases.total', 1));
        $this->delete('/discipline/'.$id)->assertStatus(405);
    }

    public function test_sp_requires_valid_private_pdf_and_keeps_versions(): void
    {
        $this->setupHr();
        $this->post('/discipline', $this->data(['kind' => 'sp1']))->assertSessionHasErrors('document')->assertRedirect('/discipline/create');
        $this->post('/discipline', $this->data(['kind' => 'sp1', 'document' => UploadedFile::fake()->createWithContent('x.pdf', 'not PDF')]))->assertSessionHasErrors('document');
        $this->post('/discipline', $this->data(['kind' => 'sp1', 'document' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')]))->assertSessionHasErrors('document');
        $id = $this->record(['kind' => 'sp1', 'document' => $this->pdf()]);
        $this->post('/discipline/'.$id.'/documents', ['document' => $this->pdf()])->assertRedirect('/discipline/'.$id)->assertSessionHasNoErrors();
        $docs = DB::table('discipline_documents')->orderBy('version')->get();
        $this->assertSame([1, 2], $docs->pluck('version')->all());
        $this->get('/discipline/'.$id.'/documents/'.$docs[0]->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->record(['reference_number' => 'HR/K/002', 'kind' => 'counseling']);
        $this->get('/discipline/'.$other.'/documents/'.$docs[0]->id)->assertNotFound();
        Storage::disk('discipline_documents')->delete($docs[0]->path);
        $this->get('/discipline/'.$id.'/documents/'.$docs[0]->id)->assertNotFound();
    }

    public function test_viewer_and_guest_cannot_read_or_modify_sensitive_records(): void
    {
        $this->setupHr();
        $id = $this->record(['kind' => 'sp2', 'document' => $this->pdf()]);
        $doc = DB::table('discipline_documents')->sole();
        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        foreach (['/discipline', '/discipline/create', '/discipline/'.$id, '/discipline/'.$id.'/edit', '/discipline/'.$id.'/documents/'.$doc->id] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/discipline', $this->data())->assertForbidden();
        $this->put('/discipline/'.$id, $this->data())->assertForbidden();
        $this->post('/discipline/'.$id.'/status')->assertForbidden();
        $this->post('/discipline/'.$id.'/documents')->assertForbidden();
        $this->post('/logout');
        $this->get('/discipline')->assertRedirect('/login');
    }

    public function test_validation_and_closure_do_not_erase_records_or_change_employee(): void
    {
        $this->setupHr();
        $this->post('/discipline', $this->data(['incident_on' => now('Asia/Jakarta')->addDay()->toDateString()]))->assertSessionHasErrors('incident_on');
        $this->post('/discipline', $this->data(['follow_up_on' => '2020-01-01']))->assertSessionHasErrors('follow_up_on');
        $id = $this->record();
        $this->put('/discipline/'.$id, $this->data(['kind' => 'sp3', 'reason' => 'Mengganti jenis catatan']))->assertSessionHasErrors('kind');
        $this->post('/discipline/'.$id.'/status', ['version' => 1, 'status' => 'resolved', 'reason' => ''])->assertSessionHasErrors('reason')->assertRedirect('/discipline/'.$id);
        $this->post('/discipline/'.$id.'/status', ['version' => 1, 'status' => 'resolved', 'reason' => 'Hasil konseling sudah disepakati'])->assertSessionHasNoErrors();
        $this->post('/discipline/'.$id.'/status', ['version' => 1, 'status' => 'voided', 'reason' => 'Salah mencatat kejadian'])->assertSessionHasErrors('version');
        $this->put('/discipline/'.$id, $this->data(['version' => 2, 'reason' => 'Koreksi catatan selesai']))->assertSessionHasErrors('case');
        $this->post('/discipline/'.$id.'/documents', ['document' => $this->pdf()])->assertSessionHasErrors('case');
        $this->post('/discipline/'.$id.'/status', ['version' => 2, 'status' => 'voided', 'reason' => 'Catatan salah dimasukkan'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('disciplinary_cases', ['id' => $id, 'status' => 'voided', 'version' => 3]);
        $this->assertDatabaseCount('discipline_events', 3);
        $e = Employee::first();
        $e->delete();
        $this->get('/discipline/'.$id)->assertInertia(fn (Assert $p) => $p->where('case.employee_archived', true));
        $this->post('/discipline', $this->data(['employee_id' => $e->id, 'reference_number' => 'HR/002']))->assertSessionHasErrors('employee_id');
    }

    public function test_audit_failure_rolls_back_sp_and_cleans_uploaded_evidence(): void
    {
        $this->setupHr();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $q) {
            if (str_contains($q->sql, 'insert into `discipline_events`')) {
                throw new \RuntimeException('audit failed');
            }
        });
        try {
            $this->post('/discipline', $this->data(['kind' => 'sp3', 'document' => $this->pdf()]));
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit failed', $e->getMessage());
        }
        $this->assertDatabaseCount('disciplinary_cases', 0);
        $this->assertDatabaseCount('discipline_documents', 0);
        $this->assertSame([], Storage::disk('discipline_documents')->allFiles());
    }

    public function test_warning_requires_issue_date_and_defaults_validity_to_six_months(): void
    {
        $this->setupHr();
        $this->post('/discipline', $this->data(['kind' => 'sp1', 'issued_on' => null, 'document' => $this->pdf()]))->assertSessionHasErrors('issued_on');
        $this->post('/discipline', $this->data(['kind' => 'sp1', 'issued_on' => now('Asia/Jakarta')->addDay()->toDateString(), 'document' => $this->pdf()]))->assertSessionHasErrors('issued_on');
        $this->post('/discipline', $this->data(['kind' => 'sp1', 'incident_on' => now('Asia/Jakarta')->subDays(3)->toDateString(), 'issued_on' => now('Asia/Jakarta')->subDays(5)->toDateString(), 'document' => $this->pdf()]))->assertSessionHasErrors('issued_on');
        $issued = now('Asia/Jakarta')->subDay()->toDateString();
        $this->post('/discipline', $this->data(['kind' => 'sp1', 'valid_until' => $issued, 'document' => $this->pdf()]))->assertSessionHasErrors('valid_until');
        $this->assertDatabaseCount('disciplinary_cases', 0);

        $id = $this->record(['kind' => 'sp1', 'document' => $this->pdf()]);
        $row = DB::table('disciplinary_cases')->find($id);
        $this->assertSame($issued, $row->issued_on);
        $this->assertSame(now('Asia/Jakarta')->subDay()->addMonthsNoOverflow(6)->toDateString(), $row->valid_until);

        $custom = now('Asia/Jakarta')->addMonths(3)->toDateString();
        $other = $this->record(['kind' => 'sp1', 'reference_number' => 'HR/SP/002', 'valid_until' => $custom, 'document' => $this->pdf()]);
        $this->assertSame($custom, DB::table('disciplinary_cases')->find($other)->valid_until);
    }

    public function test_non_warning_records_do_not_store_validity_dates(): void
    {
        $this->setupHr();
        $id = $this->record(['kind' => 'violation', 'valid_until' => now('Asia/Jakarta')->addMonth()->toDateString()]);
        $row = DB::table('disciplinary_cases')->find($id);
        $this->assertNull($row->issued_on);
        $this->assertNull($row->valid_until);
        $this->get('/discipline/'.$id)->assertInertia(fn (Assert $p) => $p->where('case.validity', null));
    }

    public function test_validity_status_is_active_expired_or_unset_for_legacy_rows(): void
    {
        $this->setupHr();
        $old = now('Asia/Jakarta')->subMonths(8)->toDateString();
        $expired = $this->record(['kind' => 'sp1', 'reference_number' => 'HR/SP/OLD', 'incident_on' => $old, 'issued_on' => $old, 'document' => $this->pdf()]);
        $active = $this->record(['kind' => 'sp1', 'reference_number' => 'HR/SP/NEW', 'document' => $this->pdf()]);
        $legacy = $this->record(['kind' => 'sp1', 'reference_number' => 'HR/SP/LEG', 'document' => $this->pdf()]);
        DB::table('disciplinary_cases')->where('id', $legacy)->update(['issued_on' => null, 'valid_until' => null]);

        $this->get('/discipline/'.$expired)->assertInertia(fn (Assert $p) => $p->where('case.validity', 'expired')->where('case.validity_label', 'Kedaluwarsa'));
        $this->get('/discipline/'.$active)->assertInertia(fn (Assert $p) => $p->where('case.validity', 'active'));
        $this->get('/discipline/'.$legacy)->assertInertia(fn (Assert $p) => $p->where('case.validity', 'unset'));
        $this->put('/discipline/'.$legacy, $this->data(['kind' => 'sp1', 'issued_on' => null, 'reason' => 'Melengkapi tanggal terbit', 'version' => 1]))->assertSessionHasErrors('issued_on');
        $this->put('/discipline/'.$legacy, $this->data(['kind' => 'sp1', 'reference_number' => 'HR/SP/LEG', 'reason' => 'Melengkapi tanggal terbit', 'version' => 1]))->assertSessionHasNoErrors();
        $this->get('/discipline/'.$legacy)->assertInertia(fn (Assert $p) => $p->where('case.validity', 'active'));
    }

    public function test_follow_up_warning_without_active_previous_level_warns_but_still_saves(): void
    {
        $this->setupHr();
        $this->post('/discipline', $this->data(['kind' => 'sp2', 'reference_number' => 'HR/SP/201', 'document' => $this->pdf()]))
            ->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->assertDatabaseCount('disciplinary_cases', 1);

        $this->post('/discipline', $this->data(['kind' => 'sp1', 'reference_number' => 'HR/SP/101', 'document' => $this->pdf()]))
            ->assertSessionHasNoErrors()->assertSessionMissing('warning');
        $this->post('/discipline', $this->data(['kind' => 'sp2', 'reference_number' => 'HR/SP/202', 'document' => $this->pdf()]))
            ->assertSessionHasNoErrors()->assertSessionMissing('warning');

        DB::table('disciplinary_cases')->where('reference_number', 'HR/SP/101')->update(['status' => 'voided']);
        $this->post('/discipline', $this->data(['kind' => 'sp2', 'reference_number' => 'HR/SP/203', 'document' => $this->pdf()]))
            ->assertSessionHasNoErrors()->assertSessionHas('warning');
        $this->post('/discipline', $this->data(['kind' => 'sp3', 'reference_number' => 'HR/SP/301', 'document' => $this->pdf()]))
            ->assertSessionHasNoErrors()->assertSessionMissing('warning');
    }
}
