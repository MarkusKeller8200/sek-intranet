<?php

namespace Modules\Zeiterfassung;

use App\Data\Store\JsonStore;
use InvalidArgumentException;

/**
 * Buchungsarten der Zeiterfassung (#99): Arbeit (z. B. Arbeitszeit, Homeoffice) und Abwesenheiten (Ferien, Krankheit,
 * Unfall …). Pflegbar unter Zeiterfassung → Buchungsarten (Recht visum-zeiterfassung), Vorgabe wie in ClickTime.
 *
 * Unabhängig von ClickTime: die Zuordnung zu ClickTime steht in einer Mapping-Tabelle im Plugin ClickTime (#100).
 * Einträge ohne Buchungsart (vor #99) erhalten sie aus Art und Kategorie (von()), es wird nichts umgeschrieben.
 */
final class Buchungsarten
{
    public const COLLECTION = 'zeit_buchungsarten';

    public const ARTEN = ['arbeit' => 'Arbeit', 'abwesenheit' => 'Abwesenheit'];

    /** @var array<string, array{name: string, art: string}> */
    public const VORGABE = [
        'arbeitszeit' => ['name' => 'Arbeitszeit', 'art' => 'arbeit'],
        'homeoffice' => ['name' => 'Homeoffice', 'art' => 'arbeit'],
        'ferien' => ['name' => 'Ferien', 'art' => 'abwesenheit'],
        'krankheit' => ['name' => 'Krankheit', 'art' => 'abwesenheit'],
        'unfall' => ['name' => 'Unfall', 'art' => 'abwesenheit'],
        'kompensation' => ['name' => 'Kompensation', 'art' => 'abwesenheit'],
        'weiterbildung' => ['name' => 'Weiterbildung', 'art' => 'abwesenheit'],
        'militaer' => ['name' => 'Militär/Zivilschutz', 'art' => 'abwesenheit'],
        'bezahlt' => ['name' => 'Bezahlte Absenz', 'art' => 'abwesenheit'],
        'unbezahlt' => ['name' => 'Unbezahlte Absenz', 'art' => 'abwesenheit'],
    ];

    /** Bisherige Abwesenheits-Kategorie → Buchungsart (Einträge vor #99). */
    private const AUS_KATEGORIE = [
        'ferien' => 'ferien', 'krank' => 'krankheit', 'weiterbildung_abw' => 'weiterbildung', 'kompensation' => 'kompensation',
        'militaer' => 'militaer', 'andere_abw' => 'bezahlt',
    ];

    /** Buchungsart (Abwesenheit) → Kategorie für Summen und Export (bleiben wie bisher). */
    private const ZU_KATEGORIE = [
        'ferien' => 'ferien', 'krankheit' => 'krank', 'unfall' => 'krank', 'weiterbildung' => 'weiterbildung_abw',
        'kompensation' => 'kompensation', 'militaer' => 'militaer',
    ];

    public function __construct(private readonly JsonStore $store) {}

    /**
     * Gespeicherte Liste, sonst die Vorgabe. Reihenfolge wie gespeichert.
     *
     * @return array<string, array{name: string, art: string, aktiv: bool}>
     */
    public function liste(bool $nurAktive = false): array
    {
        $records = $this->store->all(self::COLLECTION);
        if ($records === []) {
            $liste = array_map(fn (array $b): array => $b + ['aktiv' => true], self::VORGABE);
        } else {
            usort($records, fn (array $a, array $b): int => (int) ($a['reihenfolge'] ?? 0) <=> (int) ($b['reihenfolge'] ?? 0));
            $liste = [];
            foreach ($records as $r) {
                $liste[(string) $r['id']] = [
                    'name' => (string) ($r['name'] ?? $r['id']),
                    'art' => ($r['art'] ?? '') === 'abwesenheit' ? 'abwesenheit' : 'arbeit',
                    'aktiv' => ($r['aktiv'] ?? true) !== false,
                ];
            }
        }

        return $nurAktive ? array_filter($liste, fn (array $b): bool => $b['aktiv']) : $liste;
    }

    /**
     * Liste ersetzen (Schlüssel bleiben; neue Schlüssel aus dem Namen). Mindestens eine aktive Arbeit-Buchungsart.
     *
     * @param  list<array{key?: string|null, name: string, art: string, aktiv?: bool}>  $zeilen
     */
    public function speichern(array $zeilen): void
    {
        $neu = [];
        foreach (array_values($zeilen) as $i => $z) {
            $name = trim((string) $z['name']);
            if ($name === '') {
                continue;
            }
            $key = trim((string) ($z['key'] ?? '')) ?: self::schluessel($name);
            if (isset($neu[$key])) {
                throw new InvalidArgumentException("Buchungsart «{$name}» ist doppelt.");
            }
            $neu[$key] = ['id' => $key, 'name' => mb_substr($name, 0, 60), 'art' => ($z['art'] ?? '') === 'abwesenheit' ? 'abwesenheit' : 'arbeit',
                'aktiv' => ($z['aktiv'] ?? true) !== false, 'reihenfolge' => $i];
        }
        if (array_filter($neu, fn (array $b): bool => $b['art'] === 'arbeit' && $b['aktiv']) === []) {
            throw new InvalidArgumentException('Mindestens eine aktive Buchungsart der Art «Arbeit» ist nötig.');
        }
        foreach ($this->store->all(self::COLLECTION) as $alt) {
            if (! isset($neu[(string) $alt['id']])) {
                // nie löschen, sonst verlieren Einträge ihre Bezeichnung: als inaktiv behalten
                $neu[(string) $alt['id']] = ['id' => (string) $alt['id'], 'name' => (string) ($alt['name'] ?? $alt['id']), 'art' => (string) ($alt['art'] ?? 'arbeit'), 'aktiv' => false, 'reihenfolge' => 999];
            }
        }
        foreach ($neu as $key => $b) {
            if ($this->store->find(self::COLLECTION, $key) !== null) {
                $this->store->update(self::COLLECTION, $key, $b);
            } else {
                $this->store->insert(self::COLLECTION, $b);
            }
        }
    }

    /**
     * Buchungsart eines Eintrags; ältere Einträge aus Art und Kategorie.
     *
     * @param  array<string, mixed>  $eintrag
     */
    public static function von(array $eintrag): string
    {
        $b = trim((string) ($eintrag['buchungsart'] ?? ''));
        if ($b !== '') {
            return $b;
        }
        if (($eintrag['art'] ?? 'arbeit') !== 'abwesenheit') {
            return 'arbeitszeit';
        }

        return self::AUS_KATEGORIE[(string) ($eintrag['kategorie'] ?? '')] ?? 'bezahlt';
    }

    /** Kategorie, unter der eine Abwesenheit in Summen und Export erscheint. */
    public static function kategorieFuer(string $buchungsart): string
    {
        return self::ZU_KATEGORIE[$buchungsart] ?? 'andere_abw';
    }

    public static function schluessel(string $name): string
    {
        $s = strtolower(strtr($name, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'é' => 'e', 'è' => 'e', 'à' => 'a']));
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '_', $s), '_');

        return $s !== '' ? mb_substr($s, 0, 40) : 'art';
    }
}
