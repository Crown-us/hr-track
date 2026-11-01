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
}
