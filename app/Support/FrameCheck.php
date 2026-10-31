<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Prüft, ob eine fremde Seite das Einbetten im Intranet erlaubt (X-Frame-Options,
 * Content-Security-Policy frame-ancestors). Ergebnis 6 Stunden zwischengespeichert.
 * Hinweis: Seiten mit Anmeldung können nach dem Login andere Regeln haben.
 */
class FrameCheck
{
    /**
     * @return array{embeddable: bool|null, reason: string, details?: array{x_frame_options: string|null, frame_ancestors: string|null, server: string|null, cloudflare: bool, status: int|null}}
     */
    public static function check(string $url): array
    {
        /** @var array{embeddable: bool|null, reason: string, details?: array{x_frame_options: string|null, frame_ancestors: string|null, server: string|null, cloudflare: bool, status: int|null}} */
        return Cache::remember('frame-check:v2:'.sha1($url), now()->addHours(6), fn (): array => self::fetch($url));
    }

    /**
     * Ohne Zwischenspeicher prüfen (Knopf «Einbetten prüfen» in den Einstellungen) und Ergebnis neu merken.
     *
     * @return array{embeddable: bool|null, reason: string, details?: array{x_frame_options: string|null, frame_ancestors: string|null, server: string|null, cloudflare: bool, status: int|null}}
     */
    public static function fresh(string $url): array
    {
        $result = self::fetch($url);
        Cache::put('frame-check:v2:'.sha1($url), $result, now()->addHours(6));

        return $result;
    }

    /**
     * @return array{embeddable: bool|null, reason: string, details?: array{x_frame_options: string|null, frame_ancestors: string|null, server: string|null, cloudflare: bool, status: int|null}}
     */
    private static function fetch(string $url): array
    {
        try {
            $response = Http::withOptions(['allow_redirects' => ['max' => 5]])
                ->withHeaders(['User-Agent' => 'SekBuelach-Intranet/1 (Einbettungsprüfung)'])
                ->timeout(6)
                ->get($url);
        } catch (Throwable) {
            return ['embeddable' => null, 'reason' => 'Seite nicht erreichbar'];
        }

        $frameOptions = (string) $response->header('X-Frame-Options');
        $csp = (string) $response->header('Content-Security-Policy');
        $server = (string) $response->header('Server');
        $result = self::evaluate($frameOptions, $csp, (string) parse_url((string) config('app.url'), PHP_URL_HOST));

        // Angaben für die Diagnose auf der Seite (wer verhindert das Einbetten?)
        return $result + ['details' => [
            'x_frame_options' => $frameOptions !== '' ? $frameOptions : null,
            'frame_ancestors' => preg_match('/frame-ancestors\s+([^;]*)/i', $csp, $m) === 1 ? trim($m[1]) : null,
            'server' => $server !== '' ? $server : null,
            'cloudflare' => $response->header('CF-Ray') !== '' || stripos($server, 'cloudflare') !== false,
            'status' => $response->status(),
        ]];
    }

    /**
     * @return array{embeddable: bool, reason: string}
     */
    public static function evaluate(string $frameOptions, string $csp, string $ownHost): array
    {
        if (preg_match('/frame-ancestors\s+([^;]*)/i', $csp, $m) === 1) {
            $sources = preg_split('/\s+/', trim($m[1])) ?: [];
            foreach ($sources as $source) {
                $host = (string) (parse_url(str_contains($source, '://') ? $source : 'https://'.$source, PHP_URL_HOST) ?? '');
                if ($source === '*' || ($host !== '' && ($host === $ownHost || (str_starts_with($host, '*.') && str_ends_with($ownHost, substr($host, 1)))))) {
                    return ['embeddable' => true, 'reason' => 'Erlaubt (frame-ancestors)'];
                }
            }

            $allowed = trim($m[1]) === "'self'" ? 'nur Seiten der eigenen Adresse' : 'nur: '.trim($m[1]);

            return ['embeddable' => false, 'reason' => "Die Seite darf nur von bestimmten Adressen eingebettet werden ({$allowed}) – das Intranet gehört nicht dazu (Content-Security-Policy frame-ancestors)"];
        }

        $value = strtoupper(trim($frameOptions));
        if ($value === 'DENY' || $value === 'SAMEORIGIN') {
            return ['embeddable' => false, 'reason' => "Anbieter verbietet Einbetten (X-Frame-Options {$value})"];
        }

        return ['embeddable' => true, 'reason' => 'Keine Einschränkung gefunden'];
    }
}
