<?php

namespace Tests\Feature;

use Tests\TestCase;

/** PWA (docs/KONZEPT-PWA.md): Manifest, Symbole, Service Worker ohne Seiten-Cache. */
class PwaTest extends TestCase
{
    public function test_manifest_is_valid_and_icons_exist(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertIsArray($manifest);
        $this->assertSame('de-CH', $manifest['lang']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertStringStartsWith('/start', $manifest['start_url']);
        $purposes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$width, $height] = (array) getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], "{$width}x{$height}");
            $purposes[] = $icon['purpose'];
        }
        $this->assertContains('maskable', $purposes);
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));
    }

    public function test_service_worker_never_caches_pages_or_api(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        // Seiten nur vom Netz, Offline-Seite als Ersatz
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString("caches.match('/offline.html')", $sw);
        // Zwischengespeichert werden nur Build-Dateien, Symbole, Schriften
        $this->assertStringContainsString("url.pathname.startsWith('/build/')", $sw);
        $this->assertStringNotContainsString('/api/', $sw);
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_pages_link_the_manifest(): void
    {
        $this->signInAs('lehrperson');
        $html = (string) $this->get('/start')->getContent();

        // Manifest nach dem Erscheinungsbild (#216), Vorgabe EduKSX
        $this->assertStringContainsString('<link rel="manifest" href="/app.webmanifest"', $html);
        $this->assertStringContainsString('<meta name="theme-color"', $html);
        $this->assertStringContainsString('<meta name="app-build"', $html);
        $this->assertStringContainsString('EduKSX-Intranet</title>', $html);

        $manifest = $this->get('/app.webmanifest')->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->json();
        $this->assertSame('EduKSX-Intranet', $manifest['name']);
        $this->assertSame('/start?quelle=app', $manifest['start_url']);
        $this->assertContains('maskable', array_column($manifest['icons'], 'purpose'));
    }
}
