<?php

namespace App\Support;

use App\Data\Contracts\UserRepository;
use App\Data\Store\JsonStore;
use Illuminate\Support\Carbon;

/**
 * Datenherkunft und Synchronisation einer Person (Sammlung person_links).
 *
 * - Quelle (source): aus welchem System die Person stammt, mit dessen ID/PK. Importe suchen
 *   zuerst über (System, ID) und erst dann über die E-Mail – so entstehen keine Duplikate,
 *   auch wenn sich die E-Mail ändert.
 * - Ziel (target): in welche Systeme die Person exportiert wird, mit deren ID/PK und dem
 *   letzten Abgleich. Wurde die Person danach geändert, ist ein Abgleich fällig.
 *
 * Je Person, System und Richtung höchstens ein Eintrag; eine ID gehört je System und Richtung
 * zu höchstens einer Person.
 *
 * @phpstan-type Link array{id: string, person_id: string, direction: string, system: string, external_id: string|null, id_label: string|null, last_sync_at: string|null, note: string|null, created_at: string, updated_at: string}
 */
final class PersonLinks
{
    public const COLLECTION = 'person_links';

    public const DIRECTIONS = ['source' => 'Quelle', 'target' => 'Ziel'];

    public function __construct(private readonly JsonStore $store, private readonly UserRepository $users) {}

    /**
     * @return array<string, string> Systemschlüssel → Bezeichnung
     */
    public static function systems(): array
    {
        /** @var array<string, string> $systems */
        $systems = (array) config('intranet.sync_systems', []);

        return $systems;
    }

    /**
     * Adresse, um direkt ins fremde System zu springen (#149, config intranet.record_links), z. B. ins CMI-Dossier.
     * Null ohne Vorlage, bei ungültiger ID oder wenn die Adresse nicht https ist.
     */
    public static function recordUrl(string $system, string $externalId, string $personType): ?string
    {
        $config = config("intranet.record_links.{$system}");
        if (! is_array($config) || ! is_string($config['url'] ?? null)) {
            return null;
        }
        $id = trim($externalId);
        $pattern = is_string($config['id_pattern'] ?? null) ? $config['id_pattern'] : '/^[A-Za-z0-9-]{1,64}$/';
        if ($id === '' || preg_match($pattern, $id) !== 1) {
            return null;
        }
        $areas = is_array($config['bereiche'] ?? null) ? $config['bereiche'] : [];
        $area = (string) ($areas[$personType] ?? $areas['*'] ?? '');
        if (str_contains($config['url'], '{bereich}') && preg_match('/^[A-Za-z0-9-]{1,64}$/', $area) !== 1) {
            return null;
        }
        $url = strtr($config['url'], ['{bereich}' => $area, '{id}' => rawurlencode($id)]);

        return str_starts_with($url, 'https://') ? $url : null;
    }

    public static function recordLabel(string $system): ?string
    {
        $label = config("intranet.record_links.{$system}.label");

        return is_string($label) ? $label : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->store->all(self::COLLECTION);
    }

    /**
     * Einträge einer Person mit Status (für die Detailansicht).
     *
     * @param  array<string, mixed>  $person
     * @return list<array<string, mixed>>
     */
    public function forPerson(string $personId, array $person): array
    {
        $links = array_values(array_filter($this->all(), fn (array $l): bool => ($l['person_id'] ?? null) === $personId));
        usort($links, fn (array $a, array $b): int => [($a['direction'] ?? '') === 'source' ? 0 : 1, $a['system'] ?? ''] <=> [($b['direction'] ?? '') === 'source' ? 0 : 1, $b['system'] ?? '']);

        return array_map(fn (array $l): array => $l + ['status' => self::status($l, $person)]
            + ['open_url' => self::recordUrl((string) ($l['system'] ?? ''), (string) ($l['external_id'] ?? ''), (string) ($person['person_type'] ?? ''))]
            + ['open_label' => self::recordLabel((string) ($l['system'] ?? ''))], $links);
    }

    /**
     * Status eines Eintrags: «pending» = seit dem letzten Abgleich geändert (Ziel) bzw. nie abgeglichen.
     *
     * @param  array<string, mixed>  $link
     * @param  array<string, mixed>  $person
     */
    public static function status(array $link, array $person): string
    {
        $synced = $link['last_sync_at'] ?? null;
        if (($link['direction'] ?? null) === 'source') {
            return is_string($synced) ? 'imported' : 'unknown';
        }
        if (! is_string($synced)) {
            return 'pending';
        }
        $updated = $person['updated_at'] ?? null;

        return is_string($updated) && Carbon::parse($updated)->gt(Carbon::parse($synced)) ? 'pending' : 'current';
    }

    /**
     * Anzahl fälliger Abgleiche je Person (Spalte in der Tabelle).
     *
     * @return array<string, int>
     */
    public function pendingCounts(): array
    {
        $people = [];
        foreach ($this->users->all() as $person) {
            $people[(string) $person['id']] = $person;
        }
        $counts = [];
        foreach ($this->all() as $link) {
            $person = $people[(string) ($link['person_id'] ?? '')] ?? null;
            if ($person !== null && self::status($link, $person) === 'pending') {
                $id = (string) $link['person_id'];
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** Person zu einer ID/PK eines Systems (Duplikate beim Import vermeiden). */
    public function findPerson(string $system, string $externalId, string $direction = 'source'): ?string
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return null;
        }
        foreach ($this->all() as $link) {
            if (($link['system'] ?? null) === $system && ($link['direction'] ?? null) === $direction && (string) ($link['external_id'] ?? '') === $externalId) {
                return (string) $link['person_id'];
            }
        }

        return null;
    }

    /**
     * Eintrag anlegen oder ändern. Prüft Eindeutigkeit.
     *
     * @param  array<string, mixed>  $data  person_id, direction, system, external_id, id_label, last_sync_at, note
     * @return array{link: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function save(array $data, ?string $id = null): array
    {
        $rawSync = $data['last_sync_at'] ?? null;
        $clean = [
            'person_id' => (string) ($data['person_id'] ?? ''),
            'direction' => (string) ($data['direction'] ?? ''),
            'system' => (string) ($data['system'] ?? ''),
            'external_id' => self::nullable($data['external_id'] ?? null),
            'id_label' => self::nullable($data['id_label'] ?? null),
            'last_sync_at' => self::dateTime($rawSync),
            'note' => self::nullable($data['note'] ?? null),
        ];

        $errors = [];
        if ($this->users->find($clean['person_id']) === null) {
            $errors['person_id'] = 'Person nicht gefunden.';
        }
        if (! isset(self::DIRECTIONS[$clean['direction']])) {
            $errors['direction'] = 'Richtung ist Quelle oder Ziel.';
        }
        if (! isset(self::systems()[$clean['system']])) {
            $errors['system'] = 'Unbekanntes System.';
        }
        if ($clean['direction'] === 'source' && $clean['external_id'] === null) {
            $errors['external_id'] = 'Für die Quelle ist die ID/PK nötig (sonst entstehen beim Import Duplikate).';
        }
        if (is_string($rawSync) && trim($rawSync) !== '' && $clean['last_sync_at'] === null) {
            $errors['last_sync_at'] = 'Ungültiges Datum.';
        }

        foreach ($this->all() as $other) {
            if (($other['id'] ?? null) === $id || ($other['system'] ?? null) !== $clean['system'] || ($other['direction'] ?? null) !== $clean['direction']) {
                continue;
            }
            if (($other['person_id'] ?? null) === $clean['person_id']) {
                $errors['system'] = 'Für dieses System und diese Richtung gibt es bereits einen Eintrag.';
            } elseif ($clean['external_id'] !== null && (string) ($other['external_id'] ?? '') === $clean['external_id']) {
                $errors['external_id'] = 'Diese ID ist in diesem System bereits einer anderen Person zugeordnet.';
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors];
        }

        $link = $id === null
            ? $this->store->insert(self::COLLECTION, $clean)
            : $this->store->update(self::COLLECTION, $id, $clean);

        return $link === null ? ['errors' => ['id' => 'Eintrag nicht gefunden.']] : ['link' => $link];
    }

    /**
     * Abgleich festhalten (Import oder Export): legt den Eintrag an oder setzt ID und Zeitpunkt.
     */
    public function recordSync(string $personId, string $system, string $direction, ?string $externalId, ?string $at = null): void
    {
        $at ??= now()->toAtomString();
        foreach ($this->all() as $link) {
            if (($link['person_id'] ?? null) === $personId && ($link['system'] ?? null) === $system && ($link['direction'] ?? null) === $direction) {
                $this->store->update(self::COLLECTION, (string) $link['id'], array_filter([
                    'external_id' => self::nullable($externalId) ?? ($link['external_id'] ?? null),
                    'last_sync_at' => $at,
                ], fn (mixed $v): bool => $v !== null));

                return;
            }
        }
        $this->save(['person_id' => $personId, 'system' => $system, 'direction' => $direction, 'external_id' => $externalId, 'last_sync_at' => $at]);
    }

    public function delete(string $id): bool
    {
        return $this->store->delete(self::COLLECTION, $id);
    }

    /** Beim Löschen einer Person ihre Einträge mitlöschen. */
    public function deleteForPerson(string $personId): void
    {
        foreach ($this->all() as $link) {
            if (($link['person_id'] ?? null) === $personId) {
                $this->store->delete(self::COLLECTION, (string) $link['id']);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->store->find(self::COLLECTION, $id);
    }

    private static function nullable(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    private static function dateTime(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->toAtomString();
        } catch (\Throwable) {
            return null;
        }
    }
}
