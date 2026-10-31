<?php

namespace Tests\Feature;

use App\Plugins\PluginManager;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Plugins\Lmvz\Plugin;
use Tests\TestCase;

/**
 * LMVZ (inoffiziell): liest öffentliche Artikelseiten. Die Testseite ist erfunden und bildet nur den Aufbau nach.
 */
class PluginLmvzTest extends TestCase
{
    private const PRODUCT = 'https://shop.lmvz.ch/de/Katalog/Test-Reihe/Testbuch-Arbeitsheft-1234.html';

    private function page(): string
    {
        return <<<'HTML'
        <html><head>
        <meta property="og:title" content="Testbuch 1 &ndash; Arbeitsheft">
        <meta property="product:price:amount" content="24.50">
        <meta property="product:price:currency" content="CHF">
        </head><body><h1>Testbuch 1</h1>
        <dl><dt>Artikelnummer</dt><dd>123400.01</dd><dt>ISBN</dt><dd>978-3-03713-000-0</dd></dl>
        <p>CHF 24.50 inkl. Mwst.</p></body></html>
        HTML;
    }

    public function test_plugin_is_marked_unofficial(): void
    {
        $this->signInAs('admin');

        $this->get('/plugins/lmvz')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('plugin.unofficial', true)
            ->where('plugin.service.auth', 'none'));
        $this->get('/plugins/jamf')->assertInertia(fn (Assert $page) => $page->where('plugin.unofficial', false));
    }

    public function test_product_page_is_parsed(): void
    {
        $this->assertSame([
            'title' => 'Testbuch 1 – Arbeitsheft',
            'article_number' => '123400.01',
            'isbn' => '978-3-03713-000-0',
            'price' => '24.50',
        ], Plugin::parseProduct($this->page()));

        // Ohne Meta-Daten: Titel aus h1, fehlende Angaben leer
        $this->assertSame(['title' => 'Nur Titel', 'article_number' => '', 'isbn' => '', 'price' => ''], Plugin::parseProduct('<h1>Nur Titel</h1>'));
    }

    public function test_only_lmvz_product_pages_are_allowed(): void
    {
        $this->assertSame(['reihe' => 'Test-Reihe', 'artikel' => 'Testbuch-Arbeitsheft-1234'], Plugin::productPath(self::PRODUCT));
        $this->assertSame('1234', Plugin::productId('Testbuch-Arbeitsheft-1234'));

        foreach ([
            'http://shop.lmvz.ch/de/Katalog/Test-Reihe/Testbuch-1234.html',
            'https://shop.lmvz.ch.example.org/de/Katalog/A/B-1.html',
            'https://example.org/de/Katalog/A/B-1.html',
            'https://shop.lmvz.ch/de/basket.htm',
            'https://shop.lmvz.ch/de/Katalog/../../etc/passwd.html',
        ] as $url) {
            $this->assertNull(Plugin::productPath($url), $url);
        }
    }

    public function test_lookup_action_reads_articles_and_skips_foreign_links(): void
    {
        Http::fake([
            'shop.lmvz.ch/de/' => Http::response('<title>LMVZ Shop</title>'),
            'shop.lmvz.ch/de/Katalog/*' => Http::response($this->page()),
            '*' => Http::response('nicht erlaubt', 500),
        ]);
        $manager = app(PluginManager::class);
        $manager->saveSettings('lmvz', ['max_items' => 5, '_allow_write' => false]);
        $manager->setEnabled('lmvz', true);
        $this->signInAs('admin');

        $this->postJson('/plugins/lmvz/test')->assertOk()->assertJson(['ok' => true]);

        $result = $this->postJson('/plugins/lmvz/aktion/lookup', ['options' => [
            'urls' => self::PRODUCT."\nhttps://example.org/fremd.html",
        ]])->assertOk()->json();

        $this->assertStringContainsString('1 Artikel nachgeschlagen', $result['message']);
        $this->assertStringContainsString('Übersprungen', $result['message']);
        $this->assertSame('123400.01', $result['rows'][0]['article_number']);
        $this->assertSame('1234', $result['rows'][0]['product_id']);

        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'example.org'));
        Http::assertSent(fn (Request $r): bool => $r->url() === self::PRODUCT);
    }
}
