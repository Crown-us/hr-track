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

class TrainingTest extends TestCase
{
    use RefreshDatabase;

    private function setupHr(): User
    {
        $this->withoutVite();
        Storage::fake('training_materials');
        $hr = User::factory()->create(['role' => 'hr_staff']);
        $this->actingAs($hr);

        return $hr;
    }

    private function data(array $extra = []): array
    {
        return [...['title' => 'Komunikasi Efektif', 'trainer' => 'Tim Pengembangan SDM', 'location' => 'Ruang Pelatihan', 'starts_at' => now('Asia/Jakarta')->subDays(2)->setTime(9, 0)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->subDays(2)->setTime(11, 0)->format('Y-m-d\TH:i'), 'notes' => 'Catatan internal HR'], ...$extra];
    }

    private function employee(int $n = 1): Employee
    {
        $d = Department::firstOrCreate(['code' => 'TRN'], ['name' => 'Pengembangan SDM']);
        $p = Position::firstOrCreate(['department_id' => $d->id, 'name' => 'Staf']);

        return Employee::create(['employee_number' => 'TRN-'.$n, 'full_name' => 'Peserta '.$n, 'work_email' => 'training.'.$n.'@example.test', 'department_id' => $d->id, 'position_id' => $p->id, 'employment_status' => 'contract', 'join_date' => now()->toDateString()]);
    }

    private function training(): int
    {
        $this->post('/trainings', $this->data())->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('trainings')->sole()->id;
    }

    public function test_create_update_wib_material_versions_and_private_access(): void
    {
        $this->setupHr();
        $id = $this->training();
        $this->assertSame('02:00:00', substr(DB::table('trainings')->where('id', $id)->value('starts_at'), 11));
        $this->put('/trainings/'.$id, $this->data(['version' => 1, 'title' => 'Komunikasi Tim']))->assertSessionHasNoErrors();
        $this->put('/trainings/'.$id, $this->data(['version' => 1]))->assertSessionHasErrors('version');
        foreach ([1, 2] as $version) {
            $this->post('/trainings/'.$id.'/materials', ['material' => UploadedFile::fake()->createWithContent('materi.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors();
        }
        $material = DB::table('training_materials')->first();
        Storage::disk('training_materials')->assertExists($material->path);
        $this->assertDatabaseCount('training_materials', 2);
        $this->get('/trainings/'.$id.'/materials/'.$material->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create(['role' => 'viewer']));
        $this->get('/trainings/'.$id)->assertInertia(fn (Assert $p) => $p->has('participants.data', 0)->has('materials', 0)->has('events', 0)->missing('training.notes')->missing('summary.average_gain'));
        $this->get('/trainings/'.$id.'/materials/'.$material->id)->assertForbidden();
        $this->post('/trainings', $this->data())->assertForbidden();
        $this->post('/logout');
        $this->get('/trainings')->assertRedirect('/login');
    }

    public function test_participants_evaluation_and_paired_summary_include_zero_baseline(): void
    {
        $this->setupHr();
        $id = $this->training();
        foreach ([1, 2, 3] as $n) {
            $this->post('/trainings/'.$id.'/participants', ['employee_id' => $this->employee($n)->id, 'version' => $n])->assertSessionHasNoErrors();
        }
        $participants = DB::table('training_participants')->orderBy('id')->get();
        $this->put('/trainings/'.$id.'/participants/'.$participants[0]->id, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 0, 'post_test' => 80])->assertSessionHasNoErrors();
        $this->put('/trainings/'.$id.'/participants/'.$participants[1]->id, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 60, 'post_test' => 90])->assertSessionHasNoErrors();
        $this->put('/trainings/'.$id.'/participants/'.$participants[2]->id, ['version' => 1, 'attendance' => 'registered', 'pre_test' => 100, 'post_test' => null])->assertSessionHasNoErrors();
        $this->get('/trainings/'.$id)->assertInertia(fn (Assert $p) => $p->where('summary.registered', 3)->where('summary.evaluated', 2)->where('summary.average_pre', 30)->where('summary.average_post', 85)->where('summary.average_gain', 55));
        $this->put('/trainings/'.$id.'/participants/'.$participants[0]->id, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 20, 'post_test' => 90])->assertSessionHasErrors('version');
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $participants[0]->employee_id, 'version' => 4])->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('training_participants', 3);
    }

    public function test_invalid_eligibility_scores_and_cross_training_records_are_rejected(): void
    {
        $this->setupHr();
        $id = $this->training();
        $employee = $this->employee();
        $employee->delete();
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $employee->id, 'version' => 1])->assertSessionHasErrors('employee_id');
        $employee->restore();
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $employee->id, 'version' => 1])->assertSessionHasNoErrors();
        $p = DB::table('training_participants')->sole();
        $url = '/trainings/'.$id.'/participants/'.$p->id;
        $this->put($url, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 101, 'post_test' => -1])->assertSessionHasErrors(['pre_test', 'post_test']);
        $this->put($url, ['version' => 1, 'attendance' => 'absent', 'pre_test' => 30, 'post_test' => 80])->assertSessionHasErrors('post_test');
        $this->put($url, ['version' => 1, 'attendance' => 'attended', 'pre_test' => null, 'post_test' => 80])->assertSessionHasErrors('pre_test');
        $this->post('/trainings', $this->data(['title' => 'Pelatihan Lain']))->assertSessionHasNoErrors();
        $other = DB::table('trainings')->max('id');
        $this->put('/trainings/'.$other.'/participants/'.$p->id, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 30, 'post_test' => 80])->assertNotFound();
    }

    public function test_cancellation_and_completion_protect_history_and_future_post_tests(): void
    {
        $this->setupHr();
        $this->post('/trainings', $this->data(['starts_at' => now('Asia/Jakarta')->addDays(2)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->addDays(2)->addHours(2)->format('Y-m-d\TH:i')]))->assertSessionHasNoErrors();
        $id = DB::table('trainings')->sole()->id;
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $this->employee()->id, 'version' => 1])->assertSessionHasNoErrors();
        $p = DB::table('training_participants')->sole();
        $this->put('/trainings/'.$id.'/participants/'.$p->id, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 30, 'post_test' => 80])->assertSessionHasErrors('post_test');
        $this->post('/trainings/'.$id.'/status', ['version' => 2, 'status' => 'completed'])->assertSessionHasErrors('status');
        $this->post('/trainings/'.$id.'/status', ['version' => 2, 'status' => 'cancelled'])->assertSessionHasErrors('reason');
        $this->post('/trainings/'.$id.'/status', ['version' => 2, 'status' => 'cancelled', 'reason' => 'Jadwal disesuaikan dengan kegiatan tim.'])->assertSessionHasNoErrors();
        $this->put('/trainings/'.$id.'/participants/'.$p->id, ['version' => 1, 'attendance' => 'registered', 'pre_test' => 30])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('trainings', ['id' => $id, 'status' => 'cancelled']);
        $this->assertDatabaseCount('training_participants', 1);
    }

    public function test_completed_training_allows_evaluation_of_archived_participant_but_no_schedule_change(): void
    {
        $this->setupHr();
        $id = $this->training();
        $employee = $this->employee();
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $employee->id, 'version' => 1])->assertSessionHasNoErrors();
        $this->post('/trainings/'.$id.'/status', ['version' => 2, 'status' => 'completed'])->assertSessionHasNoErrors();
        $employee->delete();
        $p = DB::table('training_participants')->sole();
        $this->put('/trainings/'.$id.'/participants/'.$p->id, ['version' => 1, 'attendance' => 'attended', 'pre_test' => 80, 'post_test' => 70])->assertSessionHasNoErrors();
        $this->get('/trainings/'.$id)->assertInertia(fn (Assert $p) => $p->where('participants.data.0.archived', true)->where('summary.average_gain', -10));
        $this->put('/trainings/'.$id, $this->data(['version' => 3]))->assertSessionHasErrors('status');
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $this->employee(2)->id, 'version' => 3])->assertSessionHasErrors('status');
    }

    public function test_invalid_schedule_pdf_and_material_ownership_are_rejected(): void
    {
        $this->setupHr();
        $this->post('/trainings', $this->data(['ends_at' => now()->subWeek()->format('Y-m-d\TH:i')]))->assertSessionHasErrors('ends_at');
        $id = $this->training();
        $this->post('/trainings/'.$id.'/materials', ['material' => UploadedFile::fake()->createWithContent('fake.pdf', 'not PDF')])->assertSessionHasErrors('material');
        $this->post('/trainings/'.$id.'/materials', ['material' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('material');
        $this->post('/trainings/'.$id.'/materials', ['material' => UploadedFile::fake()->createWithContent('materi.pdf', "%PDF-1.4\n%%EOF")])->assertSessionHasNoErrors();
        $m = DB::table('training_materials')->sole();
        $this->post('/trainings', $this->data(['title' => 'Pelatihan kedua']))->assertSessionHasNoErrors();
        $other = DB::table('trainings')->max('id');
        $this->get('/trainings/'.$other.'/materials/'.$m->id)->assertNotFound();
        $this->get('/trainings?q=Pelatihan%20kedua')->assertInertia(fn (Assert $p) => $p->where('trainings.total', 1));
        $employee = $this->employee();
        $this->post('/trainings/'.$id.'/participants', ['employee_id' => $employee->id, 'version' => 1])->assertSessionHasNoErrors();
        $participant = DB::table('training_participants')->sole();
        $this->put('/trainings/'.$id.'/participants/'.$participant->id, ['version' => 1, 'attendance' => 'registered', 'pre_test' => 20])->assertSessionHasNoErrors();
        $this->put('/trainings/'.$id, $this->data(['version' => 2, 'starts_at' => now('Asia/Jakarta')->subDays(1)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->subDays(1)->addHours(2)->format('Y-m-d\TH:i')]))->assertSessionHasErrors('starts_at');
        $this->put('/trainings/'.$id.'/participants/'.$participant->id, ['version' => 2, 'attendance' => 'registered', 'pre_test' => null, 'post_test' => null])->assertSessionHasNoErrors();
        $this->put('/trainings/'.$id, $this->data(['version' => 2, 'starts_at' => now('Asia/Jakarta')->subDays(1)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Jakarta')->subDays(1)->addHours(2)->format('Y-m-d\TH:i')]))->assertSessionHasErrors('starts_at');
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->put('/trainings/'.$id.'/participants/'.$participant->id, ['version' => 2, 'attendance' => 'attended', 'pre_test' => 20, 'post_test' => 80])->assertForbidden();
    }

    public function test_failed_material_audit_rolls_back_file_and_document(): void
    {
        $this->setupHr();
        $id = $this->training();
        $this->withoutExceptionHandling();
        DB::listen(function (QueryExecuted $q) {
            if (str_starts_with($q->sql, 'insert into `training_events`')) {
                throw new \RuntimeException('Training audit failed');
            }
        });
        try {
            $this->post('/trainings/'.$id.'/materials', ['material' => UploadedFile::fake()->createWithContent('materi.pdf', "%PDF-1.4\n%%EOF")]);
            $this->fail('Audit failure must abort material upload.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Training audit failed', $e->getMessage());
        }
        $this->assertDatabaseCount('training_materials', 0);
        $this->assertDatabaseCount('training_events', 1);
        $this->assertCount(0, Storage::disk('training_materials')->allFiles());
    }
}
