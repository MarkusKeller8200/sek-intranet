<?php

namespace Tests\Feature;

use App\Plugins\PluginManager;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kommunikation → Fachanwendungen (Untermenü, Seitenmenü einklappen) und «Verbindung testen» bei den Plugins.
 */
class FachanwendungenTest extends TestCase
{
    public function test_fachanwendungen_is_second_main_menu(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', function ($groups): bool {
                $groups = collect($groups);
                $children = collect($groups[1]['items'] ?? []);

                return $groups->pluck('key')->take(2)->all() === ['kommunikation', 'fachanwendungen']
                    && ! collect($groups[0]['items'])->contains('key', 'fachanwendungen')
                    && $children->pluck('key')->all() === ['cmi', 'escola', 'apple-school-manager', 'jamf-pro', 'lmvz-digital', 'sl-planer']
                    && $children->every(fn ($c) => $c['collapse'] === true && str_starts_with($c['href'], '/einbindung/'))
                    && $children->firstWhere('key', 'escola')['external'] === 'https://www.sekbuelach.ch/escola/login'
                    && $children->firstWhere('key', 'escola')['open'] === 'tab'
                    && $children->firstWhere('key', 'apple-school-manager')['open'] === 'tab';
            }));
    }

    public function test_fachanwendung_page_collapses_sidebar(): void
    {
        // Escola erlaubt das Einbetten nur auf www.sekbuelach.ch (frame-ancestors 'self'): eigener Tab, keine Prüfung nötig
        Http::fake(['*' => Http::response('', 200)]);
        $this->signInAs('lehrperson');

        $this->get('/einbindung/escola')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('modules/embed')
            ->where('module.collapse', true)
            ->where('module.group', 'Fachanwendungen')
            ->where('module.open', 'tab')
            ->where('frame', null));

        // Andere Einbindungen lassen das Seitenmenü offen
        $this->get('/einbindung/lehrplan21')->assertInertia(fn (Assert $page) => $page->where('module.collapse', false));

        // Ohne Adresse (CMI noch nicht eingetragen): Seite mit Hinweis statt Rahmen
        $this->get('/einbindung/cmi')->assertOk()->assertInertia(fn (Assert $page) => $page->where('module.url', null));
    }

    public function test_all_connections_are_tested_and_inactive_plugins_skipped(): void
    {
        Http::fake([
            'shop.lmvz.ch/*' => Http::response('<title>LMVZ Shop</title>'),
            '*' => Http::response('', 500),
        ]);
        $manager = app(PluginManager::class);
        $manager->saveSettings('lmvz', ['max_items' => 5, '_allow_write' => false]);
        $manager->setEnabled('lmvz', true);

        $this->signInAs('lehrperson');
        $this->postJson('/plugins/verbindungen-testen')->assertForbidden();

        $this->signInAs('admin');
        $results = collect($this->postJson('/plugins/verbindungen-testen')->assertOk()
            ->assertJsonPath('message', '1 Verbindungen geprüft, 0 mit Fehler.')
            ->json('results'))->keyBy('id');

        $this->assertTrue($results['lmvz']['ok']);
        $this->assertNull($results['jamf']['ok']);
        $this->assertStringContainsString('ausgeschaltet', $results['jamf']['message']);
    }

    public function test_embed_check_explains_who_blocks(): void
    {
        Http::fake(['www.sekbuelach.ch/*' => Http::response('', 200, [
            'Server' => 'Apache',
            'Content-Security-Policy' => "default-src 'self'; frame-ancestors 'self'; form-action 'self'",
        ])]);
        $this->signInAs('admin');

        $this->getJson('/einstellungen/menuepunkt/escola/pruefen')->assertOk()
            ->assertJsonPath('embeddable', false)
            ->assertJsonPath('details.frame_ancestors', "'self'")
            ->assertJsonPath('details.server', 'Apache')
            ->assertJsonPath('details.cloudflare', false)
            ->assertJsonPath('reason', fn (string $r): bool => str_contains($r, 'nur Seiten der eigenen Adresse'));

        // Cloudflare wird erkannt
        Http::fake(['zh.lehrplan.ch/*' => Http::response('', 200, ['Server' => 'cloudflare', 'CF-Ray' => 'abc-ZRH', 'X-Frame-Options' => 'SAMEORIGIN'])]);
        $this->getJson('/einstellungen/menuepunkt/lehrplan21/pruefen')->assertOk()
            ->assertJsonPath('details.cloudflare', true)
            ->assertJsonPath('details.x_frame_options', 'SAMEORIGIN');

        $this->signInAs('lehrperson');
        $this->getJson('/einstellungen/menuepunkt/escola/pruefen')->assertForbidden();
    }
}
