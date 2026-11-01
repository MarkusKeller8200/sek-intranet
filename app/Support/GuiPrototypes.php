<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * GUI-Prototypen: Bausteine der Oberfläche (config/intranet.php → gui_components) mit Versionen.
 *
 * Ablauf je Version: Entwicklung → Prüfung → Freigegeben (Prüfung kann zurück an Entwicklung).
 * Es gibt höchstens eine offene (nicht freigegebene) Version. Die zuletzt freigegebene Version ist
 * das verbindliche Template; bei der Freigabe werden alle Seiten, die den Baustein verwenden, als
 * «zu überarbeiten» festgehalten (Suche nach den Imports in resources/js/pages).
 */
final class GuiPrototypes
{
    public const COLLECTION = 'gui_components';

    public const STATUSES = ['entwicklung', 'pruefung', 'freigegeben'];

    public function __construct(private readonly JsonStore $store) {}

    /**
     * @return array<string, array{title: string, icon: string, description: string, imports: list<string>}>
     */
    public static function components(): array
    {
        /** @var array<string, array{title: string, icon: string, description: string, imports: list<string>}> */
        return (array) config('intranet.gui_components', []);
    }

    public static function exists(string $key): bool
    {
        return isset(self::components()[$key]);
    }

    /**
     * Gespeicherter Stand oder Ausgangslage (Version 1.0.0 in Entwicklung, noch nicht gespeichert).
     *
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        foreach ($this->store->all(self::COLLECTION) as $record) {
            if (($record['key'] ?? null) === $key) {
                return $record + ['wishes' => [], 'rework' => []];
            }
        }

        return [
            'key' => $key,
            'versions' => [[
                'version' => '1.0.0',
                'status' => 'entwicklung',
                'changes' => 'Ausgangslage: bestehender Baustein im Intranet.',
                'created_at' => null,
                'created_by' => null,
                'log' => [],
            ]],
            'wishes' => [],
            'rework' => [],
        ];
    }

    /**
     * Zusammenfassung je Baustein für Übersicht und Seite.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    public static function summary(array $record): array
    {
        /** @var list<array<string, mixed>> $versions */
        $versions = $record['versions'];
        $released = null;
        $open = null;
        foreach ($versions as $v) {
            if ($v['status'] === 'freigegeben') {
                $released = $v;
            } else {
                $open = $v;
            }
        }
        /** @var list<array<string, mixed>> $rework */
        $rework = $record['rework'] ?? [];
        /** @var list<array<string, mixed>> $wishes */
        $wishes = $record['wishes'] ?? [];

        return [
            'released' => $released,
            'open' => $open,
            'rework_total' => count($rework),
            'rework_open' => count(array_filter($rework, fn (array $r): bool => ($r['done_at'] ?? null) === null)),
            'wishes_open' => count(array_filter($wishes, fn (array $w): bool => ($w['version'] ?? null) === null)),
        ];
    }

    /**
     * Neue Version in «Entwicklung» anlegen (nur, wenn keine Version offen ist).
     *
     * @param  list<string>  $wishIds  Wünsche, die in diese Version einfliessen
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function createVersion(string $key, string $bump, string $changes, array $wishIds, ?string $userId): array
    {
        $record = $this->get($key);
        $summary = self::summary($record);
        if ($summary['open'] !== null) {
            throw new InvalidArgumentException("Version {$summary['open']['version']} ist noch offen. Erst freigeben, dann eine neue Version anlegen.");
        }

        $last = (string) end($record['versions'])['version'];
        $version = self::bump($last, $bump);
        $now = Carbon::now()->toIso8601String();

        $record['versions'][] = [
            'version' => $version,
            'status' => 'entwicklung',
            'changes' => $changes,
            'created_at' => $now,
            'created_by' => $userId,
            'log' => [['status' => 'entwicklung', 'at' => $now, 'by' => $userId, 'comment' => null]],
        ];
        $record['wishes'] = array_map(
            fn (array $w): array => in_array($w['id'], $wishIds, true) && ($w['version'] ?? null) === null ? ['version' => $version] + $w : $w,
            $record['wishes'],
        );

        return $this->persist($key, $record);
    }

    /**
     * Status der offenen Version ändern: pruefen (→ Prüfung), zurueck (→ Entwicklung), freigeben (→ Freigegeben).
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>, version: string}
     */
    public function transition(string $key, string $action, ?string $comment, ?string $userId): array
    {
        $record = $this->get($key);
        $index = array_key_last($record['versions']);
        $version = $record['versions'][$index];

        $to = match ([$action, $version['status']]) {
            ['pruefen', 'entwicklung'] => 'pruefung',
            ['zurueck', 'pruefung'] => 'entwicklung',
            ['freigeben', 'pruefung'] => 'freigegeben',
            default => throw new InvalidArgumentException(match ($version['status']) {
                'freigegeben' => 'Keine offene Version – zuerst eine neue Version anlegen.',
                'entwicklung' => 'In Entwicklung: zuerst zur Prüfung geben.',
                default => 'Dieser Schritt ist im Status «Prüfung» nicht möglich.',
            }),
        };

        $now = Carbon::now()->toIso8601String();
        if ($version['created_at'] === null) {
            // Ausgangslage: Entwicklung beginnt mit dem ersten Schritt
            $version['created_at'] = $now;
            $version['created_by'] = $userId;
            $version['log'] = [['status' => 'entwicklung', 'at' => $now, 'by' => $userId, 'comment' => null]];
        }
        $version['status'] = $to;
        $version['log'] = [...($version['log'] ?? []), ['status' => $to, 'at' => $now, 'by' => $userId, 'comment' => $comment]];
        if ($to === 'pruefung') {
            $version['review_at'] = $now;
            $version['review_by'] = $userId;
        } elseif ($to === 'freigegeben') {
            $version['released_at'] = $now;
            $version['released_by'] = $userId;
        }
        $record['versions'][$index] = $version;

        if ($to === 'freigegeben') {
            // Neues verbindliches Template: alle verwendenden Seiten überarbeiten (ersetzt frühere Liste)
            $record['rework'] = array_map(
                fn (string $file): array => ['file' => $file, 'version' => $version['version'], 'done_at' => null, 'done_by' => null],
                self::usages($key),
            );
        }

        return $this->persist($key, $record) + ['version' => (string) $version['version']];
    }

    /**
     * Seite als überarbeitet (oder wieder offen) markieren.
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function markRework(string $key, string $file, bool $done, ?string $userId): array
    {
        $record = $this->get($key);
        $found = false;
        foreach ($record['rework'] as $i => $item) {
            if ($item['file'] === $file) {
                $record['rework'][$i]['done_at'] = $done ? Carbon::now()->toIso8601String() : null;
                $record['rework'][$i]['done_by'] = $done ? $userId : null;
                $found = true;
            }
        }
        if (! $found) {
            throw new InvalidArgumentException('Seite nicht in der Liste.');
        }

        return $this->persist($key, $record);
    }

    /**
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function addWish(string $key, string $text, ?string $userId): array
    {
        $record = $this->get($key);
        $record['wishes'][] = ['id' => (string) Str::uuid7(), 'text' => $text, 'by' => $userId, 'at' => Carbon::now()->toIso8601String(), 'version' => null];

        return $this->persist($key, $record);
    }

    /**
     * Seiten, die den Baustein verwenden (Import eines der «imports»), relativ zu resources/js.
     *
     * @return list<string>
     */
    public static function usages(string $key): array
    {
        $imports = self::components()[$key]['imports'] ?? [];
        $root = resource_path('js');
        $dir = $root.'/pages';
        if ($imports === [] || ! is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            /** @var \SplFileInfo $file */
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.tsx') || str_contains($path, '/pages/gui-prototypes/')) {
                continue;
            }
            $source = (string) file_get_contents($path);
            foreach ($imports as $import) {
                if (str_contains($source, "from '{$import}'")) {
                    $files[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
                    break;
                }
            }
        }
        sort($files);

        return $files;
    }

    public static function bump(string $version, string $part): string
    {
        [$major, $minor, $patch] = array_map('intval', array_pad(explode('.', $version), 3, '0'));

        return match ($part) {
            'major' => ($major + 1).'.0.0',
            'minor' => "{$major}.".($minor + 1).'.0',
            'patch' => "{$major}.{$minor}.".($patch + 1),
            default => throw new InvalidArgumentException('Unbekannte Versionsstufe.'),
        };
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    private function persist(string $key, array $record): array
    {
        $before = null;
        foreach ($this->store->all(self::COLLECTION) as $existing) {
            if (($existing['key'] ?? null) === $key) {
                $before = $existing;
            }
        }
        $data = ['key' => $key, 'versions' => array_values($record['versions']), 'wishes' => array_values($record['wishes']), 'rework' => array_values($record['rework'])];
        $after = $before !== null
            ? $this->store->update(self::COLLECTION, (string) $before['id'], $data)
            : $this->store->insert(self::COLLECTION, $data);

        return ['before' => $before, 'after' => (array) $after];
    }
}
