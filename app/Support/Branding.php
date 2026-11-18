<?php

namespace App\Support;

use App\Data\Store\JsonStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Erscheinungsbild der Instanz (#216, White-Labeling): welche Marke links oben und auf der Anmeldeseite steht.
 *
 * - «eduksx» (Vorgabe): Produkt-Wortmarke «EduKSX-Intranet» und das freigegebene Icon «Intranet» (CI-Manual v1.0).
 * - «markussx»: Dachmarke «MarKusSXCH» als Wortmarke, Icon wie EduKSX.
 * - «eigene»: eigenes Logo (hell, optional dunkel) und Signet der Instanz, hochgeladen unter Einstellungen.
 *
 * Wortmarken werden als Text mit Farbsegmenten gezeichnet (CI-Manual, Komponente «BrandWordmark»), nicht als Bild.
 * Dateien liegen auf der Disk «local» unter branding/; ausgeliefert über /branding/{slot} (auch ohne Anmeldung, für
 * die Anmeldeseite). Ein Datensatz «instanz» in der Sammlung «branding»; ohne Datensatz gilt EduKSX.
 */
final class Branding
{
    public const COLLECTION = 'branding';

    public const ID = 'instanz';

    public const MARKEN = ['eduksx' => 'EduKSX', 'markussx' => 'MarKusSXCH', 'eigene' => 'Eigenes Logo'];

    /** Hochladbare Bilder: Logo hell, Logo dunkel, Signet (quadratisch, für die schmale Leiste). */
    public const SLOTS = ['logo_hell' => 'Logo (heller Hintergrund)', 'logo_dunkel' => 'Logo (dunkler Hintergrund)', 'signet' => 'Signet (quadratisch)'];

    public const ORDNER = 'branding';

    /** Erlaubte Dateitypen → Endung. */
    public const TYPEN = ['image/svg+xml' => 'svg', 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public const MAX_KB = 1024;

    public function __construct(private readonly JsonStore $store) {}

    /**
     * Gespeicherte Einstellung (ohne Datensatz: EduKSX).
     *
     * @return array{marke: string, name: string, logo_hell: string|null, logo_dunkel: string|null, signet: string|null}
     */
    public function einstellung(): array
    {
        $r = $this->store->find(self::COLLECTION, self::ID) ?? [];
        $marke = (string) ($r['marke'] ?? 'eduksx');

        return [
            'marke' => isset(self::MARKEN[$marke]) ? $marke : 'eduksx',
            'name' => (string) ($r['name'] ?? ''),
            'logo_hell' => self::datei($r['logo_hell'] ?? null),
            'logo_dunkel' => self::datei($r['logo_dunkel'] ?? null),
            'signet' => self::datei($r['signet'] ?? null),
        ];
    }

    /**
     * Für die Oberfläche (geteilte Inertia-Daten): Wortmarke oder Bild-URLs, Signet und Name der Instanz.
     *
     * @return array{marke: string, name: string, wortmarke: string|null, logo: array{hell: string, dunkel: string}|null, signet: string, version: string}
     */
    public function anzeige(): array
    {
        $e = $this->einstellung();
        $v = substr(md5(json_encode($e) ?: ''), 0, 8);
        $url = fn (string $slot): string => '/branding/'.$slot.'?v='.$v;
        $eigen = $e['marke'] === 'eigene' && $e['logo_hell'] !== null;

        return [
            'marke' => $eigen ? 'eigene' : ($e['marke'] === 'markussx' ? 'markussx' : 'eduksx'),
            'name' => $e['name'],
            // Schlüssel der Wortmarke im CI-Manual (education = EduKSX, developer = MarKusSXCH)
            'wortmarke' => $eigen ? null : ($e['marke'] === 'markussx' ? 'developer' : 'education'),
            'logo' => $eigen ? ['hell' => $url('logo_hell'), 'dunkel' => $e['logo_dunkel'] !== null ? $url('logo_dunkel') : $url('logo_hell')] : null,
            'signet' => $eigen && $e['signet'] !== null ? $url('signet') : '/images/brands/eduksx-intranet-tile-light.svg',
            'version' => $v,
        ];
    }

    /**
     * Marke und Name speichern.
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function speichern(string $marke, string $name, ?string $userId): array
    {
        if (! isset(self::MARKEN[$marke])) {
            throw new InvalidArgumentException('Unbekannte Marke.');
        }
        if ($marke === 'eigene' && $this->einstellung()['logo_hell'] === null) {
            throw new InvalidArgumentException('Für «Eigenes Logo» zuerst ein Logo hochladen.');
        }

        return $this->setzen(['marke' => $marke, 'name' => mb_substr(trim($name), 0, 80), 'updated_by' => $userId]);
    }

    /**
     * Bild hochladen (SVG, PNG, JPG, WebP; höchstens 1 MB). SVG ohne Skripte, Ereignisse und externe Verweise.
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function hochladen(string $slot, UploadedFile $datei, ?string $userId): array
    {
        if (! isset(self::SLOTS[$slot])) {
            throw new InvalidArgumentException('Unbekanntes Bild.');
        }
        $inhalt = (string) $datei->get();
        $typ = self::typ($inhalt);
        if ($typ === null) {
            throw new InvalidArgumentException('Nur SVG, PNG, JPG oder WebP.');
        }
        if (strlen($inhalt) > self::MAX_KB * 1024) {
            throw new InvalidArgumentException('Datei zu gross (höchstens 1 MB).');
        }
        if ($typ === 'image/svg+xml') {
            self::svgPruefen($inhalt);
        }
        $alt = $this->einstellung()[$slot];
        $neu = self::ORDNER.'/'.$slot.'.'.self::TYPEN[$typ];
        if ($alt !== null && $alt !== $neu) {
            Storage::disk('local')->delete($alt);
        }
        Storage::disk('local')->put($neu, $inhalt);

        return $this->setzen([$slot => $neu, 'updated_by' => $userId]);
    }

    /**
     * Bild entfernen; ohne Logo fällt die Instanz auf EduKSX zurück.
     *
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    public function entfernen(string $slot, ?string $userId): array
    {
        if (! isset(self::SLOTS[$slot])) {
            throw new InvalidArgumentException('Unbekanntes Bild.');
        }
        $alt = $this->einstellung()[$slot];
        if ($alt !== null) {
            Storage::disk('local')->delete($alt);
        }
        $daten = [$slot => null, 'updated_by' => $userId];
        if ($slot === 'logo_hell' && $this->einstellung()['marke'] === 'eigene') {
            $daten['marke'] = 'eduksx';
        }

        return $this->setzen($daten);
    }

    /**
     * Pfad und Typ eines Bilds zum Ausliefern, sonst null.
     *
     * @return array{pfad: string, typ: string}|null
     */
    public function bild(string $slot): ?array
    {
        $pfad = isset(self::SLOTS[$slot]) ? ($this->einstellung()[$slot] ?? null) : null;
        if ($pfad === null || ! Storage::disk('local')->exists($pfad)) {
            return null;
        }
        $typ = array_search(pathinfo($pfad, PATHINFO_EXTENSION), self::TYPEN, true);

        return is_string($typ) ? ['pfad' => $pfad, 'typ' => $typ] : null;
    }

    /**
     * Bestehende Installationen (#216): Daten von vor dem White-Labeling behalten das Logo der Sekundarschule Bülach
     * als eigenes Logo. Neue Installationen zeigen EduKSX. Einmalig (nur ohne Datensatz).
     */
    public function bestandUebernehmen(string $stichtag = '2026-11-18'): bool
    {
        if ($this->store->find(self::COLLECTION, self::ID) !== null) {
            return false;
        }
        $aelteste = null;
        foreach ($this->store->all('users') as $u) {
            $t = (string) ($u['created_at'] ?? '');
            if ($t !== '' && ($aelteste === null || $t < $aelteste)) {
                $aelteste = $t;
            }
        }
        if ($aelteste === null || substr($aelteste, 0, 10) >= $stichtag) {
            return false;
        }
        $daten = ['id' => self::ID, 'marke' => 'eigene', 'name' => 'Sekundarschule Bülach', 'updated_by' => null];
        foreach (['logo_hell' => 'sekbuelach-logo.svg', 'logo_dunkel' => 'sekbuelach-logo-dark.svg', 'signet' => 'sekbuelach-icon.svg'] as $slot => $datei) {
            $quelle = public_path('images/'.$datei);
            if (is_file($quelle)) {
                Storage::disk('local')->put(self::ORDNER.'/'.$slot.'.svg', (string) file_get_contents($quelle));
                $daten[$slot] = self::ORDNER.'/'.$slot.'.svg';
            }
        }
        if (! isset($daten['logo_hell'])) {
            return false;
        }
        $this->store->insert(self::COLLECTION, $daten);

        return true;
    }

    /**
     * @param  array<string, mixed>  $daten
     * @return array{before: array<string, mixed>|null, after: array<string, mixed>}
     */
    private function setzen(array $daten): array
    {
        $before = $this->store->find(self::COLLECTION, self::ID);
        $after = $before !== null
            ? $this->store->update(self::COLLECTION, self::ID, $daten)
            : $this->store->insert(self::COLLECTION, ['id' => self::ID, 'marke' => 'eduksx'] + $daten);

        return ['before' => $before, 'after' => (array) $after];
    }

    private static function datei(mixed $pfad): ?string
    {
        return is_string($pfad) && str_starts_with($pfad, self::ORDNER.'/') && ! str_contains($pfad, '..') ? $pfad : null;
    }

    /** Dateityp am Inhalt erkennen (nicht an Endung oder Angabe des Browsers). */
    private static function typ(string $inhalt): ?string
    {
        if (str_starts_with($inhalt, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($inhalt, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($inhalt, 'RIFF') && substr($inhalt, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        $kopf = ltrim(substr($inhalt, 0, 1024));
        if (preg_match('/^(<\?xml[^>]*>\s*)?(<!--.*?-->\s*)*<svg[\s>]/is', $kopf) === 1) {
            return 'image/svg+xml';
        }

        return null;
    }

    /** SVG ablehnen, wenn es Skripte, Ereignis-Attribute, eingebettete Fremdinhalte oder externe Verweise enthält. */
    private static function svgPruefen(string $svg): void
    {
        $verboten = [
            '/<\s*script/i', '/<\s*foreignObject/i', '/<\s*iframe/i', '/<\s*embed/i', '/<\s*object/i',
            '/\son[a-z]+\s*=/i', '/javascript\s*:/i', '/<!ENTITY/i', '/<!DOCTYPE/i',
            '/(?:xlink:)?href\s*=\s*["\']\s*(?!#|data:image\/(?:png|jpeg|webp);base64,)/i',
            '/@import/i', '/url\(\s*["\']?\s*(?!#|data:image\/)/i',
        ];
        foreach ($verboten as $muster) {
            if (preg_match($muster, $svg) === 1) {
                throw new InvalidArgumentException('Das SVG enthält Skripte oder externe Verweise und wird nicht übernommen.');
            }
        }
    }
}
