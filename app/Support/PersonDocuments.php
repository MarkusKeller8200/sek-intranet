<?php

namespace App\Support;

use App\Auth\IntranetUser;
use Throwable;

/**
 * Kern-Schnittstelle «Dokumente bei der Person» (#208): Module melden Dokumente einer Person (z. B. unterschriebene
 * Monatsrapporte der Zeiterfassung). Die Dateien bleiben im Speicher des Moduls; die Personenseite zeigt nur Titel,
 * Datum und den Link des Moduls. Jedes Modul entscheidet selbst, wer die Dokumente sehen darf.
 */
final class PersonDocuments
{
    /** @var array<string, callable(string, IntranetUser|null): list<array{title: string, date?: string|null, url: string, kind?: string|null}>> */
    private static array $providers = [];

    /**
     * Modul meldet sich an (im ServiceProvider des Moduls, boot()).
     *
     * @param  callable(string, IntranetUser|null): list<array{title: string, date?: string|null, url: string, kind?: string|null}>  $provider
     */
    public static function register(string $module, callable $provider): void
    {
        self::$providers[$module] = $provider;
    }

    /** Nur für Tests. */
    public static function forget(): void
    {
        self::$providers = [];
    }

    /**
     * Dokumente einer Person, die die angemeldete Person sehen darf; neueste zuerst. Fehler eines Moduls blenden nur
     * dessen Dokumente aus.
     *
     * @return list<array{module: string, title: string, date: string|null, url: string, kind: string|null}>
     */
    public function forPerson(string $personId, ?IntranetUser $viewer): array
    {
        $result = [];
        foreach (self::$providers as $module => $provider) {
            try {
                foreach ($provider($personId, $viewer) as $doc) {
                    $url = (string) $doc['url'];
                    if (! str_starts_with($url, '/')) {
                        continue; // nur Links innerhalb des Intranets
                    }
                    $result[] = ['module' => $module, 'title' => (string) $doc['title'], 'date' => isset($doc['date']) ? (string) $doc['date'] : null,
                        'url' => $url, 'kind' => isset($doc['kind']) ? (string) $doc['kind'] : null];
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        usort($result, fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return $result;
    }
}
