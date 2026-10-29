<?php

namespace App\Data\Store;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Einfacher Dokumentenspeicher auf Dateibasis für den Prototyp.
 *
 * - Eine Sammlung = eine Datei <data_path>/<sammlung>.json (lesbar, von Hand editierbar)
 * - Lesen mit geteilter, Schreiben mit exklusiver Sperre (flock auf <sammlung>.lock)
 * - Schreiben atomar über eine temporäre Datei und rename()
 * - IDs als UUID v7 (zeitlich sortierbar, gleich wie in IDA geplant)
 *
 * Bewusst ohne Framework-Abhängigkeiten, damit die Klasse isoliert testbar ist.
 * Ersetzt wird sie später nicht direkt, sondern über die Repository-Schnittstellen.
 */
final class JsonStore
{
    public const FORMAT_VERSION = 1;

    public function __construct(
        private readonly string $path,
        private readonly string $timezone = 'Europe/Zurich',
    ) {}

    public function path(): string
    {
        return $this->path;
    }

    public function filePath(string $collection): string
    {
        return $this->path.DIRECTORY_SEPARATOR.$this->guard($collection).'.json';
    }

    public function exists(string $collection): bool
    {
        return is_file($this->filePath($collection));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(string $collection): array
    {
        return $this->withLock($collection, LOCK_SH, fn (): array => $this->read($collection));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $collection, string $id): ?array
    {
        foreach ($this->all($collection) as $record) {
            if (($record['id'] ?? null) === $id) {
                return $record;
            }
        }

        return null;
    }

    public function count(string $collection): int
    {
        return count($this->all($collection));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function insert(string $collection, array $attributes): array
    {
        return $this->withLock($collection, LOCK_EX, function () use ($collection, $attributes): array {
            $records = $this->read($collection);
            $now = $this->now();

            $record = ['id' => self::uuid7()]
                + $this->withoutMeta($attributes)
                + ['created_at' => $now, 'updated_at' => $now];

            if (isset($attributes['id']) && is_string($attributes['id']) && $attributes['id'] !== '') {
                $record['id'] = $attributes['id'];
            }

            foreach ($records as $existing) {
                if (($existing['id'] ?? null) === $record['id']) {
                    throw new RuntimeException("Datensatz {$record['id']} existiert bereits in {$collection}.");
                }
            }

            $records[] = $record;
            $this->write($collection, $records);

            return $record;
        });
    }

    /**
     * Ändert die übergebenen Felder; nicht übergebene Felder bleiben erhalten.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null null, wenn der Datensatz nicht existiert
     */
    public function update(string $collection, string $id, array $attributes): ?array
    {
        return $this->withLock($collection, LOCK_EX, function () use ($collection, $id, $attributes): ?array {
            $records = $this->read($collection);

            foreach ($records as $index => $record) {
                if (($record['id'] ?? null) !== $id) {
                    continue;
                }

                $updated = array_merge($record, $this->withoutMeta($attributes), [
                    'id' => $id,
                    'created_at' => $record['created_at'] ?? $this->now(),
                    'updated_at' => $this->now(),
                ]);

                $records[$index] = $updated;
                $this->write($collection, $records);

                return $updated;
            }

            return null;
        });
    }

    public function delete(string $collection, string $id): bool
    {
        return $this->withLock($collection, LOCK_EX, function () use ($collection, $id): bool {
            $records = $this->read($collection);
            $remaining = array_values(array_filter(
                $records,
                fn (array $record): bool => ($record['id'] ?? null) !== $id,
            ));

            if (count($remaining) === count($records)) {
                return false;
            }

            $this->write($collection, $remaining);

            return true;
        });
    }

    /**
     * Ersetzt den gesamten Inhalt einer Sammlung (für Seed und Import).
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function replace(string $collection, array $records): void
    {
        $this->withLock($collection, LOCK_EX, function () use ($collection, $records): void {
            $now = $this->now();

            $normalized = array_map(fn (array $record): array => array_merge(
                ['id' => self::uuid7()],
                $record,
                [
                    'created_at' => $record['created_at'] ?? $now,
                    'updated_at' => $record['updated_at'] ?? $now,
                ],
            ), $records);

            $this->write($collection, $normalized);
        });
    }

    /**
     * Liste der vorhandenen Sammlungen (Dateinamen ohne .json).
     *
     * @return list<string>
     */
    public function collections(): array
    {
        $files = glob($this->path.DIRECTORY_SEPARATOR.'*.json') ?: [];
        $names = array_map(fn (string $file): string => basename($file, '.json'), $files);
        sort($names);

        return $names;
    }

    /**
     * UUID Version 7 (RFC 9562): 48 Bit Millisekunden-Zeitstempel + Zufall.
     */
    public static function uuid7(): string
    {
        static $lastMilliseconds = 0;
        static $counter = 0;

        $milliseconds = (int) floor(microtime(true) * 1000);
        $random = random_bytes(10);

        // Monoton innerhalb eines Prozesses (RFC 9562, Methode 1: Zähler in rand_a).
        if ($milliseconds <= $lastMilliseconds) {
            $counter++;

            if ($counter > 0x0FFF) {
                $lastMilliseconds++;
                $counter = 0;
            }

            $milliseconds = $lastMilliseconds;
        } else {
            $lastMilliseconds = $milliseconds;
            $counter = (ord($random[0]) << 8 | ord($random[1])) & 0x07FF;
        }

        $time = str_pad(dechex($milliseconds), 12, '0', STR_PAD_LEFT);
        $randA = $counter;
        $randB = substr($random, 2);
        $randB[0] = chr(ord($randB[0]) & 0x3F | 0x80);

        $hex = $time.sprintf('7%03x', $randA).bin2hex($randB);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone($this->timezone)))->format(DATE_ATOM);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withoutMeta(array $attributes): array
    {
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);

        return $attributes;
    }

    private function guard(string $collection): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $collection) !== 1) {
            throw new InvalidArgumentException("Ungültiger Sammlungsname: {$collection}");
        }

        return $collection;
    }

    /**
     * @template T
     *
     * @param  int<0, 7>  $mode  LOCK_SH oder LOCK_EX
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(string $collection, int $mode, callable $callback): mixed
    {
        $this->ensureDirectory();

        $lockFile = $this->path.DIRECTORY_SEPARATOR.$this->guard($collection).'.lock';
        $handle = fopen($lockFile, 'c');

        if ($handle === false) {
            throw new RuntimeException("Sperrdatei {$lockFile} kann nicht geöffnet werden.");
        }

        try {
            if (! flock($handle, $mode)) {
                throw new RuntimeException("Sperre auf {$lockFile} nicht möglich.");
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(string $collection): array
    {
        $file = $this->filePath($collection);

        if (! is_file($file)) {
            return [];
        }

        $contents = file_get_contents($file);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Datei {$file} enthält kein gültiges JSON: {$e->getMessage()}", 0, $e);
        }

        // Erlaubt sind {"records": [...]} und – für Handarbeit – eine reine Liste.
        $records = is_array($data) && array_key_exists('records', $data) ? $data['records'] : $data;

        if (! is_array($records)) {
            throw new RuntimeException("Datei {$file} hat ein unbekanntes Format.");
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values(array_filter($records, 'is_array'));

        return $list;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function write(string $collection, array $records): void
    {
        $file = $this->filePath($collection);

        $json = json_encode([
            'collection' => $collection,
            'format_version' => self::FORMAT_VERSION,
            'updated_at' => $this->now(),
            'records' => $records,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $temp = $file.'.'.bin2hex(random_bytes(4)).'.tmp';

        if (file_put_contents($temp, $json."\n") === false) {
            throw new RuntimeException("Datei {$temp} kann nicht geschrieben werden.");
        }

        if (! rename($temp, $file)) {
            @unlink($temp);

            throw new RuntimeException("Datei {$file} kann nicht ersetzt werden.");
        }
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->path) && ! mkdir($this->path, 0775, true) && ! is_dir($this->path)) {
            throw new RuntimeException("Datenordner {$this->path} kann nicht angelegt werden.");
        }
    }
}
