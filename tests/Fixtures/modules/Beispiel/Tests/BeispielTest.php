<?php

namespace Modules\Beispiel\Tests;

use Tests\TestCase;

class BeispielTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Modulseiten liegen nicht unter resources/js/pages
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    public function test_admin_sees_page_and_can_add_entry(): void
    {
        $this->signInAs('admin');

        $this->get('/beispiel')->assertOk()->assertInertia(fn ($page) => $page->component('Beispiel::start'));
        $this->post('/beispiel', ['titel' => 'Erster Eintrag'])->assertRedirect();
        $this->get('/beispiel')->assertInertia(fn ($page) => $page->where('eintraege.0.titel', 'Erster Eintrag'));
    }

    public function test_others_have_no_access(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/beispiel')->assertForbidden();
    }
}
