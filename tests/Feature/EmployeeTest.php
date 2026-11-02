<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\EmployeeRecords;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_audit_transaction_cleans_up_file_and_preserves_employee(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $actor = $this->user();
        $actor->id = 999999;
        $data = $this->archiveData($employee);
        try {
            app(EmployeeRecords::class)->archive($employee, $data, $data['document'], $actor);
            $this->fail('Missing audit actor must cause a database error.');
        } catch (QueryException) {
            $this->assertNotSoftDeleted($employee);
            $this->assertDatabaseCount('employee_events', 0);
            $this->assertCount(0, Storage::disk('employee_records')->allFiles());
        }
    }

    public function test_database_itself_rejects_a_position_from_another_department(): void
    {
        $employee = $this->employee();
        $other = Department::create(['code' => 'OTHER', 'name' => 'Other department']);
        $position = Position::create(['department_id' => $other->id, 'name' => 'Other position']);
        try {
            DB::table('employees')->where('id', $employee->id)->update(['position_id' => $position->id]);
            $this->fail('Composite foreign key must reject mismatched placement.');
        } catch (QueryException $exception) {
            $this->assertSame(1452, $exception->errorInfo[1]);
        }
    }

    public function test_evidence_is_bound_to_its_employee_and_missing_file_returns_404(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $this->actingAs($this->user())->post('/employees/'.$employee->id.'/archive', $this->archiveData($employee))->assertRedirect();
        $event = DB::table('employee_events')->where('action', 'archived')->first();
        $another = Employee::create([...$this->data(), 'employee_number' => 'EMP-OTHER', 'work_email' => 'other@example.test']);
        $this->get('/employees/'.$another->id.'/events/'.$event->id.'/document')->assertNotFound();
        Storage::disk('employee_records')->delete($event->document_path);
        $this->get('/employees/'.$employee->id.'/events/'.$event->id.'/document')->assertNotFound();
        $this->actingAs($this->user('hr_staff'))->post('/employees/'.$employee->id.'/restore', ['version' => 2])->assertForbidden();
    }

    private function user(string $role = 'admin'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function data(): array
    {
        $department = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);
        $position = Position::firstOrCreate(['department_id' => $department->id, 'name' => 'HR Staff']);

        return ['employee_number' => 'EMP-001', 'full_name' => 'Nadia Fiktif', 'work_email' => 'nadia@example.test',
            'phone' => '08123456789', 'department_id' => $department->id, 'position_id' => $position->id,
            'employment_status' => 'permanent', 'join_date' => '2026-01-01'];
    }

    private function employee(): Employee
    {
        return Employee::create($this->data());
    }

    private function archiveData(Employee $employee): array
    {
        return ['reason' => 'Data duplikat terverifikasi oleh pengelola HR.', 'confirmation' => $employee->employee_number,
            'acknowledged' => true, 'version' => $employee->version,
            'document' => UploadedFile::fake()->createWithContent('persetujuan.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF")];
    }

    public function test_guest_is_redirected_and_viewer_cannot_write(): void
    {
        $this->get('/employees')->assertRedirect('/login');
        $viewer = $this->user('viewer');
        $this->actingAs($viewer)->withoutVite()->get('/employees')->assertOk();
        $this->post('/employees', $this->data())->assertForbidden();
        $employee = $this->employee();
        $this->put('/employees/'.$employee->id, [...$this->data(), 'version' => 1])->assertForbidden();
        $this->post('/employees/'.$employee->id.'/archive', $this->archiveData($employee))->assertForbidden();
    }

    public function test_hr_can_create_read_and_update_with_server_actor_audit(): void
    {
        $hr = $this->user('hr_staff');
        $this->actingAs($hr)->post('/employees', [...$this->data(), 'role' => 'admin', 'actor_id' => 999])->assertRedirect();
        $employee = Employee::firstOrFail();
        $this->withoutVite()->get('/employees/'.$employee->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('employees', false)->where('employee.full_name', 'Nadia Fiktif'));
        $this->put('/employees/'.$employee->id, [...$this->data(), 'full_name' => 'Nadia Diperbarui', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'full_name' => 'Nadia Diperbarui', 'version' => 2]);
        $this->assertDatabaseHas('employee_events', ['employee_id' => $employee->id, 'actor_id' => $hr->id, 'action' => 'updated']);
        $this->assertDatabaseCount('employee_events', 2);
    }

    public function test_duplicate_and_mismatched_position_are_rejected(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->user())->post('/employees', $this->data())->assertSessionHasErrors(['employee_number', 'work_email']);
        $other = Department::create(['code' => 'IT', 'name' => 'Engineering']);
        $position = Position::create(['department_id' => $other->id, 'name' => 'Engineer']);
        $this->put('/employees/'.$employee->id, [...$this->data(), 'position_id' => $position->id, 'version' => 1])->assertSessionHasErrors('position_id');
    }

    public function test_archive_requires_admin_reason_document_and_exact_confirmation(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $this->actingAs($this->user('hr_staff'))->post('/employees/'.$employee->id.'/archive', $this->archiveData($employee))->assertForbidden();
        $this->actingAs($this->user())->post('/employees/'.$employee->id.'/archive', ['version' => 1])
            ->assertSessionHasErrors(['reason', 'document', 'confirmation', 'acknowledged']);
        $this->post('/employees/'.$employee->id.'/archive', [...$this->archiveData($employee), 'confirmation' => 'WRONG'])->assertSessionHasErrors('confirmation');
        $this->assertNotSoftDeleted($employee);
    }

    public function test_archive_keeps_private_evidence_and_audit_then_admin_can_restore(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $admin = $this->user();
        $this->actingAs($admin)->post('/employees/'.$employee->id.'/archive', $this->archiveData($employee))->assertRedirect('/employees');
        $this->assertSoftDeleted($employee);
        $event = DB::table('employee_events')->where('action', 'archived')->first();
        $this->assertSame($admin->id, $event->actor_id);
        Storage::disk('employee_records')->assertExists($event->document_path);
        $this->withoutVite()->get('/employees')->assertInertia(fn (Assert $page) => $page->where('employees.total', 0));
        $this->get('/employees/'.$employee->id.'/events/'.$event->id.'/document')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->post('/employees/'.$employee->id.'/restore', ['reason' => 'Pemulihan berdasarkan pemeriksaan pengelola HR.', 'confirmation' => $employee->employee_number, 'version' => 2])->assertRedirect();
        $this->assertNotSoftDeleted($employee);
        $this->assertDatabaseHas('employee_events', ['employee_id' => $employee->id, 'action' => 'restored']);
    }

    public function test_archived_email_and_number_remain_reserved(): void
    {
        $employee = $this->employee();
        $employee->delete();
        $this->actingAs($this->user())->post('/employees', $this->data())->assertSessionHasErrors(['employee_number', 'work_email']);
    }

    public function test_document_cannot_be_downloaded_by_viewer_hr_or_guest(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $this->actingAs($this->user())->post('/employees/'.$employee->id.'/archive', $this->archiveData($employee));
        $event = DB::table('employee_events')->where('action', 'archived')->first();
        $url = '/employees/'.$employee->id.'/events/'.$event->id.'/document';
        $this->actingAs($this->user('viewer'))->get($url)->assertForbidden();
        $this->actingAs($this->user('hr_staff'))->get($url)->assertForbidden();
        $this->post('/logout');
        $this->get($url)->assertRedirect('/login');
    }

    public function test_spoofed_or_large_files_are_rejected(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $this->actingAs($this->user())->post('/employees/'.$employee->id.'/archive', [...$this->archiveData($employee), 'document' => UploadedFile::fake()->createWithContent('fake.pdf', '<?php echo 1;')])->assertSessionHasErrors('document');
        $this->post('/employees/'.$employee->id.'/archive', [...$this->archiveData($employee), 'document' => UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')])->assertSessionHasErrors('document');
        $this->assertCount(0, Storage::disk('employee_records')->allFiles());
    }

    public function test_old_forms_cannot_overwrite_or_archive_a_newer_record(): void
    {
        Storage::fake('employee_records');
        $employee = $this->employee();
        $this->actingAs($this->user())->put('/employees/'.$employee->id, [...$this->data(), 'full_name' => 'Versi baru', 'version' => 1])->assertRedirect();
        $this->put('/employees/'.$employee->id, [...$this->data(), 'full_name' => 'Versi lama', 'version' => 1])->assertSessionHasErrors('version');
        $this->post('/employees/'.$employee->id.'/archive', $this->archiveData($employee))->assertSessionHasErrors('version');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'full_name' => 'Versi baru']);
        $this->assertCount(0, Storage::disk('employee_records')->allFiles());
    }

    public function test_search_filters_sort_and_pagination_are_server_side(): void
    {
        $data = $this->data();
        for ($i = 1; $i <= 17; $i++) {
            Employee::create([...$data, 'employee_number' => 'EMP-'.$i, 'work_email' => 'person'.$i.'@example.test', 'full_name' => 'Pegawai '.sprintf('%02d', $i)]);
        }
        $this->actingAs($this->user())->withoutVite()->get('/employees?sort=name_desc')->assertInertia(fn (Assert $page) => $page->where('employees.total', 17)->has('employees.data', 15)->where('employees.data.0.full_name', 'Pegawai 17'));
        $this->get('/employees?q=Pegawai%2001')->assertInertia(fn (Assert $page) => $page->where('employees.total', 1));
        $this->get('/employees?sort=malicious')->assertSessionHasErrors('sort');
        $this->actingAs($this->user('viewer'))->get('/employees?archived=1')->assertForbidden();
    }
}
