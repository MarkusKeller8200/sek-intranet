<?php

namespace App\Plugins\Support;

use App\Data\Contracts\UserRepository;
use App\Support\AuditLog;
use App\Support\PersonFields;
use App\Support\PersonLinks;

/**
 * Vergleicht Personen aus einem Fremdsystem mit den Intranet-Personen (Schlüssel: E-Mail)
 * und übernimmt ausgewählte Änderungen. Gemeinsam genutzt von Entra, Inventory360, IDA.
 *
 * Regeln: legt an und ändert, löscht nie. Rolle und Schulhaus bestehender Personen
 * bleiben, wenn das Fremdsystem keine liefert. Personen, die nur im Intranet
 * existieren, erscheinen als «fehlt dort» (nur Information).
 */
final class PeopleImport
{
    /** Felder, die übernommen werden dürfen. */
    public const FIELDS = [
        'name', 'title', 'first_name', 'last_name', 'short', 'email', 'function', 'department', 'escola_roles',
        'phone', 'phone2', 'mobile', 'office_street', 'office_zip', 'office_city',
        'private_email', 'private_phone', 'private_mobile', 'private_street', 'private_zip', 'private_city',
        'emergency_name', 'emergency_phone', 'entry_date', 'exit_date', 'username', 'escola_id', 'external_id',
        'school', 'active',
    ];

    /**
     * @param  string|null  $system  Schlüssel in config/intranet.php → sync_systems; mit «_external_id»
     *                               je Datensatz wird zuerst über die Datenherkunft gesucht (keine Duplikate)
     */
    public function __construct(
        private readonly UserRepository $users,
        private readonly string $source = 'Import',
        private readonly ?string $system = null,
    ) {}

    private function links(): PersonLinks
    {
        return app(PersonLinks::class);
    }

    /**
     * Bestehende Person: zuerst über die ID im Quellsystem, sonst über die E-Mail.
     *
     * @return array<string, mixed>|null
     */
    private function existing(string $email, ?string $externalId): ?array
    {
        if ($this->system !== null && $externalId !== null) {
            $personId = $this->links()->findPerson($this->system, $externalId);
            $person = $personId !== null ? $this->users->find($personId) : null;
            if ($person !== null) {
                return $person;
            }
        }

        return $this->users->findByEmail($email);
    }

    /**
     * @param  list<array<string, mixed>>  $external  je Person mindestens «email» und «name»
     * @return list<array<string, mixed>> Vorschauzeilen mit _key, _change, Feldern und «changes»
     */
    public function plan(array $external, string $source): array
    {
        $rows = [];
        $seen = [];

        foreach ($external as $person) {
            $email = mb_strtolower(trim((string) ($person['email'] ?? '')));
            if ($email === '' || isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            $externalId = is_scalar($person['_external_id'] ?? null) && trim((string) $person['_external_id']) !== '' ? trim((string) $person['_external_id']) : null;
            $existing = $this->existing($email, $externalId);
            $incoming = $this->normalize(PersonFields::withNames($person)) + ['email' => $email];
            $link = ['_external_id' => $externalId, '_person_id' => $existing['id'] ?? null];

            if ($existing === null) {
                $rows[] = $this->row($email, 'neu', $incoming, [], $source) + $link;

                continue;
            }

            $changes = [];
            foreach ($incoming as $field => $value) {
                if ($value === null || ($existing[$field] ?? null) === $value) {
                    continue;
                }
                $changes[$field] = ['old' => $existing[$field] ?? null, 'new' => $value];
            }

            $rows[] = $this->row($email, $changes === [] ? 'gleich' : 'ändern', $incoming + $existing, $changes, $source) + $link;
        }

        $matched = array_flip(array_filter(array_map(fn (array $r): string => (string) ($r['_person_id'] ?? ''), $rows)));
        foreach ($this->users->all() as $user) {
            $email = mb_strtolower((string) ($user['email'] ?? ''));
            if ($email !== '' && ! isset($seen[$email]) && ! isset($matched[(string) $user['id']])) {
                $rows[] = $this->row($email, 'fehlt dort', $user, [], $source) + ['_selectable' => false];
            }
        }

        usort($rows, fn (array $a, array $b): int => [self::order($a['_change']), $a['name']] <=> [self::order($b['_change']), $b['name']]);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows  Ergebnis von plan()
     * @param  list<string>  $selected  ausgewählte _key
     * @return array{created: int, updated: int}
     */
    public function apply(array $rows, array $selected): array
    {
        $created = 0;
        $updated = 0;

        foreach ($rows as $row) {
            // Unveränderte Personen: nur Datenherkunft festhalten (ID und Zeitpunkt)
            if ($row['_change'] === 'gleich' && is_string($row['_person_id'] ?? null)) {
                $this->remember($row['_person_id'], $row['_external_id'] ?? null);
            }
            if (! in_array($row['_key'], $selected, true)) {
                continue;
            }

            if ($row['_change'] === 'neu') {
                $record = ['role' => 'mitarbeitende', 'school' => 'gesamt', 'active' => true];
                foreach ((array) ($row['_record'] ?? []) as $field => $value) {
                    if ($value !== null && in_array($field, self::FIELDS, true)) {
                        $record[$field] = $value;
                    }
                }
                $person = $this->users->create(PersonFields::withNames($record));
                app(AuditLog::class)->record('users', null, $person, $this->source, auth()->user());
                $this->remember((string) $person['id'], $row['_external_id'] ?? null);
                $created++;
            } elseif ($row['_change'] === 'ändern') {
                $existing = is_string($row['_person_id'] ?? null) ? $this->users->find($row['_person_id']) : $this->users->findByEmail((string) $row['email']);
                if ($existing !== null) {
                    $after = $this->users->update((string) $existing['id'], array_map(fn (array $c): mixed => $c['new'], $row['changes']));
                    app(AuditLog::class)->record('users', $existing, $after, $this->source, auth()->user());
                    $this->remember((string) $existing['id'], $row['_external_id'] ?? null);
                    $updated++;
                }
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function remember(string $personId, mixed $externalId): void
    {
        if ($this->system !== null) {
            $this->links()->recordSync($personId, $this->system, 'source', is_scalar($externalId) ? (string) $externalId : null);
        }
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function columns(): array
    {
        return [
            ['key' => '_change', 'label' => 'Änderung'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'E-Mail'],
            ['key' => 'function', 'label' => 'Funktion'],
            ['key' => 'summary', 'label' => 'Details'],
        ];
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    private function normalize(array $person): array
    {
        $out = [];
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $person)) {
                continue;
            }
            $value = $person[$field];
            $out[$field] = match ($field) {
                'active' => $value === null ? null : (bool) $value,
                'school' => is_string($value) && array_key_exists($value, (array) config('intranet.schools')) ? $value : null,
                'entry_date', 'exit_date' => self::date($value),
                'email', 'private_email' => is_string($value) && trim($value) !== '' ? mb_strtolower(trim($value)) : null,
                default => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null,
            };
        }

        return $out;
    }

    /** Datum als JJJJ-MM-TT; akzeptiert auch TT.MM.JJJJ (Escola). */
    private static function date(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     * @return array<string, mixed>
     */
    private function row(string $email, string $change, array $data, array $changes, string $source): array
    {
        $summary = match ($change) {
            'neu' => "wird angelegt (Rolle Mitarbeitende, Quelle {$source})",
            'ändern' => implode(', ', array_map(
                fn (string $f, array $c): string => $f.': '.self::show($c['old']).' → '.self::show($c['new']),
                array_keys($changes),
                $changes,
            )),
            'gleich' => 'keine Änderung',
            default => "nur im Intranet, nicht in {$source} (wird nicht gelöscht)",
        };

        return [
            '_key' => $email,
            '_change' => $change,
            '_selectable' => in_array($change, ['neu', 'ändern'], true),
            'name' => (string) ($data['name'] ?? $email),
            'email' => $email,
            'function' => $data['function'] ?? null,
            'phone' => $data['phone'] ?? null,
            'school' => $data['school'] ?? null,
            'active' => $data['active'] ?? true,
            'changes' => $changes,
            'summary' => $summary,
            // vollständiger Datensatz für «neu» (nicht als Spalte angezeigt)
            '_record' => array_intersect_key($data, array_flip(self::FIELDS)),
        ];
    }

    private static function show(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '–',
            $value === true => 'ja',
            $value === false => 'nein',
            default => is_scalar($value) ? (string) $value : '…',
        };
    }

    private static function order(string $change): int
    {
        return ['neu' => 0, 'ändern' => 1, 'gleich' => 2][$change] ?? 3;
    }
}
