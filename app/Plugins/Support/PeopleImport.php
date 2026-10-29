<?php

namespace App\Plugins\Support;

use App\Data\Contracts\UserRepository;

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
    public const FIELDS = ['name', 'email', 'function', 'phone', 'school', 'active'];

    public function __construct(private readonly UserRepository $users) {}

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

            $existing = $this->users->findByEmail($email);
            $incoming = $this->normalize($person) + ['email' => $email];

            if ($existing === null) {
                $rows[] = $this->row($email, 'neu', $incoming, [], $source);

                continue;
            }

            $changes = [];
            foreach ($incoming as $field => $value) {
                if ($value === null || ($existing[$field] ?? null) === $value) {
                    continue;
                }
                $changes[$field] = ['old' => $existing[$field] ?? null, 'new' => $value];
            }

            $rows[] = $this->row($email, $changes === [] ? 'gleich' : 'ändern', $incoming + $existing, $changes, $source);
        }

        foreach ($this->users->all() as $user) {
            $email = mb_strtolower((string) ($user['email'] ?? ''));
            if ($email !== '' && ! isset($seen[$email])) {
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
            if (! in_array($row['_key'], $selected, true)) {
                continue;
            }

            if ($row['_change'] === 'neu') {
                $this->users->create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'role' => 'mitarbeitende',
                    'function' => $row['function'] ?? null,
                    'school' => $row['school'] ?? 'gesamt',
                    'phone' => $row['phone'] ?? null,
                    'active' => $row['active'] ?? true,
                ]);
                $created++;
            } elseif ($row['_change'] === 'ändern') {
                $existing = $this->users->findByEmail((string) $row['email']);
                if ($existing !== null) {
                    $this->users->update((string) $existing['id'], array_map(fn (array $c): mixed => $c['new'], $row['changes']));
                    $updated++;
                }
            }
        }

        return ['created' => $created, 'updated' => $updated];
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
                default => is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null,
            };
        }

        return $out;
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
