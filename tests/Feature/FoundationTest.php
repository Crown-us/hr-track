<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_public_home_is_an_inertia_page_without_private_data(): void
    {
        $this->withoutVite()
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome', false)
                ->missing('employees')
                ->missing('applicants')
                ->missing('database'));
    }

    public function test_lab_page_renders_the_imported_component_specimen(): void
    {
        $this->withoutVite()
            ->get('/lab')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('lab', false)
                ->missing('employees')
                ->missing('applicants')
                ->missing('database'));
    }
}
