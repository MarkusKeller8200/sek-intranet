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
     * @return array{embeddable: bool|null, reason: string}
     */
    public static function check(string $url): array
    {
        /** @var array{embeddable: bool|null, reason: string} */
        return Cache::remember('frame-check:'.sha1($url), now()->addHours(6), fn (): array => self::fetch($url));
    }

    /**
     * @return array{embeddable: bool|null, reason: string}
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

        return self::evaluate(
            (string) $response->header('X-Frame-Options'),
            (string) $response->header('Content-Security-Policy'),
            (string) parse_url((string) config('app.url'), PHP_URL_HOST),
        );
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

            return ['embeddable' => false, 'reason' => 'Anbieter erlaubt nur eigene Einbettungen (frame-ancestors)'];
        }

        $value = strtoupper(trim($frameOptions));
        if ($value === 'DENY' || $value === 'SAMEORIGIN') {
            return ['embeddable' => false, 'reason' => "Anbieter verbietet Einbetten (X-Frame-Options {$value})"];
        }

        return ['embeddable' => true, 'reason' => 'Keine Einschränkung gefunden'];
    }
}
