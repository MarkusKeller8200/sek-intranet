<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Versionen und Releases (Ablauf aus TagStudio, siehe docs/KONZEPT-VERSIONIERUNG.md).
 *
 * Die Nummer steht nur in VERSION und wird ausschliesslich beim freigegebenen Release erhöht
 * (`php tools/release.php 0.32.0`). Dazwischen sammeln sich Änderungen im CHANGELOG unter
 * «Unveröffentlicht»; der ausgerollte Stand gilt als «Developer Release».
 *
 * Bewusst ohne Laravel-Abhängigkeiten, damit tools/release.php und der Workflow release.yml die
 * Klasse ohne `composer install` laden können.
 */
final class Release
{
    public const SEMVER = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(-[0-9A-Za-z.-]+)?$/';

    public const UNRELEASED = '## [Unveröffentlicht]';

    public function __construct(private readonly string $root) {}

    public function current(): string
    {
        return trim((string) @file_get_contents($this->root.'/VERSION'));
    }

    public static function isValid(string $version): bool
    {
        return preg_match(self::SEMVER, $version) === 1;
    }

    public static function isPrerelease(string $version): bool
    {
        return str_contains($version, '-');
    }

    /**
     * Vergleich nach SemVer: 0.32.0-beta.2 < 0.32.0-beta.10 < 0.32.0-rc.1 < 0.32.0.
     */
    public static function compare(string $a, string $b): int
    {
        return self::key($a) <=> self::key($b);
    }

    /**
     * @return array{int, int, int, int, list<array{int, int, string}>}
     */
    private static function key(string $version): array
    {
        if (preg_match(self::SEMVER, $version, $m) !== 1) {
            throw new InvalidArgumentException("«{$version}» ist keine gültige Version (MAJOR.MINOR.PATCH).");
        }
        $pre = [];
        if (($m[4] ?? '') !== '') {
            foreach (explode('.', ltrim($m[4], '-')) as $part) {
                $pre[] = ctype_digit($part) ? [0, (int) $part, ''] : [1, 0, $part];
            }
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3], $pre === [] ? 1 : 0, $pre];
    }

    /**
     * Nächste geplante Version für die Anzeige «Developer Release»: nur Fehlerbehebungen
     * («Behoben») → Patch, sonst Minor. Eine laufende Vorabversion wird ohne Zusatz fertig.
     */
    public static function planned(string $version, string $changelog): string
    {
        if (preg_match(self::SEMVER, $version, $m) !== 1) {
            return $version;
        }
        if (($m[4] ?? '') !== '') {
            return "{$m[1]}.{$m[2]}.{$m[3]}";
        }
        $titles = [];
        preg_match_all('/^### (.+)$/mu', self::unreleasedText($changelog), $found);
        foreach ($found[1] as $title) {
            $titles[] = trim($title);
        }
        $onlyFixes = $titles !== [] && array_diff($titles, ['Behoben']) === [];

        return $onlyFixes ? "{$m[1]}.{$m[2]}.".((int) $m[3] + 1) : "{$m[1]}.".((int) $m[2] + 1).'.0';
    }

    /** Text unter «Unveröffentlicht» (ohne Überschrift). */
    public static function unreleasedText(string $changelog): string
    {
        $start = strpos($changelog, self::UNRELEASED);
        if ($start === false) {
            return '';
        }
        $rest = substr($changelog, $start + strlen(self::UNRELEASED));
        $end = preg_match('/^## \[/m', $rest, $m, PREG_OFFSET_CAPTURE) === 1 ? $m[0][1] : strlen($rest);

        return trim(substr($rest, 0, $end));
    }

    /** Abschnitt einer Version (ohne Überschrift) – Text für das GitHub-Release. */
    public static function notes(string $changelog, string $version): string
    {
        $pattern = '/^## \['.preg_quote($version, '/').'\][^\n]*\n(.*?)(?=^## \[|\z)/msu';

        return preg_match($pattern, $changelog, $m) === 1 ? trim($m[1]) : '';
    }

    /**
     * Neue Version vorbereiten: VERSION setzen, «Unveröffentlicht» wird zu «[X] – Datum».
     * Vorabversionen (0.32.0-beta.1) lassen den CHANGELOG unverändert.
     *
     * @return list<string> Hinweise
     */
    public function prepare(string $version, string $date): array
    {
        if (! self::isValid($version)) {
            throw new InvalidArgumentException("«{$version}» ist keine gültige Version (MAJOR.MINOR.PATCH, z. B. 0.32.0).");
        }
        $current = $this->current();
        if (self::isValid($current) && self::compare($version, $current) <= 0) {
            throw new InvalidArgumentException("Die neue Version muss grösser sein als die aktuelle ({$current}).");
        }
        $changelog = (string) file_get_contents($this->root.'/CHANGELOG.md');
        if (str_contains($changelog, '## ['.$version.']')) {
            throw new InvalidArgumentException("Version {$version} steht schon im CHANGELOG.");
        }
        if (self::unreleasedText($changelog) === '') {
            throw new InvalidArgumentException('Unter «Unveröffentlicht» stehen keine Änderungen – nichts zu veröffentlichen.');
        }
        if (! self::isPrerelease($version)) {
            $changelog = str_replace(self::UNRELEASED, self::UNRELEASED."\n\n## [{$version}] – {$date}", $changelog);
            file_put_contents($this->root.'/CHANGELOG.md', $changelog);
        }
        file_put_contents($this->root.'/VERSION', $version."\n");

        return ["VERSION: {$current} → {$version}", self::isPrerelease($version) ? 'Vorabversion: CHANGELOG unverändert.' : "CHANGELOG: «Unveröffentlicht» → [{$version}] – {$date}"];
    }

    /**
     * Prüft, ob Version, CHANGELOG und Plugin-Versionen zusammenpassen.
     *
     * @param  list<string>  $changedPlugins  Plugin-Ordner mit Änderungen seit dem letzten Tag
     * @param  array<string, string>  $taggedPluginVersions  Plugin-Ordner → Version beim letzten Tag
     * @return list<string> Fehler (leer = alles in Ordnung)
     */
    public function check(array $changedPlugins = [], array $taggedPluginVersions = []): array
    {
        $errors = [];
        $version = $this->current();
        if (! self::isValid($version)) {
            $errors[] = "VERSION «{$version}» ist keine gültige Version.";
        }
        $changelog = (string) @file_get_contents($this->root.'/CHANGELOG.md');
        if (! str_contains($changelog, self::UNRELEASED)) {
            $errors[] = 'CHANGELOG.md: Abschnitt «## [Unveröffentlicht]» fehlt.';
        }
        if (self::isValid($version) && ! self::isPrerelease($version) && ! str_contains($changelog, '## ['.$version.']')) {
            $errors[] = "CHANGELOG.md: Abschnitt für Version {$version} fehlt.";
        }
        foreach ($this->plugins() as $folder => $pluginVersion) {
            $normalized = self::pluginVersion($pluginVersion);
            if ($normalized === null) {
                $errors[] = "Plugin {$folder}: «version» «{$pluginVersion}» ist keine gültige Version (z. B. 1.0 oder 1.0.1).";

                continue;
            }
            $before = self::pluginVersion($taggedPluginVersions[$folder] ?? '');
            if (in_array($folder, $changedPlugins, true) && $before !== null && self::compare($normalized, $before) <= 0) {
                $errors[] = "Plugin {$folder} wurde geändert, aber «version» ist noch {$pluginVersion} – bitte erhöhen.";
            }
        }

        return $errors;
    }

    /** Plugin-Versionen dürfen zweiteilig sein (1.0 = 1.0.0). */
    public static function pluginVersion(string $version): ?string
    {
        if (preg_match('/^\d+\.\d+$/', $version) === 1) {
            $version .= '.0';
        }

        return self::isValid($version) ? $version : null;
    }

    /**
     * @return array<string, string> Plugin-Ordner → Version
     */
    public function plugins(): array
    {
        $plugins = [];
        foreach (glob($this->root.'/plugins/*/plugin.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            $plugins[basename(dirname($file))] = is_array($data) ? (string) ($data['version'] ?? '') : '';
        }
        ksort($plugins);

        return $plugins;
    }
}
