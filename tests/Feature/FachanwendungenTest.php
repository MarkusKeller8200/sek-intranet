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
    public function test_kommunikation_has_fachanwendungen_submenu(): void
    {
        $this->signInAs('lehrperson');

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('navigation', function ($groups): bool {
                $group = collect($groups)->firstWhere('key', 'kommunikation');
                $menu = collect($group['items'] ?? [])->firstWhere('key', 'fachanwendungen');
                $children = collect($menu['children'] ?? []);

                return $children->pluck('key')->all() === ['cmi', 'escola', 'apple-school-manager', 'jamf-pro']
                    && $children->every(fn ($c) => $c['collapse'] === true && str_starts_with($c['href'], '/einbindung/'))
                    && $children->firstWhere('key', 'escola')['external'] === 'https://www.sekbuelach.ch/escola/login'
                    && $children->firstWhere('key', 'apple-school-manager')['open'] === 'tab';
            }));
    }

    public function test_fachanwendung_page_collapses_sidebar(): void
    {
        // Escola erlaubt das Einbetten (bestätigt): keine Prüfung, Rahmen auch wenn die Prüfung anders ausfiele
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'SAMEORIGIN'])]);
        $this->signInAs('lehrperson');

        $this->get('/einbindung/escola')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('modules/embed')
            ->where('module.collapse', true)
            ->where('module.group', 'Kommunikation')
            ->where('frame.embeddable', true));
        Http::assertNotSent(fn ($r): bool => str_contains($r->url(), 'sekbuelach.ch/escola'));

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
}
