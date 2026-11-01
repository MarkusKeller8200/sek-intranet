<?php

namespace App\Support;

/**
 * Version des Intranets: Nummer aus der Datei VERSION, Build (Commit) und Zeitpunkt aus dem
 * Deployment (INTRANET_BUILD, INTRANET_BUILD_DATE), Änderungen aus CHANGELOG.md.
 *
 * Developer Release (wie TagStudio): Ausgerollter Stand mit Commits nach dem Tag v<VERSION>, also weder Beta noch
 * Release. Das Deployment übergibt dazu `git describe --tags --long` (INTRANET_BUILD_DESCRIBE).
 */
final class AppVersion
{
    /**
     * @return array{version: string, build: string|null, build_date: string|null, prototype: bool, dev: array{commits: int, base: string, planned: string}|null}
     */
    public static function info(): array
    {
        $version = (string) config('intranet.version', '0.0.0');

        return [
            'version' => $version,
            'build' => config('intranet.build') !== null ? (string) config('intranet.build') : null,
            'build_date' => config('intranet.build_date') !== null ? (string) config('intranet.build_date') : null,
            'prototype' => str_starts_with($version, '0.'),
            'dev' => self::developerRelease($version, config('intranet.build_describe') !== null ? (string) config('intranet.build_describe') : null),
        ];
    }

    /**
     * Aus `git describe --tags --long --match 'v[0-9]*'` (z. B. v0.31.0-5-gabc1234): Commits nach dem Tag
     * und die geplante nächste Version. Null = genau ein Release oder unbekannt.
     *
     * @return array{commits: int, base: string, planned: string}|null
     */
    public static function developerRelease(string $version, ?string $describe): ?array
    {
        if ($describe === null || preg_match('/^v(.+)-(\d+)-g[0-9a-f]+$/', trim($describe), $m) !== 1) {
            return null;
        }
        $commits = (int) $m[2];
        if ($commits === 0 && $m[1] === $version) {
            return null;
        }
        $changelog = (string) @file_get_contents(base_path('CHANGELOG.md'));

        return ['commits' => $commits, 'base' => $m[1], 'planned' => Release::planned($version, $changelog)];
    }

    /**
     * CHANGELOG.md als Liste von Versionen mit Abschnitten und Einträgen.
     *
     * @return list<array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}>
     */
    public static function changelog(): array
    {
        $path = base_path('CHANGELOG.md');
        if (! is_file($path)) {
            return [];
        }

        $releases = [];
        $current = null;
        $item = null;

        foreach ([...(preg_split('/\R/', (string) file_get_contents($path)) ?: []), ''] as $line) {
            // Fortsetzungszeile eines Eintrags (eingerückt)
            if ($item !== null && preg_match('/^\s{2,}(\S.*)$/u', $line, $m) === 1) {
                $item .= ' '.$m[1];

                continue;
            }
            // Sonst ist der laufende Eintrag fertig
            if ($item !== null && $current !== null) {
                $current = self::addItem($current, $item);
            }
            $item = null;

            if (preg_match('/^## \[([^\]]+)\](?:\s+[–-]\s+(\S+))?/u', $line, $m) === 1) {
                if ($current !== null) {
                    $releases[] = $current;
                }
                $current = ['version' => $m[1], 'date' => $m[2] ?? null, 'sections' => []];
            } elseif ($current !== null && preg_match('/^### (.+)$/u', $line, $m) === 1) {
                $current['sections'][] = ['title' => trim($m[1]), 'items' => []];
            } elseif ($current !== null && preg_match('/^- (.+)$/u', $line, $m) === 1) {
                $item = $m[1];
            }
        }
        if ($current !== null) {
            $releases[] = $current;
        }

        // «Unveröffentlicht» nur zeigen, wenn es Einträge hat
        return array_values(array_filter($releases, fn (array $r): bool => $r['version'] !== 'Unveröffentlicht' || $r['sections'] !== []));
    }

    /**
     * @param  array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}  $release
     * @return array{version: string, date: string|null, sections: list<array{title: string, items: list<string>}>}
     */
    private static function addItem(array $release, string $item): array
    {
        if ($release['sections'] === []) {
            $release['sections'][] = ['title' => 'Änderungen', 'items' => []];
        }
        $last = count($release['sections']) - 1;
        $release['sections'][$last]['items'][] = trim($item);

        return $release;
    }
}
