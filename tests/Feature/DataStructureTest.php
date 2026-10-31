<?php

namespace Tests\Feature;

use App\Data\Contracts\EventRepository;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DataStructureTest extends TestCase
{
    public function test_admin_sees_schema_with_counts_and_detects_unknown_fields(): void
    {
        $event = app(EventRepository::class)->all()[0];
        app(EventRepository::class)->update($event['id'], ['raum_nr' => '104']);

        $this->signInAs('admin');

        $this->get('/datenstrukturen')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('data-structures')
                ->where('store', 'json')
                ->has('collections', 10)
                ->where('collections.0.name', 'users')
                ->where('collections.0.count', 22)
                ->where('collections.2.name', 'events')
                ->where('collections.2.unknown_fields.raum_nr', 1)
                ->where('collections.0.fields', fn ($fields): bool => collect($fields)->firstWhere('name', 'email')['unique'] === true));
    }
}
