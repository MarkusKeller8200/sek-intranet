<?php

namespace App\Plugins;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Was ein Plugin während eines Laufs zur Verfügung hat.
 *
 * - settings()/secret(): Einstellungen, Zugangsdaten entschlüsselt (nur serverseitig)
 * - http(): HTTP-Client mit Basis-URL, Anmeldung, Zeitlimit und Wiederholung bei 429/503
 * - call(): einen im Manifest beschriebenen Endpunkt aufrufen
 * - log(): Zeile ins Lauf-Protokoll (Zugangsdaten werden geschwärzt)
 * - users(): Personen des Intranets (für Übernahmen)
 * - demo(): true, wenn das Plugin simulierte Antworten liefert (DemoResponder) – dann geht nichts an den Dienst
 */
final class PluginContext
{
    /** @var list<string> */
    private array $lines = [];

    private ?DemoResponder $demo = null;

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, string>  $secrets
     */
    public function __construct(
        public readonly PluginManifest $manifest,
        private readonly array $settings,
        private readonly array $secrets,
        public readonly bool $allowWrite,
        public readonly ?IntranetUser $user,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Einstellung; ohne gespeicherten Wert gilt die Vorgabe aus plugin.json («default»), dann $default.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $value = $this->settings[$key] ?? null;
        if ($value !== null && $value !== '') {
            return $value;
        }
        foreach ($this->manifest->settings() as $setting) {
            if (($setting['key'] ?? null) === $key && isset($setting['default']) && $setting['default'] !== '') {
                return $setting['default'];
            }
        }

        return $default;
    }

    public function secret(string $key): ?string
    {
        $value = $this->secrets[$key] ?? null;

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * Demo-Modus einschalten (PluginManager, wenn das Plugin DemoResponder umsetzt und demoActive() gilt).
     */
    public function useDemo(DemoResponder $responder): void
    {
        $this->demo = $responder;
    }

    public function demo(): bool
    {
        return $this->demo !== null;
    }

    public function users(): UserRepository
    {
        return $this->userRepository;
    }

    public function log(string $line): void
    {
        $this->lines[] = $this->redact($line);
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function requireWrite(): void
    {
        if (! $this->allowWrite) {
            throw new RuntimeException('Schreiben ist für dieses Plugin ausgeschaltet (Einstellungen → «Schreibende Aktionen erlauben»).');
        }
    }

    /**
     * Basis-URL aus dem Manifest (service.base) oder der Einstellung «base_url».
     * Weitere Adressen desselben Herstellers stehen unter service.hosts (Schlüssel → https-Adresse) und werden
     * je Endpunkt mit «host» gewählt. Platzhalter {name} werden durch Einstellungen ersetzt.
     */
    public function baseUrl(?string $host = null): string
    {
        $hosts = $this->manifest->hosts();
        if ($host !== null && $host !== PluginManifest::DEFAULT_HOST) {
            $base = $hosts[$host] ?? throw new InvalidArgumentException("Unbekannte Adresse «{$host}» (service.hosts).");
        } else {
            $base = (string) ($this->manifest->service()['base'] ?? $this->setting('base_url', ''));
        }
        $base = rtrim($this->fill($base), '/');

        if ($base === '' || ! str_starts_with($base, 'https://')) {
            throw new InvalidArgumentException('Basis-URL fehlt oder ist nicht https:// (Einstellungen).');
        }

        return $base;
    }

    /**
     * @param  string|null  $host  Schlüssel aus service.hosts (null = Hauptadresse)
     */
    public function http(?string $host = null): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl($host))
            ->acceptJson()
            ->withUserAgent('SekBuelach-Intranet/'.(string) ($this->manifest->data['version'] ?? '1').' (Plugin '.$this->manifest->id().')')
            ->connectTimeout(5)
            ->timeout((int) ($this->manifest->service()['timeout'] ?? 20))
            ->retry(
                3,
                function (int $attempt, mixed $exception): int {
                    $retryAfter = $exception instanceof RequestException
                        ? (int) $exception->response->header('Retry-After')
                        : 0;

                    return max(1, min($retryAfter, 10)) * 1000 * $attempt;
                },
                fn (mixed $exception): bool => $exception instanceof RequestException
                    && in_array($exception->response->status(), [429, 503], true),
                throw: false,
            );

        // Anmeldung nur an die Adressen, für die sie gilt (service.auth.hosts), nie an andere Server
        return $this->manifest->authAppliesTo($host ?? PluginManifest::DEFAULT_HOST) ? $this->authenticate($request) : $request;
    }

    /**
     * Endpunkt aus dem Manifest aufrufen.
     *
     * Mit $file geht der Aufruf als multipart/form-data: Teil «file» (bzw. field) mit der Datei, Teil «data»
     * (bzw. data_field) mit $body als JSON (z. B. DeepSign POST /documents/file).
     *
     * @param  array<string, mixed>  $params  Pfad- ({name}) und Abfrageparameter
     * @param  array<string, mixed>|null  $body
     * @param  array{name: string, contents: string, mime?: string, field?: string, data_field?: string}|null  $file
     */
    public function call(string $endpointId, array $params = [], ?array $body = null, ?array $file = null): Response
    {
        $endpoint = $this->manifest->endpoint($endpointId)
            ?? throw new InvalidArgumentException("Unbekannter Endpunkt «{$endpointId}».");

        $method = strtoupper((string) ($endpoint['method'] ?? 'GET'));
        if (PluginManifest::endpointWrites($endpoint)) {
            $this->requireWrite();
        }

        $path = (string) $endpoint['path'];
        $query = [];

        /** @var list<array<string, mixed>> $definitions */
        $definitions = is_array($endpoint['params'] ?? null) ? $endpoint['params'] : [];
        foreach ($definitions as $param) {
            $name = (string) $param['name'];
            $value = $params[$name] ?? ($param['default'] ?? null);
            if ($value === null || $value === '') {
                continue;
            }
            if (str_contains($path, '{'.$name.'}')) {
                $path = str_replace('{'.$name.'}', rawurlencode((string) $value), $path);
            } elseif (($param['repeat'] ?? false) === true) {
                // Mehrfacher Parameter (z. B. ClickTime filters=a&filters=b): Liste oder Text mit «|»
                $items = is_array($value) ? $value : explode('|', (string) $value);
                $items = array_values(array_filter(array_map(fn (mixed $v): string => trim(is_scalar($v) ? (string) $v : ''), $items), fn (string $v): bool => $v !== ''));
                if ($items !== []) {
                    $query[$name] = $items;
                }
            } else {
                $query[$name] = $value;
            }
        }

        $pathParams = [];
        foreach ($params as $name => $value) {
            if (str_contains($path, '{'.$name.'}')) {
                $path = str_replace('{'.$name.'}', rawurlencode((string) $value), $path);
            }
        }
        foreach ($definitions as $param) {
            $name = (string) $param['name'];
            if (str_contains((string) $endpoint['path'], '{'.$name.'}')) {
                $value = $params[$name] ?? ($param['default'] ?? null);
                $pathParams[$name] = is_scalar($value) ? (string) $value : '';
            }
        }

        if (preg_match('/\{[a-z0-9_]+\}/i', $path) === 1) {
            throw new InvalidArgumentException("Pfadparameter fehlt: {$path}");
        }

        if ($this->demo !== null) {
            $this->log("{$method} {$path} (Demo-Modus, nichts an den Dienst gesendet)");
            $demoBody = $file === null ? $body : ($body ?? []) + ['_file' => ['name' => $file['name'], 'mime' => $file['mime'] ?? 'application/pdf', 'contents' => $file['contents']]];
            $answer = $this->demo->demoResponse($endpoint, $pathParams, $query, $demoBody, $this);
            $json = array_key_exists('json', $answer) ? (string) json_encode($answer['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $response = new Response(new Psr7Response($answer['status'], $json === '' ? [] : ['Content-Type' => 'application/json'], $json));
            $this->log("→ HTTP {$response->status()} (Demo)");

            return $response;
        }

        // Personendaten (endpoints[].personal_data) nur auf dem Schweizer Server – auch nicht im Endtest
        // Ausnahme: für einen Test mit dem Admin-PIN befristet freigeschaltet (PersonalDataUnlock)
        if (($endpoint['personal_data'] ?? false) === true && ! (bool) config('intranet.server_in_switzerland')
            && ! app(PersonalDataUnlock::class)->active($this->manifest->id())) {
            throw new RuntimeException('«'.(string) ($endpoint['label'] ?? $endpointId).'» liefert Personendaten: echte Aufrufe erst auf dem Schweizer Server (hier nur Demo-Modus, Endtest ohne Personendaten oder befristet mit dem Admin-PIN freigeschaltet).');
        }
        if (($endpoint['personal_data'] ?? false) === true && ! (bool) config('intranet.server_in_switzerland')) {
            $this->log('Personendaten-Endpunkt mit Admin-PIN-Freischaltung (Test).');
        }

        $this->log("{$method} {$path}");
        $host = isset($endpoint['host']) ? (string) $endpoint['host'] : null;
        $repeated = array_filter($query, 'is_array');
        if ($repeated !== []) {
            // Wiederholte Parameter ohne [] (name=a&name=b), direkt an den Pfad
            $parts = [];
            foreach ($query as $name => $value) {
                foreach (is_array($value) ? $value : [$value] as $v) {
                    $parts[] = rawurlencode((string) $name).'='.rawurlencode(is_scalar($v) ? (string) $v : '');
                }
            }
            $path .= (str_contains($path, '?') ? '&' : '?').implode('&', $parts);
            $query = [];
        }
        // Leere «query»-Option würde bei Guzzle die Abfrage im Pfad ersetzen
        $request = $query === [] ? $this->http($host) : $this->http($host)->withQueryParameters($query);

        if ($file !== null) {
            $request = $request->attach($file['field'] ?? 'file', $file['contents'], $file['name'], ['Content-Type' => $file['mime'] ?? 'application/pdf']);
            if ($body !== null) {
                $request = $request->attach($file['data_field'] ?? 'data', (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'data.json', ['Content-Type' => 'application/json']);
            }
            // Laravel hängt die Teile nur an, wenn «multipart» in den Optionen steht
            $response = $request->send($method, $path, ['multipart' => []]);
        } else {
            $response = $body === null
                ? $request->send($method, $path)
                : $request->send($method, $path, ['json' => $body]);
        }

        $this->log("→ HTTP {$response->status()}");

        return $response;
    }

    /**
     * Ersetzt Zugangsdaten in Texten durch «•••».
     */
    public function redact(string $text): string
    {
        foreach ($this->secrets as $secret) {
            if (strlen($secret) >= 4) {
                $text = str_replace($secret, '•••', $text);
            }
        }

        return (string) preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]{8,}/', '$1•••', $text);
    }

    public function fill(string $value): string
    {
        return (string) preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m): string {
            $v = $this->setting($m[1]) ?? $this->secret($m[1]);

            return is_scalar($v) ? (string) $v : $m[0];
        }, $value);
    }

    private function authenticate(PendingRequest $request): PendingRequest
    {
        $auth = $this->manifest->auth();

        return match ($auth['type'] ?? 'none') {
            'bearer' => $request->withToken($this->required((string) $auth['token_setting'])),
            'header' => $request->withHeaders($this->headerAuth($auth)),
            'basic' => $request->withBasicAuth(
                (string) $this->setting((string) $auth['username_setting'], ''),
                $this->required((string) $auth['password_setting']),
            ),
            'oauth2_client_credentials' => $request->withToken($this->oauthToken($auth)),
            // Passwort-Anmeldung (z. B. KLARA/ePost); ist ein API-Schlüssel hinterlegt, gilt dieser
            'oauth2_password' => match (true) {
                $this->apiKey($auth) === null => $request->withToken($this->oauthToken($auth)),
                // ein eingefügtes Zugriffstoken (JWT) gilt als Bearer-Token, sonst als API-Schlüssel
                self::isJwt((string) $this->apiKey($auth)) => $request->withToken((string) $this->apiKey($auth)),
                default => $request->withHeaders([(string) ($auth['api_key_header'] ?? 'X-API-KEY') => (string) $this->apiKey($auth)]),
            },
            default => $request,
        };
    }

    /**
     * Kopfzeilen-Anmeldung. Name und Präfix fest («header», «prefix») oder aus
     * Einstellungen («header_setting», «prefix_setting»), z. B. für Dienste, deren
     * Format je Installation verschieden ist.
     *
     * @param  array<string, mixed>  $auth
     * @return array<string, string>
     */
    private function headerAuth(array $auth): array
    {
        $name = isset($auth['header_setting'])
            ? (string) $this->setting((string) $auth['header_setting'], $auth['header'] ?? 'Authorization')
            : (string) ($auth['header'] ?? 'Authorization');

        $prefix = isset($auth['prefix_setting'])
            ? trim((string) $this->setting((string) $auth['prefix_setting'], ''))
            : trim((string) ($auth['prefix'] ?? ''));

        $token = $this->required((string) $auth['setting']);

        return [$name => $prefix === '' || $prefix === 'none' ? $token : "{$prefix} {$token}"];
    }

    private static function isJwt(string $value): bool
    {
        $value = (string) preg_replace('/^Bearer\s+/i', '', $value);

        return preg_match('/^eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/', $value) === 1;
    }

    /**
     * @param  array<string, mixed>  $auth
     */
    private function apiKey(array $auth): ?string
    {
        if (! isset($auth['api_key_setting'])) {
            return null;
        }
        $key = $this->secret((string) $auth['api_key_setting']) ?? $this->setting((string) $auth['api_key_setting']);
        if (! is_scalar($key) || trim((string) $key) === '') {
            return null;
        }

        // «Bearer » mitkopiert? Weglassen.
        return (string) preg_replace('/^Bearer\s+/i', '', trim((string) $key));
    }

    /**
     * Holt (und merkt sich bis kurz vor Ablauf) ein Zugriffstoken per Client Credentials
     * oder Passwort-Anmeldung (oauth2_password, mit Zusatzfeldern aus «form»: Feld → Einstellung).
     *
     * @param  array<string, mixed>  $auth
     */
    private function oauthToken(array $auth): string
    {
        $tokenUrl = $this->fill((string) $auth['token_url']);
        if (! str_starts_with($tokenUrl, 'https://')) {
            $tokenUrl = $this->baseUrl().'/'.ltrim($tokenUrl, '/');
        }

        if (($auth['type'] ?? '') === 'oauth2_password') {
            $form = [
                'grant_type' => 'password',
                'username' => $this->required((string) ($auth['username_setting'] ?? 'username')),
                'password' => $this->required((string) ($auth['password_setting'] ?? 'password')),
            ];
            foreach ((array) ($auth['form'] ?? []) as $field => $setting) {
                $form[(string) $field] = $this->required((string) $setting);
            }
        } else {
            $form = [
                'grant_type' => 'client_credentials',
                // client_id als Einstellung oder als Zugangsdatum (Typ password, z. B. ClickTime)
                'client_id' => (string) ($this->setting((string) ($auth['client_id_setting'] ?? 'client_id')) ?? $this->secret((string) ($auth['client_id_setting'] ?? 'client_id')) ?? ''),
                'client_secret' => $this->required((string) ($auth['client_secret_setting'] ?? 'client_secret')),
            ];
        }
        if (isset($auth['scope'])) {
            $form['scope'] = $this->fill((string) $auth['scope']);
        }

        $cacheKey = 'plugin-token:'.$this->manifest->id().':'.hash('sha256', $tokenUrl.'|'.json_encode($form));
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->acceptJson()->connectTimeout(5)->timeout(20)->post($tokenUrl, $form);
        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            $detail = $response->json('error_description') ?? $response->json('error') ?? $response->status();
            throw new RuntimeException('Anmeldung beim Dienst fehlgeschlagen: '.$this->redact(is_scalar($detail) ? (string) $detail : 'unbekannt'));
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 120);
        Cache::put($cacheKey, $token, $ttl);
        $this->log('Zugriffstoken erhalten (gültig ca. '.intdiv($ttl, 60).' min).');

        return $token;
    }

    private function required(string $key): string
    {
        $value = $this->secret($key) ?? $this->setting($key);

        if (! is_scalar($value) || (string) $value === '') {
            throw new InvalidArgumentException("Zugangsdaten fehlen: {$key} (Einstellungen).");
        }

        return (string) $value;
    }
}
