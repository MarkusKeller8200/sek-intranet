<?php

namespace App\Support;

use App\Data\Contracts\AnnouncementRepository;
use App\Data\Store\JsonStore;
use Illuminate\Support\Facades\Gate;

/**
 * Neuerungen einer Version als Mitteilung (docs/KONZEPT-VERSIONIERUNG.md, #26).
 *
 * Quelle ist der Abschnitt «### Für Mitarbeitende» einer Version im CHANGELOG. Ein Punkt kann mit einem Recht
 * beginnen («- [visum-zeiterfassung] …»); dann sehen ihn nur Personen mit diesem Recht. Nach dem Deployment legt
 * `intranet:upgrade` für ein freigegebenes Release einmal die Mitteilung an. Die Sammlung «release_announcements»
 * merkt sich die Version – eine gelöschte Mitteilung kommt darum nicht wieder.
 */
final class ReleaseAnnouncements
{
    public const SECTION = 'Für Mitarbeitende';

    public const COLLECTION = 'release_announcements';

    public const SETTINGS = 'app_settings';

    public const SETTING_ID = 'release_announcements';

    public const CATEGORY = 'neuerungen';

    public const AUTHOR = 'Intranet';

    private const TAG = '/^\[([a-z][a-z0-9-]*)\]\s+/u';

    public function __construct(
        private readonly JsonStore $store,
        private readonly AnnouncementRepository $announcements,
    ) {}

    /**
     * Punkte «Für Mitarbeitende» einer Version.
     *
     * @return list<array{text: string, can: string|null}>
     */
    public static function items(string $changelog, string $version): array
    {
        foreach (AppVersion::parse($changelog) as $release) {
            if ($release['version'] !== $version) {
                continue;
            }
            foreach ($release['sections'] as $section) {
                if ($section['title'] === self::SECTION) {
                    return array_values(array_filter(array_map(self::item(...), $section['items']), fn (array $i): bool => $i['text'] !== ''));
                }
            }
        }

        return [];
    }

    /**
     * @return array{text: string, can: string|null}
     */
    public static function item(string $raw): array
    {
        $can = preg_match(self::TAG, $raw, $m) === 1 ? $m[1] : null;

        return ['text' => trim(str_replace(['**', '`'], '', self::stripTag($raw))), 'can' => $can];
    }

    /** Rechte-Kürzel «[recht] » am Anfang eines Punkts entfernen (Anzeige auf «Versionen»). */
    public static function stripTag(string $raw): string
    {
        return preg_replace(self::TAG, '', $raw) ?? $raw;
    }

    /** «Neu im Intranet: Version 0.33» (bei x.y.0 ohne .0). */
    public static function title(string $version): string
    {
        $short = preg_match('/^(\d+\.\d+)\.0$/', $version, $m) === 1 ? $m[1] : $version;

        return "Neu im Intranet: Version {$short}";
    }

    /** Release-Notes einer Version auf GitHub (#144); null ohne Repository oder bei ungültiger Version. */
    public static function releaseUrl(string $version): ?string
    {
        $repo = trim((string) config('intranet.github_repo', ''));
        if ($repo === '' || preg_match('#^[\w.-]+/[\w.-]+$#', $repo) !== 1 || ! Release::isValid($version)) {
            return null;
        }

        return "https://github.com/{$repo}/releases/tag/v{$version}";
    }

    /**
     * @param  list<array{text: string, can: string|null}>  $items
     */
    public static function body(string $version, array $items): string
    {
        $lines = array_map(fn (array $i): string => '• '.$i['text'], $items);

        return "Neu mit Version {$version}:\n\n".implode("\n", $lines)."\n\nAlle Änderungen stehen unter «Versionen».";
    }

    public function enabled(): bool
    {
        $record = $this->store->find(self::SETTINGS, self::SETTING_ID);

        return $record === null || ($record['enabled'] ?? true) === true;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->store->find(self::SETTINGS, self::SETTING_ID) !== null
            ? $this->store->update(self::SETTINGS, self::SETTING_ID, ['enabled' => $enabled])
            : $this->store->insert(self::SETTINGS, ['id' => self::SETTING_ID, 'enabled' => $enabled]);
    }

    /** Wurde für diese Version schon eine Mitteilung angelegt (auch wenn sie inzwischen gelöscht ist)? */
    public function announced(string $version): bool
    {
        return $this->store->find(self::COLLECTION, $version) !== null;
    }

    /**
     * Mitteilung für ein freigegebenes Release einmal anlegen. Null = nichts angelegt
     * (Vorabversion, ausgeschaltet, schon angelegt oder kein Abschnitt «Für Mitarbeitende»).
     *
     * @return array<string, mixed>|null
     */
    public function publish(string $version, string $changelog): ?array
    {
        if (! Release::isValid($version) || Release::isPrerelease($version) || ! $this->enabled() || $this->announced($version)) {
            return null;
        }
        $items = self::items($changelog, $version);
        if ($items === []) {
            return null;
        }
        // Unbekannte Rechte (z. B. Tippfehler) nicht als Sperre behandeln: Punkt für alle
        $known = array_map('strval', array_keys((array) config('intranet.abilities', [])));
        $items = array_map(fn (array $i): array => $i['can'] !== null && ! in_array($i['can'], $known, true) ? ['text' => $i['text'], 'can' => null] : $i, $items);

        $announcement = $this->announcements->create([
            'title' => self::title($version),
            'body' => self::body($version, $items),
            'category' => self::CATEGORY,
            'audience' => 'alle',
            'pinned' => false,
            'published_at' => now()->toAtomString(),
            'author_id' => null,
            'author_label' => self::AUTHOR,
            'release_version' => $version,
            'release_items' => $items,
        ]);
        $this->store->insert(self::COLLECTION, ['id' => $version, 'announcement_id' => $announcement['id']]);

        return $announcement;
    }

    /**
     * Mitteilung für die angemeldete Person: Punkte mit Recht nur, wenn sie es hat. Null = nichts für sie.
     * Bearbeitete Mitteilungen (ohne release_items) bleiben unverändert.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public static function forViewer(array $item): ?array
    {
        $items = $item['release_items'] ?? null;
        if (! is_array($items) || $items === []) {
            return $item;
        }
        $visible = [];
        foreach ($items as $i) {
            if (! is_array($i) || ! is_string($i['text'] ?? null)) {
                continue;
            }
            $can = is_string($i['can'] ?? null) ? $i['can'] : null;
            if ($can === null || Gate::allows($can)) {
                $visible[] = ['text' => $i['text'], 'can' => $can];
            }
        }
        if ($visible === []) {
            return null;
        }

        return array_merge($item, ['body' => self::body((string) ($item['release_version'] ?? ''), $visible)]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function filterForViewer(array $items): array
    {
        return array_values(array_filter(array_map(self::forViewer(...), $items), fn (?array $i): bool => $i !== null));
    }
}
