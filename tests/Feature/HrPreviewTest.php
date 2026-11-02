<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrPreviewTest extends TestCase
{
    public function test_design_preview_renders_without_private_database_props(): void
    {
        $this->withoutVite()->get('/preview/hr')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('hr', false)
                ->missing('employees')->missing('applicants')->where('auth.user', null));
    }

    public function test_design_preview_is_unavailable_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->get('/preview/hr')->assertNotFound();
    }
}
