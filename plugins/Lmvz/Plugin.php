<?php

namespace Plugins\Lmvz;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;

/**
 * LMVZ (Lehrmittelverlag Zürich) – inoffiziell. Es gibt keine offizielle Schnittstelle.
 *
 * - Webshop (shop.lmvz.ch): öffentliche Artikelseiten, ohne Anmeldung; nur Adressen auf shop.lmvz.ch.
 * - LMVZ Digital (digital.lmvz.ch, service.hosts «digital»): interne REST-Schnittstelle aus einer Aufzeichnung
 *   mit dem API-Recorder; Token nur für diese Adresse (service.auth.hosts). Die Lizenzübersicht enthält keine
 *   Personendaten; Endpunkte mit Personendaten sind nur im API-Tester verfügbar.
 */
class Plugin extends BasePlugin
{
    public const HOST = 'shop.lmvz.ch';

    public function test(PluginContext $ctx): array
    {
        $response = $ctx->call('home');

        if (! $response->successful()) {
            return ['ok' => false, 'message' => "Der LMVZ-Shop antwortet mit HTTP {$response->status()}."];
        }

        if (stripos($response->body(), 'lmvz') === false) {
            return ['ok' => false, 'message' => 'Antwort sieht nicht nach dem LMVZ-Shop aus – Aufbau der Website geändert?'];
        }
        $message = 'LMVZ-Shop erreichbar (inoffiziell, öffentliche Seiten).';

        // LMVZ Digital nur prüfen, wenn Token und Organisation hinterlegt sind
        if ($ctx->secret('digital_token') === null || $this->orgId($ctx) === '') {
            return ['ok' => true, 'message' => $message.' LMVZ Digital: Token und Organisations-ID fehlen noch.'];
        }
        $org = $ctx->call('d-org', ['orgId' => $this->orgId($ctx)]);

        return match (true) {
            $org->successful() => ['ok' => true, 'message' => $message.' LMVZ Digital verbunden: «'.((string) ($org->json('name') ?? '?')).'».'],
            in_array($org->status(), [401, 403], true) => ['ok' => false, 'message' => $message.' LMVZ Digital: Token abgelaufen oder ohne Berechtigung (HTTP '.$org->status().') – neues Token hinterlegen.'],
            default => ['ok' => false, 'message' => $message." LMVZ Digital antwortet mit HTTP {$org->status()}."],
        };
    }

    public function widget(string $widget, PluginContext $ctx): array
    {
        $rows = $this->licenseRows($ctx);

        return ['items' => [
            ['label' => 'Lehrmittel', 'value' => count($rows)],
            ['label' => 'Lizenzen zugewiesen', 'value' => array_sum(array_column($rows, 'assigned'))],
            ['label' => 'frei', 'value' => array_sum(array_column($rows, 'available'))],
        ]];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'lookup' => $this->lookup($ctx, (string) ($options['urls'] ?? '')),
            'licenses' => $this->licenses($ctx),
            default => parent::action($action, $ctx, $options),
        };
    }

    private function orgId(PluginContext $ctx): string
    {
        return trim((string) $ctx->setting('org_id', ''));
    }

    /**
     * Lizenzübersicht der Schule (nur Zahlen je Lehrmittel, keine Personendaten).
     *
     * @return list<array<string, mixed>>
     */
    private function licenseRows(PluginContext $ctx): array
    {
        $org = $this->orgId($ctx);
        $view = trim((string) $ctx->setting('license_view', ''));
        if ($org === '' || $view === '') {
            throw new \InvalidArgumentException('Einstellungen «Organisations-ID» und «Pfadwert Lizenzübersicht» fehlen.');
        }

        $response = $ctx->call('d-licenses', ['view' => $view, 'orgId' => $org], ['sorting' => [], 'offset' => 0, 'limit' => 0]);
        if (in_array($response->status(), [401, 403], true)) {
            throw new \RuntimeException('LMVZ Digital: Token abgelaufen oder ohne Berechtigung – neues Token in den Einstellungen hinterlegen.');
        }
        if (! $response->successful()) {
            throw new \RuntimeException("LMVZ Digital antwortet mit HTTP {$response->status()}.");
        }

        return self::licenseOverview((array) $response->json());
    }

    /**
     * Antwort von /rest/lizenzpakete/…: licenseOverviewOneYearEP → Zeilen. Lizenzschlüssel werden nie übernommen.
     *
     * @param  array<mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function licenseOverview(array $data): array
    {
        $rows = [];
        foreach ((array) ($data['licenseOverviewOneYearEP'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $rows[] = [
                'media' => (string) ($item['fullMediaName'] ?? $item['mediaName'] ?? '–'),
                'part' => (string) ($item['mediaPart'] ?? ''),
                'model' => (string) ($item['licenseModel'] ?? ''),
                'duration' => isset($item['licenseDuration']) ? (int) $item['licenseDuration'] : null,
                'purchased' => (int) ($item['numSlotsPurchased'] ?? $item['numSlotsTotal'] ?? 0),
                'assigned' => (int) ($item['numSlotsAssigned'] ?? 0),
                'available' => (int) ($item['numSlotsAvailableTotal'] ?? 0),
                'expired' => (int) ($item['numSlotsExpired'] ?? 0),
                'validity' => (string) ($item['validityMsg'] ?? ''),
            ];
        }

        return $rows;
    }

    private function licenses(PluginContext $ctx): ActionResult
    {
        try {
            $rows = $this->licenseRows($ctx);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return ActionResult::warning($e->getMessage());
        }

        return ActionResult::table(count($rows).' Lehrmittel mit Lizenzen in LMVZ Digital.', [
            ['key' => 'media', 'label' => 'Lehrmittel'],
            ['key' => 'part', 'label' => 'Teil'],
            ['key' => 'model', 'label' => 'Lizenzmodell'],
            ['key' => 'duration', 'label' => 'Laufzeit (Monate)'],
            ['key' => 'purchased', 'label' => 'Gekauft'],
            ['key' => 'assigned', 'label' => 'Zugewiesen'],
            ['key' => 'available', 'label' => 'Frei'],
            ['key' => 'expired', 'label' => 'Abgelaufen'],
            ['key' => 'validity', 'label' => 'Gültigkeit'],
        ], $rows);
    }

    private function lookup(PluginContext $ctx, string $input): ActionResult
    {
        $max = max(1, min(50, (int) $ctx->setting('max_items', 20)));
        $lines = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $input) ?: []), fn (string $l): bool => $l !== ''));

        if ($lines === []) {
            return ActionResult::warning('Bitte mindestens einen Link zu einer Artikelseite angeben.');
        }

        $rows = [];
        $skipped = [];
        foreach (array_slice(array_unique($lines), 0, $max) as $url) {
            $parts = self::productPath($url);
            if ($parts === null) {
                $skipped[] = $url;

                continue;
            }

            $response = $ctx->call('product', $parts);
            if (! $response->successful()) {
                $rows[] = ['title' => '–', 'url' => $url, 'status' => "HTTP {$response->status()}"];

                continue;
            }

            $rows[] = self::parseProduct($response->body()) + [
                'product_id' => self::productId($parts['artikel']),
                'url' => $url,
                'status' => 'gelesen',
            ];
        }

        $message = count($rows).' Artikel nachgeschlagen.';
        if ($skipped !== []) {
            $message .= ' Übersprungen (keine Artikelseite auf '.self::HOST.'): '.implode(', ', array_slice($skipped, 0, 5));
        }
        if (count($lines) > $max) {
            $message .= " Nur die ersten {$max} Links abgefragt.";
        }

        return ActionResult::table($message, [
            ['key' => 'title', 'label' => 'Titel'],
            ['key' => 'article_number', 'label' => 'Artikelnummer'],
            ['key' => 'isbn', 'label' => 'ISBN'],
            ['key' => 'price', 'label' => 'Preis (CHF, Liste)'],
            ['key' => 'product_id', 'label' => 'Produkt-ID'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'url', 'label' => 'Link'],
        ], $rows);
    }

    /**
     * Link einer Artikelseite → Pfadteile. Nur shop.lmvz.ch, nur /de/Katalog/<Reihe>/<Artikel>.html.
     *
     * @return array{reihe: string, artikel: string}|null
     */
    public static function productPath(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || strtolower($parts['host'] ?? '') !== self::HOST || ($parts['scheme'] ?? '') !== 'https') {
            return null;
        }
        if (preg_match('#^/de/Katalog/([A-Za-z0-9._~%-]+)/([A-Za-z0-9._~%-]+)\.html$#i', $parts['path'] ?? '', $m) !== 1) {
            return null;
        }

        return ['reihe' => rawurldecode($m[1]), 'artikel' => rawurldecode($m[2])];
    }

    public static function productId(string $slug): string
    {
        return preg_match('/-(\d+)$/', $slug, $m) === 1 ? $m[1] : '';
    }

    /**
     * Angaben aus einer Artikelseite: Meta-Daten (Open Graph, product:price) und Seitentext.
     *
     * @return array{title: string, article_number: string, isbn: string, price: string}
     */
    public static function parseProduct(string $html): array
    {
        $meta = function (string $name) use ($html): string {
            $quoted = preg_quote($name, '/');
            foreach ([
                '/<meta[^>]+(?:property|name)=["\']'.$quoted.'["\'][^>]*content=["\']([^"\']*)["\']/i',
                '/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']'.$quoted.'["\']/i',
            ] as $pattern) {
                if (preg_match($pattern, $html, $m) === 1) {
                    return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5));
                }
            }

            return '';
        };

        $title = $meta('og:title');
        if ($title === '' && preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $m) === 1) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5));
        }

        $price = $meta('product:price:amount') ?: $meta('meta-product:price:amount');

        // Tags durch Leerzeichen ersetzen, damit «ISBN</dt><dd>978…» getrennt bleibt
        $text = (string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) preg_replace('/</', ' <', $html)), ENT_QUOTES | ENT_HTML5));
        $isbn = preg_match('/(?<!\d)97[89][-\s]?(?:\d[-\s]?){9}\d(?!\d)/', $text, $m) === 1 ? $m[0] : '';
        $article = preg_match('/Artikel(?:-?nummer|-?nr\.?)\D{0,20}(\d{5,7}\.\d{2})/i', $text, $m) === 1
            ? $m[1]
            : (preg_match('/(?<![\d.])(\d{6}\.\d{2})(?![\d.])/', $text, $m) === 1 ? $m[1] : '');

        return [
            'title' => $title !== '' ? $title : '–',
            'article_number' => $article,
            'isbn' => $isbn,
            'price' => $price,
        ];
    }
}
