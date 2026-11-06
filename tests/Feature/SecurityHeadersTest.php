<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Support\ModuleSettings;
use Tests\TestCase;

/**
 * Schutz-Kopfzeilen (#147): kein Einbetten des Intranets durch fremde Seiten, im Rahmen nur eingestellte Anwendungen.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_headers_on_every_response(): void
    {
        $response = $this->get('/anmelden');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);

        // Auch API und Fehlerseiten
        $this->getJson('/api/v1/gibt-es-nicht')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_frame_src_only_for_embeds_that_open_in_a_frame(): void
    {
        $sources = app(SecurityHeaders::class)->frameSources();
        // Lehrplan 21: Rahmen mit Knopf «Neuer Tab»
        $this->assertContains('https://zh.lehrplan.ch', $sources);
        // Apple School Manager und SL-Planer: immer neuer Tab – nicht im Rahmen erlaubt
        $this->assertNotContains('https://school.apple.com', $sources);
        $this->assertNotContains('https://slplaner.ch', $sources);

        // Einstellung übersteuert: andere Adresse im Rahmen, Tab entfernt sie
        app(ModuleSettings::class)->save('sl-planer', 'https://planer.example.ch/login', 'both', null);
        $this->assertContains('https://planer.example.ch', app(SecurityHeaders::class)->frameSources());
        app(ModuleSettings::class)->save('lehrplan21', null, 'tab', null);
        $this->assertNotContains('https://zh.lehrplan.ch', app(SecurityHeaders::class)->frameSources());

        $csp = (string) $this->get('/anmelden')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-src 'self' ", $csp);
        $this->assertStringContainsString('https://planer.example.ch', $csp);
    }

    public function test_only_https_origins(): void
    {
        $this->assertSame('https://a.example.ch', SecurityHeaders::origin('https://A.example.ch/pfad?x=1'));
        $this->assertSame('https://a.example.ch:8443', SecurityHeaders::origin('https://a.example.ch:8443/'));
        $this->assertNull(SecurityHeaders::origin('http://a.example.ch'));
        $this->assertNull(SecurityHeaders::origin('javascript:alert(1)'));
        $this->assertNull(SecurityHeaders::origin('https://a.example.ch; script-src *'));
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('/anmelden')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/anmelden')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
