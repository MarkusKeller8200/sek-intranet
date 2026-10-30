<?php

namespace Tests\Feature;

use App\Data\Contracts\EventRepository;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventTest extends TestCase
{
    public function test_upcoming_events_by_default_past_on_request(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/termine')->assertInertia(fn (Assert $page) => $page
            ->component('events/index')
            ->has('events', 5)
            ->where('showPast', false));

        $this->get('/termine?vergangene=1')->assertInertia(fn (Assert $page) => $page->has('events', 6));
    }

    public function test_dashboard_shows_next_five_events_in_order(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->has('events', 5)
            ->where('events.0.title', 'Gesamtkonferenz')
            ->where('stats.events', 5));
    }

    public function test_editor_creates_all_day_event(): void
    {
        $this->signInAs('redaktion');

        $this->post('/termine', [
            'title' => 'Projektwoche',
            'category' => 'schulanlass',
            'school' => 'gesamt',
            'all_day' => true,
            'starts_at' => '2026-11-16T08:00',
            'ends_at' => '2026-11-20',
        ])->assertRedirect('/termine');

        $event = collect(app(EventRepository::class)->all())->firstWhere('title', 'Projektwoche');
        $this->assertSame('2026-11-16T00:00:00+01:00', $event['starts_at']);
        $this->assertSame('2026-11-20T00:00:00+01:00', $event['ends_at']);
        $this->assertTrue($event['all_day']);
        $this->assertNull($event['location']);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->signInAs('redaktion');

        $this->post('/termine', [
            'title' => 'Falsch',
            'category' => 'termin',
            'school' => 'gesamt',
            'starts_at' => '2026-11-16T10:00',
            'ends_at' => '2026-11-16T09:00',
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_staff_cannot_change_events(): void
    {
        $this->signInAs('lehrperson');
        $event = app(EventRepository::class)->all()[0];

        $this->delete("/termine/{$event['id']}")->assertForbidden();
        $this->assertNotNull(app(EventRepository::class)->find($event['id']));
    }
}
