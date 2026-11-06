<?php

namespace Tests\Feature;

use App\Data\Contracts\AnnouncementRepository;
use App\Support\AppVersion;
use App\Support\ReleaseAnnouncements;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Neuerungen einer Version als Mitteilung (#26).
 */
class ReleaseAnnouncementsTest extends TestCase
{
    private const CHANGELOG = <<<'MD'
        # Changelog

        ## [Unveröffentlicht]

        ## [1.4.0] – 2026-11-03

        ### Für Mitarbeitende

        - Neue **Suche** über alle Mitteilungen,
          auch in Dokumenten.
        - [manage-users] Personen in Gruppen verwalten.
        - [gibt-es-nicht] Punkt mit unbekanntem Recht.

        ### Neu

        - Technische Details (#99).

        ## [1.3.1] – 2026-10-01

        ### Behoben

        - Kleinigkeit.
        MD;

    private function service(): ReleaseAnnouncements
    {
        return app(ReleaseAnnouncements::class);
    }

    private function changelog(): string
    {
        return self::CHANGELOG;
    }

    public function test_items_with_rights_and_plain_text(): void
    {
        $items = ReleaseAnnouncements::items($this->changelog(), '1.4.0');

        $this->assertSame([
            ['text' => 'Neue Suche über alle Mitteilungen, auch in Dokumenten.', 'can' => null],
            ['text' => 'Personen in Gruppen verwalten.', 'can' => 'manage-users'],
            ['text' => 'Punkt mit unbekanntem Recht.', 'can' => 'gibt-es-nicht'],
        ], $items);
        $this->assertSame([], ReleaseAnnouncements::items($this->changelog(), '1.3.1'));
        $this->assertSame('Neu im Intranet: Version 1.4', ReleaseAnnouncements::title('1.4.0'));
        $this->assertSame('Neu im Intranet: Version 1.3.1', ReleaseAnnouncements::title('1.3.1'));
    }

    public function test_publish_once_and_never_again_after_delete(): void
    {
        $item = $this->service()->publish('1.4.0', $this->changelog());

        $this->assertNotNull($item);
        $this->assertSame('neuerungen', $item['category']);
        $this->assertSame('Intranet', $item['author_label']);
        $this->assertNull($item['author_id']);
        // unbekanntes Recht → Punkt für alle
        $this->assertNull($item['release_items'][2]['can']);
        $this->assertStringContainsString('• Personen in Gruppen verwalten.', (string) $item['body']);

        $this->assertNull($this->service()->publish('1.4.0', $this->changelog()), 'zweimal');
        app(AnnouncementRepository::class)->delete((string) $item['id']);
        $this->assertNull($this->service()->publish('1.4.0', $this->changelog()), 'nach dem Löschen');
    }

    public function test_no_announcement_for_prerelease_missing_section_or_disabled(): void
    {
        $this->assertNull($this->service()->publish('1.4.0-beta.1', $this->changelog()));
        $this->assertNull($this->service()->publish('1.3.1', $this->changelog()));

        $this->service()->setEnabled(false);
        $this->assertNull($this->service()->publish('1.4.0', $this->changelog()));
        $this->assertFalse($this->service()->announced('1.4.0'), 'ausgeschaltet merkt sich nichts');

        $this->service()->setEnabled(true);
        $this->assertNotNull($this->service()->publish('1.4.0', $this->changelog()));
    }

    public function test_items_with_rights_only_for_people_with_that_right(): void
    {
        $item = $this->service()->publish('1.4.0', $this->changelog());
        $this->assertNotNull($item);

        $this->signInAs('lehrperson');
        $this->get('/mitteilungen/'.$item['id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('announcement.author_name', 'Intranet')
            ->where('announcement.body', fn (string $body): bool => ! str_contains($body, 'Gruppen') && str_contains($body, 'Suche')));
        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('announcements.0.body', fn (string $body): bool => ! str_contains($body, 'Gruppen')));

        $this->signInAs('admin');
        $this->get('/mitteilungen/'.$item['id'])->assertInertia(fn (Assert $page) => $page
            ->where('announcement.body', fn (string $body): bool => str_contains($body, 'Gruppen')));
    }

    public function test_link_to_release_notes_on_github(): void
    {
        config(['intranet.github_repo' => 'MarkusKeller8200/sek-intranet']);
        $item = $this->service()->publish('1.4.0', $this->changelog());
        $this->assertNotNull($item);

        $this->signInAs('lehrperson');
        $this->get('/mitteilungen/'.$item['id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('announcement.release_version', '1.4.0')
            ->where('releaseUrl', 'https://github.com/MarkusKeller8200/sek-intranet/releases/tag/v1.4.0'));

        config(['intranet.github_repo' => '']);
        $this->assertNull(ReleaseAnnouncements::releaseUrl('1.4.0'));
        $this->assertNull(ReleaseAnnouncements::releaseUrl('kein-semver'));
    }

    public function test_hidden_when_nothing_is_for_the_person(): void
    {
        $changelog = "## [2.0.0] – 2026-12-01\n\n### Für Mitarbeitende\n\n- [manage-users] Nur für die Administration.\n";
        $item = $this->service()->publish('2.0.0', $changelog);
        $this->assertNotNull($item);

        $this->signInAs('lehrperson');
        $this->get('/mitteilungen/'.$item['id'])->assertNotFound();
        $this->get('/mitteilungen')->assertInertia(fn (Assert $page) => $page
            ->where('announcements', fn ($list): bool => collect($list)->doesntContain('id', $item['id'])));
    }

    public function test_edited_text_applies_to_everyone(): void
    {
        $item = $this->service()->publish('1.4.0', $this->changelog());
        $this->assertNotNull($item);

        $this->signInAs('admin');
        $this->put('/mitteilungen/'.$item['id'], [
            'title' => $item['title'],
            'body' => 'Von Hand angepasst.',
            'category' => 'neuerungen',
            'audience' => 'alle',
            'published_at' => $item['published_at'],
        ])->assertRedirect();

        $this->signInAs('lehrperson');
        $this->get('/mitteilungen/'.$item['id'])->assertInertia(fn (Assert $page) => $page
            ->where('announcement.body', 'Von Hand angepasst.'));
    }

    public function test_upgrade_command_announces_current_release(): void
    {
        config(['intranet.version' => '0.33.0']);
        $this->assertSame(0, Artisan::call('intranet:upgrade'));

        $titles = array_column(app(AnnouncementRepository::class)->all(), 'title');
        $this->assertContains('Neu im Intranet: Version 0.33', $titles);
        $this->assertTrue($this->service()->announced('0.33.0'));
    }

    public function test_toggle_on_versions_page_and_tags_hidden_in_changelog(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/versionen')->assertInertia(fn (Assert $page) => $page->where('announcements', null));
        $this->post('/versionen/neuerungen', ['enabled' => false])->assertForbidden();

        $this->signInAs('admin');
        $this->get('/versionen')->assertInertia(fn (Assert $page) => $page->where('announcements.enabled', true));
        $this->post('/versionen/neuerungen', ['enabled' => false])->assertRedirect();
        $this->assertFalse($this->service()->enabled());

        foreach (AppVersion::changelog() as $release) {
            foreach ($release['sections'] as $section) {
                foreach ($section['items'] as $text) {
                    $this->assertDoesNotMatchRegularExpression('/^\[[a-z][a-z0-9-]*\] /', $text);
                }
            }
        }
    }
}
