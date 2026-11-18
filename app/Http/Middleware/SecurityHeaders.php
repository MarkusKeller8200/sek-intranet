<?php

namespace App\Http\Middleware;

use App\Support\ModuleSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schutz-Kopfzeilen für alle Antworten (#147).
 *
 * - Nur das Intranet selbst darf das Intranet einbetten (X-Frame-Options, CSP frame-ancestors) – gegen Clickjacking.
 * - Im Rahmen nur das Intranet und die Fachanwendungen bzw. Einbindungen, die als Rahmen eingestellt sind
 *   (CSP frame-src, aus config/intranet.php und den Einstellungen der Menüpunkte).
 * - Keine Plugins/Objekte, kein fremdes base, kein MIME-Raten, gekürzter Referrer, Geräte-Rechte aus, HSTS bei HTTPS.
 *
 * Eine vollständige CSP für Skripte und Stile folgt separat.
 */
final class SecurityHeaders
{
    /** Öffnen-Arten, bei denen die Anwendung im Rahmen läuft */
    private const FRAMED = ['embed', 'both'];

    public function __construct(private readonly ModuleSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Eine strengere Richtlinie der Antwort (z. B. hochgeladene SVG-Logos in einer Sandbox, #216) bleibt erhalten
        $eigene = $headers->get('Content-Security-Policy');
        $headers->set('Content-Security-Policy', is_string($eigene) && $eigene !== '' ? $eigene.(str_contains($eigene, 'frame-ancestors') ? '' : "; frame-ancestors 'self'") : implode('; ', [
            "frame-ancestors 'self'",
            'frame-src '.implode(' ', ["'self'", ...$this->frameSources()]),
            "object-src 'none'",
            "base-uri 'self'",
        ]));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Ursprünge (https://host[:port]) aller Einbindungen, die im Rahmen öffnen.
     *
     * @return list<string>
     */
    public function frameSources(): array
    {
        $origins = [];
        /** @param array<mixed> $items */
        $walk = function (array $items) use (&$walk, &$origins): void {
            foreach ($items as $key => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $embed = $item['embed'] ?? null;
                if (is_array($embed)) {
                    $setting = $this->settings->get((string) $key);
                    $url = is_string($setting['url'] ?? null) ? $setting['url'] : ($embed['url'] ?? null);
                    $open = is_string($setting['open'] ?? null) ? $setting['open'] : ($embed['open'] ?? 'both');
                    $origin = is_string($url) ? self::origin($url) : null;
                    if ($origin !== null && in_array($open, self::FRAMED, true)) {
                        $origins[$origin] = true;
                    }
                }
                if (is_array($item['items'] ?? null)) {
                    $walk($item['items']);
                }
            }
        };
        $walk((array) config('intranet.modules', []));

        return array_keys($origins);
    }

    /** Nur https-Adressen; Ursprung ohne Pfad. Null bei allem anderen (auch Platzhalter oder Leerzeichen). */
    public static function origin(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (($parts['scheme'] ?? '') !== 'https' || ! is_string($parts['host'] ?? null) || preg_match('/^[a-z0-9.-]+$/i', $parts['host']) !== 1) {
            return null;
        }

        return 'https://'.strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
