<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Support\ModuleSettings;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

/**
 * Menü «Einstellungen»: Einstellungen der Menüpunkte (Fachanwendungen) und Browser-Erweiterung.
 */
class ModuleSettingsTest extends TestCase
{
    public function test_settings_menu_sits_above_prototyp_for_administration(): void
    {
        $this->signInAs('admin');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', function ($groups): bool {
            $keys = collect($groups)->pluck('key')->all();
            $settings = array_search('einstellungen', $keys, true);

            return $settings !== false && $keys[$settings + 1] === 'prototyp'
                && collect($groups[$settings]['items'])->pluck('href')->contains('/einstellungen/fachanwendungen');
        }));

        $this->signInAs('lehrperson');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', fn ($groups): bool => ! collect($groups)->contains('key', 'einstellungen')));
        $this->get('/einstellungen/fachanwendungen')->assertForbidden();
        $this->put('/einstellungen/menuepunkt/cmi', ['url' => 'https://cmi.example.org', 'open' => 'tab'])->assertForbidden();
    }

    public function test_fachanwendungen_settings_override_defaults(): void
    {
        $this->signInAs('admin');

        $this->get('/einstellungen/fachanwendungen')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('module-settings')
            ->where('items', fn ($items): bool => collect($items)->pluck('key')->all() === ['cmi', 'escola', 'apple-school-manager', 'jamf-pro', 'lmvz-digital'])
            ->where('modes', ['embed', 'both', 'tab', 'extension', 'split']));
        $this->get('/einstellungen/einbindungen')->assertInertia(fn (Assert $page) => $page
            ->where('items', fn ($items): bool => collect($items)->contains('key', 'lehrplan21') && ! collect($items)->contains('key', 'cmi')));

        // CMI: Adresse und «eigenes Fenster» setzen
        $this->put('/einstellungen/menuepunkt/cmi', ['url' => 'https://cmi.example.org/schule', 'open' => 'tab'])->assertRedirect();
        $this->get('/start')->assertInertia(fn (Assert $page) => $page->where('navigation', function ($groups): bool {
            $cmi = collect(collect($groups)->firstWhere('key', 'fachanwendungen')['items'])->firstWhere('key', 'cmi');

            return $cmi['external'] === 'https://cmi.example.org/schule' && $cmi['open'] === 'tab';
        }));

        // Escola über die Browser-Erweiterung: keine Rahmen-Prüfung, Seite mit Erweiterungs-Hinweis
        Http::fake();
        $this->put('/einstellungen/menuepunkt/escola', ['url' => '', 'open' => 'extension'])->assertRedirect();
        $this->get('/einbindung/escola')->assertInertia(fn (Assert $page) => $page
            ->where('module.open', 'extension')
            ->where('module.url', 'https://www.sekbuelach.ch/escola/login')
            ->where('frame', null));
        Http::assertNothingSent();

        // Zurücksetzen: Vorgabe gilt wieder
        $this->delete('/einstellungen/menuepunkt/escola')->assertRedirect();
        $this->get('/einbindung/escola')->assertInertia(fn (Assert $page) => $page->where('module.open', 'tab'));
        $this->assertCount(1, app(JsonStore::class)->all(ModuleSettings::COLLECTION));
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->signInAs('admin');

        $this->put('/einstellungen/menuepunkt/cmi', ['url' => 'http://unsicher.example.org', 'open' => 'tab'])->assertSessionHasErrors('url');
        $this->put('/einstellungen/menuepunkt/cmi', ['url' => 'javascript:alert(1)', 'open' => 'tab'])->assertSessionHasErrors('url');
        $this->put('/einstellungen/menuepunkt/cmi', ['url' => '', 'open' => 'popup'])->assertSessionHasErrors('open');
        $this->put('/einstellungen/menuepunkt/gibt-es-nicht', ['url' => '', 'open' => 'tab'])->assertNotFound();
        $this->assertSame([], app(JsonStore::class)->all(ModuleSettings::COLLECTION));
    }

    public function test_changed_escola_address_is_checked_again(): void
    {
        Http::fake(['*' => Http::response('', 200, ['X-Frame-Options' => 'DENY'])]);
        $this->signInAs('admin');
        $this->put('/einstellungen/menuepunkt/escola', ['url' => 'https://escola.example.org', 'open' => 'both']);

        $this->get('/einbindung/escola')->assertInertia(fn (Assert $page) => $page->where('frame.embeddable', false));
    }

    public function test_browser_extension_page_and_download(): void
    {
        $this->signInAs('admin');
        $this->get('/einstellungen/browser-erweiterung')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('browser-extension')
            ->where('origin', rtrim((string) config('app.url'), '/')));

        $response = $this->get('/einstellungen/browser-erweiterung/download')->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file));
        $manifest = json_decode((string) $zip->getFromName('sbi-connector/manifest.json'), true);
        $zip->close();

        $this->assertSame(3, $manifest['manifest_version']);
        $this->assertSame(rtrim((string) config('app.url'), '/').'/*', $manifest['host_permissions'][0]);
        $this->assertSame(config('intranet.version'), $manifest['version']);
        $this->assertSame(['https://*/*'], $manifest['optional_host_permissions'], 'andere Websites nur nach Freigabe');

        // Erweiterung und Lesezeichen verwenden dasselbe Nachrichtenformat
        $background = (string) file_get_contents(base_path('browser-extension/background.js'));
        $this->assertStringContainsString("const RECORDER_TYPE = 'sbi-api-recorder';", $background);
        $this->assertStringContainsString("export const MESSAGE_TYPE = 'sbi-api-recorder';", (string) file_get_contents(resource_path('js/lib/api-recorder/capture.ts')));
    }
}
