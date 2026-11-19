<?php

namespace Tests\Feature;

use App\Data\Store\JsonStore;
use App\Support\Branding;
use App\Support\Themes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * White-Labeling (#216): Marke, eigenes Logo, eingebaute Farbkonzepte EduKSX und MarKusSXCH.
 */
class BrandingTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\xff\xff?\0\x05\xfe\x02\xfe\xa7\x35\x81\x84\0\0\0\0IEND\xaeB`\x82";

    public function test_default_is_eduksx_wordmark(): void
    {
        $this->signInAs('lehrperson');
        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('branding.marke', 'eduksx')
            ->where('branding.wortmarke', 'education')
            ->where('branding.logo', null)
            ->where('branding.signet', '/images/brands/eduksx-intranet-tile-light.svg'));
        $this->get('/einstellungen/erscheinungsbild')->assertForbidden();
    }

    public function test_own_logo_upload_and_safe_delivery(): void
    {
        Storage::fake('local');
        $this->signInAs('admin');
        $this->get('/einstellungen/erscheinungsbild')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/branding')->where('einstellung.marke', 'eduksx'));

        // «Eigenes Logo» erst mit Logo
        $this->put('/einstellungen/erscheinungsbild', ['marke' => 'eigene', 'name' => 'Sekundarschule Musterhausen'])->assertSessionHasErrors('marke');

        // Bild am Inhalt erkannt; SVG mit Skript abgelehnt
        $this->post('/einstellungen/erscheinungsbild/logo_hell', ['datei' => UploadedFile::fake()->createWithContent('logo.png', 'kein Bild')])->assertSessionHasErrors('datei');
        $boese = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $this->post('/einstellungen/erscheinungsbild/logo_hell', ['datei' => UploadedFile::fake()->createWithContent('logo.svg', $boese)])->assertSessionHasErrors('datei');
        $extern = '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/x.png"/></svg>';
        $this->post('/einstellungen/erscheinungsbild/logo_hell', ['datei' => UploadedFile::fake()->createWithContent('logo.svg', $extern)])->assertSessionHasErrors('datei');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#007ebb"/></svg>';
        $this->post('/einstellungen/erscheinungsbild/logo_hell', ['datei' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])->assertSessionHasNoErrors();
        $this->post('/einstellungen/erscheinungsbild/signet', ['datei' => UploadedFile::fake()->createWithContent('signet.png', self::PNG)])->assertSessionHasNoErrors();
        $this->put('/einstellungen/erscheinungsbild', ['marke' => 'eigene', 'name' => 'Sekundarschule Musterhausen'])->assertSessionHasNoErrors();

        $this->get('/start')->assertInertia(fn (Assert $page) => $page
            ->where('branding.marke', 'eigene')
            ->where('branding.wortmarke', null)
            ->where('branding.name', 'Sekundarschule Musterhausen')
            ->where('branding.logo.hell', fn (string $u): bool => str_starts_with($u, '/branding/logo_hell?v='))
            ->where('branding.logo.dunkel', fn (string $u): bool => str_starts_with($u, '/branding/logo_hell?v='))
            ->where('branding.signet', fn (string $u): bool => str_starts_with($u, '/branding/signet?v=')));

        // ohne Anmeldung abrufbar (Anmeldeseite), SVG in Sandbox
        auth()->logout();
        $this->get('/branding/logo_hell')->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox; frame-ancestors 'self'");
        $this->get('/branding/signet')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/branding/logo_dunkel')->assertNotFound();
        $this->get('/branding/irgendwas')->assertNotFound();

        // Logo entfernen → zurück zu EduKSX
        $this->signInAs('admin');
        $this->delete('/einstellungen/erscheinungsbild/logo_hell')->assertRedirect();
        $this->assertSame('eduksx', app(Branding::class)->einstellung()['marke']);
        Storage::disk('local')->assertMissing('branding/logo_hell.svg');
    }

    public function test_school_logo_is_replaced_by_eduksx_but_own_upload_stays(): void
    {
        Storage::fake('local');
        $store = app(JsonStore::class);
        $branding = app(Branding::class);
        // Stand nach dem ersten Update: SekBülach-Logo als «eigenes» Logo übernommen
        Storage::disk('local')->put('branding/logo_hell.svg', (string) file_get_contents(public_path('images/sekbuelach-logo.svg')));
        Storage::disk('local')->put('branding/signet.svg', (string) file_get_contents(public_path('images/sekbuelach-icon.svg')));
        $store->insert(Branding::COLLECTION, ['id' => Branding::ID, 'marke' => 'eigene', 'name' => 'Sekundarschule Bülach', 'logo_hell' => 'branding/logo_hell.svg', 'signet' => 'branding/signet.svg']);

        $this->assertTrue($branding->sekbuelachErsetzen());
        $this->assertSame(['eduksx', 'Sekundarschule Bülach', null, null], [$branding->einstellung()['marke'], $branding->einstellung()['name'], $branding->einstellung()['logo_hell'], $branding->einstellung()['signet']]);
        Storage::disk('local')->assertMissing('branding/logo_hell.svg');
        $this->assertFalse($branding->sekbuelachErsetzen());

        // ein selbst hochgeladenes Logo bleibt
        Storage::disk('local')->put('branding/logo_hell.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $store->update(Branding::COLLECTION, Branding::ID, ['marke' => 'eigene', 'logo_hell' => 'branding/logo_hell.svg']);
        $this->assertFalse($branding->sekbuelachErsetzen());
        $this->assertSame('eigene', $branding->einstellung()['marke']);
    }

    public function test_builtin_color_schemes(): void
    {
        $themes = app(Themes::class);
        $ids = array_column($themes->all(), 'id');
        $this->assertSame(['standard', 'eduksx', 'markussx'], array_slice($ids, 0, 3));

        $this->signInAs('admin');
        $this->post('/farbkonzept/themes/eduksx/aktivieren')->assertRedirect();
        $this->assertSame('eduksx', $themes->activeId());
        $css = $themes->activeCss();
        $this->assertStringContainsString('--primary:#a85400', $css);
        $this->assertStringContainsString('--primary:#ff8a00', $css);

        // nicht änderbar, nicht löschbar; ein gespeichertes Theme löst es ab
        $this->delete('/farbkonzept/themes/eduksx')->assertRedirect();
        $this->assertSame('eduksx', $themes->activeId());
        $this->post('/farbkonzept/themes/markussx/aktivieren')->assertRedirect();
        $this->assertSame('markussx', $themes->activeId());
        $this->post('/farbkonzept/themes/standard/aktivieren')->assertRedirect();
        $this->assertSame('standard', $themes->activeId());
        $this->assertSame('', $themes->activeCss());
        // Datensatz für das eingebaute Farbkonzept erscheint nicht als Theme
        $this->assertNotContains('_eingebaut', array_column($themes->all(), 'id'));
    }
}
