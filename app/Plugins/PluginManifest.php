<?php

namespace App\Plugins;

use InvalidArgumentException;

/**
 * Beschreibung eines Plugins aus plugins/<Ordner>/plugin.json (siehe PLUGINS.md).
 *
 * Bewusst als einfache, geprüfte Datenstruktur: alles, was die Oberfläche
 * anzeigt (Einstellungen, Aktionen, Endpunkte), kommt aus dem Manifest.
 */
final class PluginManifest
{
    public const API_VERSION = 1;

    public const SETTING_TYPES = ['text', 'url', 'password', 'select', 'check', 'number', 'textarea'];

    public const AUTH_TYPES = ['none', 'bearer', 'header', 'basic', 'oauth2_client_credentials', 'oauth2_password'];

    public const ACTION_KINDS = ['read', 'import', 'write'];

    public const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /** Schlüssel der Hauptadresse (service.base bzw. Einstellung base_url) */
    public const DEFAULT_HOST = 'default';

    /** full = mit Hersteller-Doku bestätigt, partial = teilweise/aus Aufzeichnung, planned = vorgemerkt */
    public const ENDPOINT_STATUS = ['full', 'partial', 'planned'];

    public const EVENTS = [
        'person.created', 'person.updated', 'person.deleted',
        'announcement.created', 'event.created', 'document.created',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly string $directory,
        public readonly array $data,
    ) {}

    public static function fromFile(string $file): self
    {
        $raw = file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($data)) {
            throw new InvalidArgumentException("{$file}: kein gültiges JSON.");
        }

        /** @var array<string, mixed> $data */
        return self::fromArray($data, basename(dirname($file)));
    }

    /**
     * Prüft ein Manifest (z. B. aus dem Generator, bevor es geschrieben wird).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data, string $directory): self
    {
        $manifest = new self($directory, $data);
        $manifest->validate();

        return $manifest;
    }

    public function id(): string
    {
        return (string) $this->data['id'];
    }

    public function name(): string
    {
        return (string) ($this->data['name'] ?? $this->id());
    }

    public function className(): string
    {
        // Standard: plugins/<Ordner>/Plugin.php mit Namespace Plugins\<Ordner>.
        // «class» im Manifest nur für Test-Plugins (tests/Fixtures).
        $class = $this->data['class'] ?? null;

        return is_string($class) && $class !== '' ? $class : 'Plugins\\'.$this->directory.'\\Plugin';
    }

    /**
     * @return array<string, mixed>
     */
    public function service(): array
    {
        $service = $this->data['service'] ?? [];

        return is_array($service) ? $service : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function auth(): array
    {
        $auth = $this->service()['auth'] ?? ['type' => 'none'];

        return is_array($auth) ? $auth : ['type' => 'none'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function settings(): array
    {
        return $this->list('settings');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actions(): array
    {
        return $this->list('actions');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function action(string $id): ?array
    {
        foreach ($this->actions() as $action) {
            if (($action['id'] ?? null) === $id) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Weitere Adressen desselben Herstellers (service.hosts: Schlüssel → https-Adresse).
     *
     * @return array<string, string>
     */
    public function hosts(): array
    {
        $hosts = $this->service()['hosts'] ?? [];

        return is_array($hosts) ? array_map('strval', array_filter($hosts, 'is_string')) : [];
    }

    /**
     * Gilt die Anmeldung für diese Adresse? Ohne service.auth.hosts: nur für die Hauptadresse und
     * – bei Plugins ohne weitere Adressen – für alle Endpunkte.
     */
    public function authAppliesTo(string $host): bool
    {
        $only = $this->auth()['hosts'] ?? null;

        return is_array($only) ? in_array($host, $only, true) : $host === self::DEFAULT_HOST;
    }

    /**
     * Schreibt ein Endpunkt? Standard: alles ausser GET. «effect» übersteuert, z. B. Listen per POST (read)
     * oder GET-Aufrufe, die etwas auslösen (write).
     *
     * @param  array<string, mixed>  $endpoint
     */
    public static function endpointWrites(array $endpoint): bool
    {
        $effect = $endpoint['effect'] ?? null;
        if ($effect === 'read' || $effect === 'write') {
            return $effect === 'write';
        }

        return strtoupper((string) ($endpoint['method'] ?? 'GET')) !== 'GET';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function endpoints(): array
    {
        $endpoints = $this->service()['endpoints'] ?? [];

        return is_array($endpoints) ? array_values(array_filter($endpoints, 'is_array')) : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function endpoint(string $id): ?array
    {
        foreach ($this->endpoints() as $endpoint) {
            if (($endpoint['id'] ?? null) === $id) {
                return $endpoint;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function widgets(): array
    {
        return $this->list('widgets');
    }

    /**
     * @return list<string>
     */
    public function events(): array
    {
        $events = $this->data['events'] ?? [];

        return is_array($events) ? array_values(array_map('strval', $events)) : [];
    }

    /**
     * Felder, die nie im Klartext an die Oberfläche gehen.
     *
     * @return list<string>
     */
    public function secretKeys(): array
    {
        return array_values(array_map(
            fn (array $s): string => (string) $s['key'],
            array_filter($this->settings(), fn (array $s): bool => ($s['type'] ?? '') === 'password' || ($s['secret'] ?? false) === true),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name(),
            'version' => (string) ($this->data['version'] ?? '1.0'),
            'category' => (string) ($this->data['category'] ?? 'other'),
            'description' => (string) ($this->data['description'] ?? ''),
            'author' => $this->data['author'] ?? null,
            'data_notice' => $this->data['data_notice'] ?? null,
            'unofficial' => ($this->data['unofficial'] ?? false) === true,
            'service' => [
                'name' => $this->service()['name'] ?? $this->name(),
                'home' => $this->service()['home'] ?? null,
                'docs' => $this->service()['docs'] ?? null,
                'auth' => $this->auth()['type'] ?? 'none',
                'hosts' => $this->hosts(),
                'auth_note' => $this->service()['auth_note'] ?? null,
                'endpoints' => $this->endpoints(),
            ],
            'settings' => $this->settings(),
            'actions' => $this->actions(),
            'widgets' => $this->widgets(),
            'events' => $this->events(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(string $key): array
    {
        $value = $this->data[$key] ?? [];

        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private function validate(): void
    {
        $fail = fn (string $msg) => throw new InvalidArgumentException("plugins/{$this->directory}/plugin.json: {$msg}");

        $id = $this->data['id'] ?? null;
        if (! is_string($id) || preg_match('/^[a-z][a-z0-9_-]{1,40}$/', $id) !== 1) {
            $fail('«id» fehlt oder ist ungültig (Kleinbuchstaben, Ziffern, - und _).');
        }
        if (($this->data['api'] ?? null) !== self::API_VERSION) {
            $fail('«api» muss '.self::API_VERSION.' sein.');
        }
        if (isset($this->data['unofficial']) && ! is_bool($this->data['unofficial'])) {
            $fail('«unofficial» muss true oder false sein.');
        }
        if (! in_array($this->auth()['type'] ?? 'none', self::AUTH_TYPES, true)) {
            $fail('Unbekannter Anmeldetyp in service.auth.type.');
        }

        $keys = [];
        foreach ($this->settings() as $setting) {
            if (! is_string($setting['key'] ?? null) || ! in_array($setting['type'] ?? '', self::SETTING_TYPES, true)) {
                $fail('Einstellung ohne gültigen «key» oder «type».');
            }
            $keys[] = $setting['key'];
        }
        if (count($keys) !== count(array_unique($keys))) {
            $fail('Einstellungs-Schlüssel doppelt.');
        }

        foreach ($this->actions() as $action) {
            if (! is_string($action['id'] ?? null) || ! in_array($action['kind'] ?? 'read', self::ACTION_KINDS, true)) {
                $fail('Aktion ohne gültige «id» oder «kind».');
            }
        }

        foreach ($this->hosts() as $key => $url) {
            if (preg_match('/^[a-z][a-z0-9_-]{0,30}$/', (string) $key) !== 1 || ! str_starts_with($url, 'https://')) {
                $fail('service.hosts: Schlüssel klein geschrieben, Adressen nur https://.');
            }
        }

        $endpointIds = [];
        foreach ($this->endpoints() as $endpoint) {
            if (! is_string($endpoint['id'] ?? null) || ! is_string($endpoint['path'] ?? null)) {
                $fail('Endpunkt ohne «id» oder «path».');
            }
            if (! in_array(strtoupper((string) ($endpoint['method'] ?? 'GET')), self::METHODS, true)) {
                $fail("Endpunkt «{$endpoint['id']}»: unbekannte Methode.");
            }
            if (! in_array($endpoint['status'] ?? 'full', self::ENDPOINT_STATUS, true)) {
                $fail("Endpunkt «{$endpoint['id']}»: status muss full, partial oder planned sein.");
            }
            if (isset($endpoint['effect']) && ! in_array($endpoint['effect'], ['read', 'write'], true)) {
                $fail("Endpunkt «{$endpoint['id']}»: effect muss read oder write sein.");
            }
            if (isset($endpoint['host']) && $endpoint['host'] !== self::DEFAULT_HOST && ! array_key_exists((string) $endpoint['host'], $this->hosts())) {
                $fail("Endpunkt «{$endpoint['id']}»: Adresse «{$endpoint['host']}» fehlt unter service.hosts.");
            }
            if (isset($endpointIds[$endpoint['id']])) {
                $fail("Endpunkt-ID «{$endpoint['id']}» doppelt.");
            }
            $endpointIds[$endpoint['id']] = true;
        }

        foreach ($this->events() as $event) {
            if (! in_array($event, self::EVENTS, true)) {
                $fail("Unbekanntes Ereignis «{$event}».");
            }
        }
    }
}
