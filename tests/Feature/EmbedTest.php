<?php

namespace Tests\Feature;

use App\Support\FrameCheck;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmbedTest extends TestCase
{
    public function test_verwaltung_menu_links_to_embed_pages(): void
    {
        $this->signInAs('mitarbeitende');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', function ($groups): bool {
                $group = collect($groups)->firstWhere('key', 'verwaltung');
                $items = collect($group['items'] ?? []);

                return $items->pluck('key')->all() === ['workflows', 'tickets', 'elternportal']
                    && $items->every(fn ($i) => str_starts_with($i['href'], '/einbindung/') && str_starts_with((string) $i['external'], 'https://'));
            }));
    }

    public function test_blocked_page_is_offered_in_new_tab(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);
        $this->signInAs('mitarbeitende');

        $this->get('/einbindung/tickets')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('modules/embed')
            ->where('module.url', 'https://sekbuelach.atlassian.net/servicedesk/customer/portal/2')
            ->where('module.group', 'Verwaltung')
            ->where('frame.embeddable', false));
    }

    public function test_application_without_address_shows_repository(): void
    {
        Http::fake();
        $this->signInAs('admin');

        $this->get('/einbindung/websnapshot')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('module.url', null)
            ->where('module.repo', 'MarkusKeller8200/websnapshot')
            ->where('frame', null));
        Http::assertNothingSent();
    }

    public function test_ict_apps_are_for_administration_only(): void
    {
        $this->signInAs('mitarbeitende');

        $this->get('/einbindung/websnapshot')->assertNotFound();
        $this->get('/einbindung/gibt-es-nicht')->assertNotFound();
    }

    public function test_frame_rules(): void
    {
        $host = 'intranet.sekbuelach.ch';

        $this->assertFalse(FrameCheck::evaluate('SAMEORIGIN', '', $host)['embeddable']);
        $this->assertFalse(FrameCheck::evaluate('', "default-src 'self'; frame-ancestors 'self' teams.microsoft.com", $host)['embeddable']);
        $this->assertTrue(FrameCheck::evaluate('', 'frame-ancestors https://intranet.sekbuelach.ch', $host)['embeddable']);
        $this->assertTrue(FrameCheck::evaluate('', 'frame-ancestors *.sekbuelach.ch', $host)['embeddable']);
        // frame-ancestors hat Vorrang vor X-Frame-Options
        $this->assertTrue(FrameCheck::evaluate('DENY', 'frame-ancestors *', $host)['embeddable']);
        $this->assertTrue(FrameCheck::evaluate('', '', $host)['embeddable']);
    }

    public function test_only_https_addresses_are_used(): void
    {
        config(['intranet.modules.verwaltung.items.tickets.embed.url' => 'javascript:alert(1)']);
        $this->signInAs('mitarbeitende');

        $this->get('/einbindung/tickets')->assertInertia(fn (Assert $page) => $page->where('module.url', null));
    }
}
