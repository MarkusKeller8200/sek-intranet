<?php

namespace Tests\Feature;

use App\Data\Contracts\UserRepository;
use App\Http\Controllers\ConnectorController;
use App\Plugins\PluginManager;
use App\Support\PersonLinks;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

/**
 * Verbindung Intranet ⇄ Tab einer Fachanwendung (Browser-Erweiterung): Kontext, Abrufe, Nebeneinander.
 */
class ConnectorTest extends TestCase
{
    public function test_ids_are_read_from_addresses(): void
    {
        $this->assertSame(['4711', 'T-001'], ConnectorController::ids('https://www.sekbuelach.ch/escola/person/4711/detail?ref=T-001&tab=a'));
        $this->assertSame([], ConnectorController::ids('https://www.sekbuelach.ch/escola/login'));
    }

    /** Escola-ID einer Person (vorhandene Datenherkunft oder neu angelegt). */
    private function escolaId(array $person, string $fallback): string
    {
        $links = app(PersonLinks::class);
        foreach ($links->all() as $link) {
            if ($link['person_id'] === $person['id'] && $link['system'] === 'escola' && $link['direction'] === 'source') {
                return (string) $link['external_id'];
            }
        }
        $links->save(['person_id' => $person['id'], 'direction' => 'source', 'system' => 'escola', 'external_id' => $fallback]);

        return $fallback;
    }

    public function test_context_finds_people_via_data_origin(): void
    {
        $person = collect(app(UserRepository::class)->all())->firstWhere('name', 'Daniel Probe');
        $id = $this->escolaId($person, '4711');

        $this->signInAs('lehrperson');
        $this->postJson('/einbindung/escola/kontext', ['url' => 'https://www.sekbuelach.ch/escola/person/'.rawurlencode($id).'/detail'])->assertOk()
            ->assertJsonPath('system', 'escola')
            ->assertJsonPath('people.0.name', 'Daniel Probe')
            ->assertJsonPath('people.0.matched', $id)
            ->assertJsonPath('people.0.href', '/personen/'.$person['id']);

        // Ohne Abgleich eingerichtet (LMVZ Digital) oder ohne Treffer: keine Personen
        $this->postJson('/einbindung/lmvz-digital/kontext', ['url' => 'https://digital.lmvz.ch/benutzer/4711'])->assertOk()->assertJsonPath('people', []);
        $this->postJson('/einbindung/escola/kontext', ['url' => 'javascript:alert(1)'])->assertStatus(422);
    }

    public function test_protected_people_only_with_view_students(): void
    {
        $student = collect(app(UserRepository::class)->all())->firstWhere('person_type', 'schueler');
        $id = rawurlencode($this->escolaId($student, '9001'));

        $this->signInAs('redaktion');
        $this->postJson('/einbindung/escola/kontext', ['url' => 'https://www.sekbuelach.ch/escola/schueler/'.$id])->assertOk()->assertJsonPath('people', []);

        $this->signInAs('lehrperson');
        $this->postJson('/einbindung/escola/kontext', ['url' => 'https://www.sekbuelach.ch/escola/schueler/'.$id])->assertOk()->assertJsonCount(1, 'people');
    }

    public function test_lmvz_digital_offers_read_endpoints_for_ict_only(): void
    {
        app(PluginManager::class)->saveSettings('lmvz', ['org_id' => '900', 'license_view' => '7', '_allow_write' => false]);

        $this->signInAs('admin');
        $this->get('/einbindung/lmvz-digital')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('module.open', 'extension')
            ->where('endpoints', function ($endpoints): bool {
                $list = collect($endpoints);
                $licenses = $list->firstWhere('id', 'd-licenses');

                return $licenses !== null
                    && collect($licenses['params'])->pluck('value', 'name')->all() === ['view' => '7', 'orgId' => '900']
                    && ! $list->contains('id', 'd-user-create')   // schreibend
                    && ! $list->contains('id', 'd-avatar-reset')  // GET mit Wirkung
                    && ! $list->contains('id', 'product');        // anderer Host (Shop)
            }));

        $this->signInAs('lehrperson');
        $this->get('/einbindung/lmvz-digital')->assertInertia(fn (Assert $page) => $page->where('endpoints', []));
    }

    public function test_split_mode_and_app_origins_in_extension(): void
    {
        $this->signInAs('admin');
        $this->put('/einstellungen/menuepunkt/escola', ['url' => '', 'open' => 'split'])->assertSessionHasNoErrors();
        $this->get('/einbindung/escola')->assertInertia(fn (Assert $page) => $page->where('module.open', 'split')->where('frame', null));

        $file = $this->get('/einstellungen/browser-erweiterung/download')->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($file);
        $manifest = json_decode((string) $zip->getFromName('sbi-connector/manifest.json'), true);
        $this->assertNotFalse($zip->getFromName('sbi-connector/page-hook.js'));
        $zip->close();

        $this->assertContains('https://www.sekbuelach.ch/*', $manifest['host_permissions']);
        $this->assertContains('https://digital.lmvz.ch/*', $manifest['host_permissions']);
        $this->assertContains('https://school.apple.com/*', $manifest['host_permissions']);

        // Der Seiten-Hook gibt die Anmeldung der Seite nie weiter
        $hook = (string) file_get_contents(base_path('browser-extension/page-hook.js'));
        $this->assertStringContainsString('signedIn: auth !== null', $hook);
        $this->assertStringNotContainsString('auth,', $hook);
        $this->assertStringContainsString('url.origin !== location.origin', $hook);
    }
}
