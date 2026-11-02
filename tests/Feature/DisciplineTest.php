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
        return [...['employee_id' => Employee::withTrashed()->first()->id, 'reference_number' => 'HR/SP/001', 'kind' => 'violation', 'incident_on' => now('Asia/Jakarta')->subDay()->toDateString(), 'subject' => 'Ketidaksesuaian prosedur', 'description' => 'Catatan kejadian yang ditinjau tim HR.', 'follow_up_on' => now('Asia/Jakarta')->addWeek()->toDateString(), 'reason' => '', 'version' => 1], ...$extra];
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
}
