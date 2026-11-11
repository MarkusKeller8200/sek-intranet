<?php

namespace Modules\Beschaffung;

use RuntimeException;

/**
 * Startinhalt der Kataloge aus dem ICT-Wiki (Confluence, ausgelesen am 11.11.2026, #198), bereinigt gemäss
 * Entscheid der Projektleitung. Unklare Angaben sind mit «Prüfen» und einem Hinweis markiert. Bilder liegen unter
 * startinhalt/bilder (App-Symbole aus dem App Store) und werden mit «start:<datei>» referenziert.
 */
final class Startinhalt
{
    public static function verzeichnis(): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.'startinhalt';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function artikel(): array
    {
        $daten = json_decode((string) file_get_contents(self::verzeichnis().DIRECTORY_SEPARATOR.'artikel.json'), true);
        if (! is_array($daten)) {
            throw new RuntimeException('Beschaffung: Startinhalt (artikel.json) ist nicht lesbar.');
        }

        return array_values(array_filter($daten, 'is_array'));
    }

    /**
     * Pfad eines Bildes aus dem Startinhalt, null wenn unbekannt (nur einfache Dateinamen).
     */
    public static function bild(string $datei): ?string
    {
        if (preg_match('/^[a-z0-9-]+\.(jpg|png|webp)$/', $datei) !== 1) {
            return null;
        }
        $pfad = self::verzeichnis().DIRECTORY_SEPARATOR.'bilder'.DIRECTORY_SEPARATOR.$datei;

        return is_file($pfad) ? $pfad : null;
    }
}
