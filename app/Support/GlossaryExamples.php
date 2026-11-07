<?php

namespace App\Support;

use App\Data\Contracts\GlossaryRepository;
use App\Data\Store\JsonStore;

/**
 * Beispieldaten zum Testen des Glossars (#68): Kurzbeschreibungen, mehrere Kategorien und Herkünfte,
 * eigene Bezeichnung und Markdown-Notiz. Einmalig; ergänzt nur leere bzw. unveränderte Felder.
 */
final class GlossaryExamples
{
    private const SETTING_ID = 'glossary_examples';

    /** Abkürzung → Kurzbeschreibung (Tooltip). */
    private const SHORT = [
        'VSA' => 'Amt der Bildungsdirektion, zuständig für die Volksschule im Kanton Zürich.',
        'BI' => 'Direktion des Kantons Zürich für Volksschule, Mittel- und Berufsschulen.',
        'AJB' => 'Kantonales Amt für Kinder- und Jugendhilfe sowie Berufsberatung.',
        'MBA' => 'Kantonales Amt für Mittelschulen und Berufsbildung.',
        'BR' => 'Kantonale Behörde für Grundsatzentscheide im Bildungswesen.',
        'EDK' => 'Konferenz der kantonalen Bildungsdirektorinnen und -direktoren.',
        'PHZH' => 'Pädagogische Hochschule Zürich: Aus- und Weiterbildung der Lehrpersonen.',
        'SL' => 'Leitet die Schule pädagogisch, personell und organisatorisch.',
        'SP' => 'Behörde der Schulgemeinde, strategische Führung der Schule.',
        'LP' => 'Unterrichtet an der Volksschule.',
        'VZE' => 'Mass für Pensen und Stellen (100 % = 1 VZE).',
        'MAB' => 'Beurteilung der Mitarbeitenden durch die Schulleitung.',
        'VSG' => 'Volksschulgesetz des Kantons Zürich.',
        'VSV' => 'Volksschulverordnung des Kantons Zürich.',
        'RRB' => 'Entscheid des Regierungsrats, mit Nummer und Jahr zitiert.',
        'IDG' => 'Zürcher Gesetz über die Information und den Datenschutz.',
        'DSG' => 'Bundesgesetz über den Datenschutz.',
        'ICT' => 'Informations- und Kommunikationstechnologie.',
        'MDM' => 'Zentrale Verwaltung mobiler Geräte, z. B. der iPads.',
        'BYOD' => 'Eigene Geräte der Schülerinnen und Schüler im Unterricht.',
        'DaZ' => 'Förderunterricht Deutsch für Kinder mit anderer Erstsprache.',
        'SSG' => 'Gespräch zur Abklärung und Planung besonderer Förderung.',
        'SPD' => 'Fachstelle für schulpsychologische Abklärung und Beratung.',
        'ISR' => 'Sonderschulung in einer Regelklasse der Volksschule.',
        'QUIMS' => 'Kantonales Programm für Schulen mit vielen fremdsprachigen Kindern.',
        'BFS' => 'Statistikamt des Bundes.',
        'PISA' => 'Internationale Schulleistungsstudie der OECD.',
        'Sek A' => 'Abteilung der Sekundarschule mit den höchsten Anforderungen.',
    ];

    /** Abkürzung → zusätzliche Herkunft (mehrere Herkünfte als Beispiel). */
    private const ORIGIN = ['BFS' => 'bund', 'DSG' => 'bund', 'IDG' => 'kanton', 'VSG' => 'kanton'];

    /** Abkürzung → zusätzliche Kategorie (mehrere Kategorien als Beispiel). */
    private const CATEGORY = ['BYOD' => 'unt', 'MDM' => 'unt', 'DaZ' => 'unt'];

    public function __construct(
        private readonly GlossaryRepository $glossary,
        private readonly JsonStore $store,
    ) {}

    /** @return int Anzahl ergänzter Einträge */
    public function apply(): int
    {
        if ($this->store->find(ReleaseAnnouncements::SETTINGS, self::SETTING_ID) !== null) {
            return 0;
        }

        $changed = 0;
        // Listen mit UUID (#159): Schlüssel des Startinhalts → ID
        $origin = fn (string $key): string => $this->glossary->listId('herkunft', $key) ?? $key;
        $category = fn (string $key): string => $this->glossary->listId('kategorien', $key) ?? $key;
        foreach ($this->glossary->all() as $entry) {
            $abk = (string) ($entry['abk_offiziell'] ?? '');
            $update = [];
            if (isset(self::SHORT[$abk]) && trim((string) ($entry['kurzbeschreibung'] ?? '')) === '') {
                $update['kurzbeschreibung'] = self::SHORT[$abk];
            }
            $origins = (array) ($entry['herkunft'] ?? []);
            if (isset(self::ORIGIN[$abk]) && $origins === [$origin('vsa')]) {
                $update['herkunft'] = [$origin('vsa'), $origin(self::ORIGIN[$abk])];
            }
            $categories = (array) ($entry['kategorien'] ?? []);
            if (isset(self::CATEGORY[$abk]) && count($categories) === 1 && ! in_array($category(self::CATEGORY[$abk]), $categories, true)) {
                $update['kategorien'] = [...$categories, $category(self::CATEGORY[$abk])];
            }
            if ($abk === 'VSA' && trim((string) ($entry['notiz'] ?? '')) === '') {
                $update['notiz'] = "**Beispiel-Notiz:** Weisungen des VSA gelten für alle Schulen.\n- [Volksschule Kanton Zürich](".GlossarySeed::VSA_URL.')';
            }
            if ($abk === 'BYOD' && trim((string) ($entry['bez_eigen'] ?? '')) === '') {
                $update['bez_eigen'] = 'Eigene Geräte im Unterricht';
                $update['alias_eigen'] = 'Eigene Geräte';
            }
            if ($update !== []) {
                $this->glossary->update((string) $entry['id'], $update);
                $changed++;
            }
        }
        $this->store->insert(ReleaseAnnouncements::SETTINGS, ['id' => self::SETTING_ID, 'enabled' => true]);

        return $changed;
    }
}
