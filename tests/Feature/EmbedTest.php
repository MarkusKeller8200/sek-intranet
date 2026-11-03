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
        $this->signInAs('lehrperson');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', function ($groups): bool {
                $group = collect($groups)->firstWhere('key', 'verwaltung');
                $items = collect($group['items'] ?? []);

                // Workflows nur für Verwaltung und Schulleitung
                return $items->pluck('key')->all() === ['tickets', 'elternportal']
                    && $items->every(fn ($i) => str_starts_with($i['href'], '/einbindung/') && str_starts_with((string) $i['external'], 'https://'));
            }));
    }

    public function test_sharepoint_workflows_replaced_by_module(): void
    {
        // Die SharePoint-Seite «Workflows» ist durch das Modul «Abläufe Verwaltung» ersetzt (#45)
        Http::fake();
        $this->signInAs('verwaltung');
        $this->get('/einbindung/workflows')->assertNotFound();
    }

    public function test_blocked_page_is_offered_in_new_tab(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);
        $this->signInAs('lehrperson');

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

    public function test_ict_apps_are_for_ict_only(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/einbindung/websnapshot')->assertNotFound();
        $this->get('/einbindung/gibt-es-nicht')->assertNotFound();

        Http::fake();
        $this->signInAs('ict');
        $this->get('/einbindung/websnapshot')->assertOk();
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
        $this->signInAs('lehrperson');

        $this->get('/einbindung/tickets')->assertInertia(fn (Assert $page) => $page->where('module.url', null));
    }
}
