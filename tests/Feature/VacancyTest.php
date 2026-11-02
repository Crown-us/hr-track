<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VacancyTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        $department = Department::firstOrCreate(['code' => 'HR'], ['name' => 'Human Resources']);

        return ['job_title' => 'HR Generalist', 'department_id' => $department->id, 'employment_type' => 'full_time',
            'location' => 'Jakarta · Kantor', 'description' => 'Mengelola administrasi dan kebutuhan sumber daya manusia.',
            'requirements' => 'Memahami administrasi HR dan mampu bekerja sama dengan tim.',
            'opening_date' => now('Asia/Jakarta')->toDateString(), 'closing_date' => now('Asia/Jakarta')->addDays(30)->toDateString(), 'status' => 'draft'];
    }

    public function test_guest_and_viewer_cannot_manage_vacancies(): void
    {
        $this->get('/vacancies')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->withoutVite()->get('/vacancies')->assertOk();
        $this->post('/vacancies', $this->data())->assertForbidden();
        $vacancy = Vacancy::create($this->data());
        $this->put('/vacancies/'.$vacancy->id, [...$this->data(), 'version' => 1])->assertForbidden();
        $this->get('/vacancies/'.$vacancy->id)->assertOk();
    }

    public function test_hr_can_create_read_edit_and_close_without_deleting(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'hr_staff']));
        $this->post('/vacancies', $this->data())->assertRedirect();
        $vacancy = Vacancy::firstOrFail();
        $this->withoutVite()->get('/vacancies/'.$vacancy->id)->assertInertia(fn (Assert $page) => $page->component('vacancies', false)->where('vacancy.job_title', 'HR Generalist'));
        $this->put('/vacancies/'.$vacancy->id, [...$this->data(), 'status' => 'published', 'version' => 1])->assertRedirect();
        $this->assertDatabaseHas('vacancies', ['id' => $vacancy->id, 'status' => 'published', 'version' => 2]);
        $this->put('/vacancies/'.$vacancy->id, [...$this->data(), 'status' => 'closed', 'version' => 2])->assertRedirect();
        $this->assertDatabaseHas('vacancies', ['id' => $vacancy->id, 'status' => 'closed', 'version' => 3]);
        $this->assertDatabaseCount('vacancies', 1);
    }

    public function test_invalid_dates_status_type_and_department_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/vacancies', [...$this->data(), 'closing_date' => '2000-01-01', 'status' => 'unexpected', 'employment_type' => 'unexpected', 'department_id' => 9999])
            ->assertSessionHasErrors(['closing_date', 'status', 'employment_type', 'department_id']);
        $this->assertDatabaseCount('vacancies', 0);
    }

    public function test_stale_form_cannot_overwrite_changes(): void
    {
        $vacancy = Vacancy::create($this->data());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put('/vacancies/'.$vacancy->id, [...$this->data(), 'job_title' => 'Versi baru', 'version' => 1])->assertRedirect();
        $this->put('/vacancies/'.$vacancy->id, [...$this->data(), 'job_title' => 'Versi lama', 'version' => 1])->assertSessionHasErrors('version');
        $this->assertDatabaseHas('vacancies', ['id' => $vacancy->id, 'job_title' => 'Versi baru']);
    }

    public function test_search_filter_pagination_and_sort_validation(): void
    {
        $data = $this->data();
        for ($i = 1; $i <= 17; $i++) {
            Vacancy::create([...$data, 'job_title' => 'Posisi '.sprintf('%02d', $i), 'status' => $i === 1 ? 'closed' : 'draft']);
        }
        $this->actingAs(User::factory()->create(['role' => 'viewer']))->withoutVite()->get('/vacancies')->assertInertia(fn (Assert $page) => $page->where('vacancies.total', 17)->has('vacancies.data', 15));
        $this->get('/vacancies?q=Posisi%2001')->assertInertia(fn (Assert $page) => $page->where('vacancies.total', 1));
        $this->get('/vacancies?status=closed')->assertInertia(fn (Assert $page) => $page->where('vacancies.total', 1));
        $this->get('/vacancies?sort=invalid')->assertSessionHasErrors('sort');
    }

    public function test_dashboard_only_counts_open_published_vacancies_in_the_wib_date_window(): void
    {
        $this->travelTo(now()->setDateTime(2026, 11, 1, 18, 0, 0)); // 2 November WIB.
        $data = $this->data();
        Vacancy::create([...$data, 'status' => 'published', 'opening_date' => '2026-11-02', 'closing_date' => '2026-11-02']);
        Vacancy::create([...$data, 'status' => 'published', 'opening_date' => '2026-11-03', 'closing_date' => '2026-11-30']);
        Vacancy::create([...$data, 'status' => 'published', 'opening_date' => '2026-10-01', 'closing_date' => '2026-11-01']);
        Vacancy::create($data);
        Vacancy::create([...$data, 'status' => 'closed']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->withoutVite()->get('/hr')
            ->assertInertia(fn (Assert $page) => $page->where('vacancySummary.count', 1)->has('vacancySummary.recent', 1));
    }
}
