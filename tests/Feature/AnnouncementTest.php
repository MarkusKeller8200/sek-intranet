<?php

namespace Tests\Feature;

use App\Data\Contracts\AnnouncementRepository;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    public function test_staff_see_only_published_announcements_pinned_first(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/mitteilungen')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('announcements/index')
                ->has('announcements', 4)
                ->where('announcements.0.pinned', true)
                ->where('announcements.0.author_name', 'Andrea Muster'));
    }

    public function test_editors_also_see_scheduled_announcements(): void
    {
        $this->signInAs('redaktion');

        $this->get('/mitteilungen')->assertInertia(fn (Assert $page) => $page->has('announcements', 5));
    }

    public function test_staff_cannot_open_scheduled_announcement_or_create(): void
    {
        $this->signInAs('lehrperson');
        $scheduled = collect(app(AnnouncementRepository::class)->all())
            ->first(fn (array $a) => strtotime($a['published_at']) > time());

        $this->get("/mitteilungen/{$scheduled['id']}")->assertNotFound();
        $this->get('/mitteilungen/neu')->assertForbidden();
        $this->post('/mitteilungen', [])->assertForbidden();
    }

    public function test_editor_can_create_update_and_delete(): void
    {
        $editor = $this->signInAs('redaktion');

        $this->post('/mitteilungen', [
            'title' => 'Testmitteilung',
            'body' => 'Inhalt',
            'category' => 'it',
            'audience' => 'alle',
            'pinned' => true,
            'published_at' => '2026-01-15T08:30',
        ])->assertRedirect()->assertInertiaFlash('toast.message', 'Mitteilung gespeichert.');

        $created = collect(app(AnnouncementRepository::class)->all())->firstWhere('title', 'Testmitteilung');
        $this->assertNotNull($created);
        $this->assertSame($editor->id(), $created['author_id']);
        $this->assertSame('2026-01-15T08:30:00+01:00', $created['published_at']);
        $this->assertTrue($created['pinned']);

        $this->put("/mitteilungen/{$created['id']}", [
            'title' => 'Geändert',
            'body' => 'Inhalt',
            'category' => 'it',
            'audience' => 'lehrpersonen',
            'published_at' => '2026-07-01T10:00',
        ])->assertRedirect("/mitteilungen/{$created['id']}");

        $updated = app(AnnouncementRepository::class)->find($created['id']);
        $this->assertSame('Geändert', $updated['title']);
        $this->assertFalse($updated['pinned']);
        $this->assertSame('2026-07-01T10:00:00+02:00', $updated['published_at']);

        $this->delete("/mitteilungen/{$created['id']}")->assertRedirect('/mitteilungen');
        $this->assertNull(app(AnnouncementRepository::class)->find($created['id']));
    }

    public function test_validation_messages_are_german(): void
    {
        $this->signInAs('redaktion');

        $this->post('/mitteilungen', ['category' => 'gibt-es-nicht'])
            ->assertSessionHasErrors([
                'title' => 'Titel muss ausgefüllt werden.',
                'category' => 'Der gewählte Wert für Kategorie ist ungültig.',
            ]);
    }
}
