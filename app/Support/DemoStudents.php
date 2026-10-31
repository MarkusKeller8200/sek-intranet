<?php

namespace App\Support;

use App\Data\Store\JsonStore;

/**
 * Erfundene Schüler:innen und Eltern für den Prototyp (nie echte Daten – ADR-005).
 * Werden angelegt, wenn die Demo-Daten aktiv sind (Demo-Administration vorhanden) und noch
 * keine Schüler:innen erfasst sind.
 */
final class DemoStudents
{
    public const DOMAIN = 'edu.sekbuelach.example';

    private const MARKER = 'andrea.muster@sekbuelach.example';

    /**
     * @return list<array<string, mixed>>
     */
    public static function records(): array
    {
        $pupils = [
            ['Aebi', 'Lina', 'M_A1a', 'mettmenriet', '200101'],
            ['Brunner', 'Noah', 'M_A1a', 'mettmenriet', '200102'],
            ['Caflisch', 'Mila', 'M_A1a', 'mettmenriet', '200103'],
            ['Dosch', 'Elias', 'M_B1b', 'mettmenriet', '200104'],
            ['Egli', 'Sofia', 'M_B1b', 'mettmenriet', '200105'],
            ['Frei', 'Levin', 'M_B1b', 'mettmenriet', null],
            ['Gasser', 'Emma', 'H_A1a', 'hinterbirch', '200107'],
            ['Huber', 'Jonas', 'H_A1a', 'hinterbirch', '200108'],
            ['Iseli', 'Ella', 'H_A2a', 'hinterbirch', '190211'],
            ['Jost', 'Luca', 'H_A2a', 'hinterbirch', '190212'],
            ['Kälin', 'Nora', 'M_C3a', 'mettmenriet', '180333'],
            ['Lüthi', 'Tim', 'M_C3a', 'mettmenriet', '180334'],
        ];

        $records = [];
        foreach ($pupils as [$last, $first, $class, $school, $device]) {
            $records[] = [
                'first_name' => $first,
                'last_name' => $last,
                'name' => "{$first} {$last}",
                'email' => self::username($first, $last),
                'person_type' => 'schueler',
                'role' => 'kein-zugang',
                'school' => $school,
                'class_name' => $class,
                'device_inventory' => $device,
                'function' => 'Schüler:in '.$class,
                'active' => true,
            ];
        }
        $records[] = ['first_name' => 'Ruth', 'last_name' => 'Aebi', 'name' => 'Ruth Aebi', 'email' => 'ruth.aebi@eltern.example', 'person_type' => 'eltern', 'role' => 'kein-zugang', 'school' => 'mettmenriet', 'function' => 'Mutter von Lina Aebi (M_A1a)', 'mobile' => '079 000 50 01', 'active' => true];
        $records[] = ['first_name' => 'Marco', 'last_name' => 'Gasser', 'name' => 'Marco Gasser', 'email' => null, 'person_type' => 'eltern', 'role' => 'kein-zugang', 'school' => 'hinterbirch', 'function' => 'Vater von Emma Gasser (H_A1a)', 'mobile' => '079 000 50 02', 'active' => true];

        return $records;
    }

    public static function username(string $first, string $last): string
    {
        $clean = fn (string $s): string => (string) preg_replace('/[^a-z]/', '', strtr(mb_strtolower($s), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e']));

        return $clean($first).'.'.$clean($last).'@'.self::DOMAIN;
    }

    /** Demo-Daten aktiv und noch keine Schüler:innen? Dann anlegen. Liefert die Anzahl. */
    public static function addIfMissing(JsonStore $store): int
    {
        $users = $store->all('users');
        $isDemo = collect($users)->contains(fn (array $u): bool => ($u['email'] ?? null) === self::MARKER);
        $hasStudents = collect($users)->contains(fn (array $u): bool => ($u['person_type'] ?? null) === 'schueler');
        if (! $isDemo || $hasStudents) {
            return 0;
        }
        foreach (self::records() as $record) {
            $store->insert('users', $record);
        }

        return count(self::records());
    }

    /** Beispiel-Einträge Datenherkunft, wenn noch keine vorhanden sind. */
    public static function addDemoLinks(JsonStore $store): int
    {
        if ($store->all(PersonLinks::COLLECTION) !== []) {
            return 0;
        }
        $byEmail = [];
        foreach ($store->all('users') as $u) {
            if (is_string($u['email'] ?? null)) {
                $byEmail[$u['email']] = $u;
            }
        }
        $earlier = now()->subDays(3)->toAtomString();
        $links = [
            ['andrea.muster@sekbuelach.example', 'source', 'escola', 'T-001', 'Escola-ID', $earlier],
            ['andrea.muster@sekbuelach.example', 'target', 'entra', '8f2d5a3e-0000-4000-8000-000000000001', 'objectId', now()->addMinute()->toAtomString()],
            ['daniel.probe@sekbuelach.example', 'source', 'escola', 'T-014', 'Escola-ID', $earlier],
            ['daniel.probe@sekbuelach.example', 'target', 'entra', '8f2d5a3e-0000-4000-8000-000000000014', 'objectId', '2026-01-15T08:00:00+01:00'],
            ['daniel.probe@sekbuelach.example', 'target', 'jamf', null, null, null],
            ['lina.aebi@'.self::DOMAIN, 'source', 'datei', 'S-200101', 'Schüler-Nr.', $earlier],
            ['lina.aebi@'.self::DOMAIN, 'target', 'jamf', '4711', 'Jamf-ID (iPad)', $earlier],
        ];
        $count = 0;
        foreach ($links as [$email, $direction, $system, $externalId, $label, $at]) {
            $person = $byEmail[$email] ?? null;
            if ($person === null) {
                continue;
            }
            $store->insert(PersonLinks::COLLECTION, [
                'person_id' => (string) $person['id'], 'direction' => $direction, 'system' => $system,
                'external_id' => $externalId, 'id_label' => $label, 'last_sync_at' => $at, 'note' => null,
            ]);
            $count++;
        }

        return $count;
    }
}
