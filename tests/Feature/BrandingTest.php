<?php

namespace Tests\Feature;

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
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
        $this->get('/branding/signet')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/branding/logo_dunkel')->assertNotFound();
        $this->get('/branding/irgendwas')->assertNotFound();

        // Logo entfernen → zurück zu EduKSX
        $this->signInAs('admin');
        $this->delete('/einstellungen/erscheinungsbild/logo_hell')->assertRedirect();
        $this->assertSame('eduksx', app(Branding::class)->einstellung()['marke']);
        Storage::disk('local')->assertMissing('branding/logo_hell.svg');
    }

    public function test_existing_installation_keeps_school_logo(): void
    {
        Storage::fake('local');
        $branding = app(Branding::class);
        // Daten von «vor» dem Stichtag (hier: Stichtag in der Zukunft)
        $this->assertTrue($branding->bestandUebernehmen('2999-01-01'));
        $this->assertSame('eigene', $branding->einstellung()['marke']);
        $this->assertSame('Sekundarschule Bülach', $branding->einstellung()['name']);
        Storage::disk('local')->assertExists('branding/logo_hell.svg');
        // nur einmal
        $this->assertFalse($branding->bestandUebernehmen('2999-01-01'));
    }

    public function test_new_installation_keeps_eduksx(): void
    {
        $this->assertFalse(app(Branding::class)->bestandUebernehmen('2000-01-01'));
        $this->assertSame('eduksx', app(Branding::class)->einstellung()['marke']);
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
