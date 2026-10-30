<?php

namespace App\Console\Commands;

use App\Data\Store\JsonStore;
use App\Support\PersonFields;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Legt erfundene Demo-Daten an. Keine echten Personendaten verwenden (IDA AGENTS.md).
 */
class SeedIntranetCommand extends Command
{
    protected $signature = 'intranet:seed
        {--force : Vorhandene Daten ohne Rückfrage überschreiben}';

    protected $description = 'Legt Demo-Daten für den Intranet-Prototyp an (JSON-Dateien)';

    public function handle(JsonStore $store): int
    {
        $collections = ['users', 'announcements', 'events', 'documents'];
        $existing = array_values(array_filter($collections, fn (string $c): bool => $store->count($c) > 0));

        if ($existing !== [] && ! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->components->info('Daten vorhanden ('.implode(', ', $existing).'), nichts geändert. Mit --force überschreiben.');

                return self::SUCCESS;
            }

            if (! $this->confirm('Vorhandene Daten in '.implode(', ', $existing).' werden überschrieben. Fortfahren?', false)) {
                $this->components->warn('Abgebrochen, nichts geändert.');

                return self::SUCCESS;
            }
        }

        $now = CarbonImmutable::now();
        $ids = [
            'admin' => JsonStore::uuid7(),
            'schulleitung' => JsonStore::uuid7(),
            'redaktion' => JsonStore::uuid7(),
            'lp1' => JsonStore::uuid7(),
            'lp2' => JsonStore::uuid7(),
            'lp3' => JsonStore::uuid7(),
            'sekretariat' => JsonStore::uuid7(),
            'hauswart' => JsonStore::uuid7(),
        ];

        $store->replace('users', array_map(PersonFields::withNames(...), [
            ['id' => $ids['admin'], 'name' => 'Andrea Muster', 'email' => 'andrea.muster@sekbuelach.example', 'role' => 'admin', 'function' => 'ICT-Verantwortliche', 'school' => 'verwaltung', 'phone' => '044 000 00 01', 'active' => true, 'short' => 'AM', 'department' => 'Schulverwaltung', 'entry_date' => '2019-08-01', 'office_street' => 'Musterstrasse 1', 'office_zip' => '8180', 'office_city' => 'Bülach', 'escola_id' => 'T-001', 'username' => 'andrea.muster'],
            ['id' => $ids['schulleitung'], 'name' => 'Beat Beispiel', 'email' => 'beat.beispiel@sekbuelach.example', 'role' => 'redaktion', 'function' => 'Schulleiter', 'school' => 'hinterbirch', 'phone' => '044 000 00 02', 'active' => true],
            ['id' => $ids['redaktion'], 'name' => 'Claudia Demo', 'email' => 'claudia.demo@sekbuelach.example', 'role' => 'redaktion', 'function' => 'Schulverwaltung', 'school' => 'verwaltung', 'phone' => '044 000 00 03', 'active' => true],
            ['id' => $ids['lp1'], 'name' => 'Daniel Probe', 'email' => 'daniel.probe@sekbuelach.example', 'role' => 'mitarbeitende', 'function' => 'Lehrperson Sek A, Klassenlehrer 2a', 'school' => 'hinterbirch', 'phone' => null, 'active' => true, 'short' => 'DP', 'mobile' => '079 000 00 04', 'entry_date' => '2022-08-15', 'escola_roles' => 'Lehrperson Hinterbirch', 'private_city' => 'Testdorf', 'emergency_name' => 'Erfunden Person', 'emergency_phone' => '079 000 99 99'],
            ['id' => $ids['lp2'], 'name' => 'Eva Fiktiv', 'email' => 'eva.fiktiv@sekbuelach.example', 'role' => 'mitarbeitende', 'function' => 'Lehrperson Mathematik', 'school' => 'mettmenriet', 'phone' => null, 'active' => true],
            ['id' => $ids['lp3'], 'name' => 'Fabian Platzhalter', 'email' => 'fabian.platzhalter@sekbuelach.example', 'role' => 'mitarbeitende', 'function' => 'Schulische Heilpädagogik', 'school' => 'mettmenriet', 'phone' => null, 'active' => true],
            ['id' => $ids['sekretariat'], 'name' => 'Gabriela Vorlage', 'email' => 'gabriela.vorlage@sekbuelach.example', 'role' => 'mitarbeitende', 'function' => 'Sekretariat', 'school' => 'verwaltung', 'phone' => '044 000 00 10', 'active' => true],
            ['id' => $ids['hauswart'], 'name' => 'Hans Exempel', 'email' => 'hans.exempel@sekbuelach.example', 'role' => 'mitarbeitende', 'function' => 'Hauswart', 'school' => 'hinterbirch', 'phone' => '044 000 00 20', 'active' => false],
        ]));

        $store->replace('announcements', [
            [
                'title' => 'Willkommen im neuen Intranet (Prototyp)',
                'body' => "Dies ist ein Prototyp des SekBülach-Intranets. Alle Inhalte sind erfunden.\n\nBitte testet die Funktionen und meldet Rückmeldungen an die ICT-Verantwortliche.",
                'category' => 'it', 'audience' => 'alle', 'pinned' => true,
                'published_at' => $now->subDays(1)->setTime(7, 30)->toAtomString(), 'author_id' => $ids['admin'],
            ],
            [
                'title' => 'Gesamtkonferenz: Traktanden online',
                'body' => "Die Traktandenliste für die nächste Gesamtkonferenz ist unter «Dokumente» abgelegt.\nAnträge bitte bis Ende Woche an die Schulleitung.",
                'category' => 'schulleitung', 'audience' => 'lehrpersonen', 'pinned' => false,
                'published_at' => $now->subDays(3)->setTime(12, 0)->toAtomString(), 'author_id' => $ids['schulleitung'],
            ],
            [
                'title' => 'iPad-Rückgabe 3. Klassen',
                'body' => 'Die Rückgabe der iPads der 3. Klassen findet in der letzten Schulwoche statt. Details folgen per Mail an die Klassenlehrpersonen.',
                'category' => 'it', 'audience' => 'lehrpersonen', 'pinned' => false,
                'published_at' => $now->subDays(6)->setTime(9, 15)->toAtomString(), 'author_id' => $ids['admin'],
            ],
            [
                'title' => 'Neue Öffnungszeiten Sekretariat',
                'body' => 'Ab nächstem Monat ist das Sekretariat am Mittwochnachmittag geschlossen.',
                'category' => 'allgemein', 'audience' => 'alle', 'pinned' => false,
                'published_at' => $now->subDays(10)->setTime(8, 0)->toAtomString(), 'author_id' => $ids['redaktion'],
            ],
            [
                'title' => 'Vorschau: Sporttag (noch nicht veröffentlicht)',
                'body' => 'Diese Mitteilung ist für die Zukunft geplant und erscheint erst ab dem Veröffentlichungsdatum.',
                'category' => 'anlass', 'audience' => 'alle', 'pinned' => false,
                'published_at' => $now->addDays(5)->setTime(7, 0)->toAtomString(), 'author_id' => $ids['redaktion'],
            ],
        ]);

        $store->replace('events', [
            ['title' => 'Gesamtkonferenz', 'description' => 'Traktanden siehe Dokumente.', 'category' => 'konferenz', 'school' => 'gesamt', 'location' => 'Aula Hinterbirch', 'starts_at' => $now->addDays(4)->setTime(16, 45)->toAtomString(), 'ends_at' => $now->addDays(4)->setTime(18, 30)->toAtomString(), 'all_day' => false, 'created_by' => $ids['schulleitung']],
            ['title' => 'Weiterbildung: KI im Unterricht', 'description' => 'Interne Weiterbildung, Anmeldung nicht nötig.', 'category' => 'weiterbildung', 'school' => 'gesamt', 'location' => 'Zimmer 104', 'starts_at' => $now->addDays(9)->setTime(14, 0)->toAtomString(), 'ends_at' => $now->addDays(9)->setTime(17, 0)->toAtomString(), 'all_day' => false, 'created_by' => $ids['admin']],
            ['title' => 'Sporttag', 'description' => 'Verschiebedatum eine Woche später.', 'category' => 'schulanlass', 'school' => 'gesamt', 'location' => 'Sportanlage', 'starts_at' => $now->addDays(15)->startOfDay()->toAtomString(), 'ends_at' => null, 'all_day' => true, 'created_by' => $ids['redaktion']],
            ['title' => 'Elternabend 1. Klassen', 'description' => null, 'category' => 'schulanlass', 'school' => 'mettmenriet', 'location' => 'Singsaal', 'starts_at' => $now->addDays(21)->setTime(19, 30)->toAtomString(), 'ends_at' => $now->addDays(21)->setTime(21, 0)->toAtomString(), 'all_day' => false, 'created_by' => $ids['redaktion']],
            ['title' => 'Herbstferien', 'description' => null, 'category' => 'ferien', 'school' => 'gesamt', 'location' => null, 'starts_at' => $now->addDays(40)->startOfDay()->toAtomString(), 'ends_at' => $now->addDays(54)->startOfDay()->toAtomString(), 'all_day' => true, 'created_by' => $ids['redaktion']],
            ['title' => 'Teamsitzung Hinterbirch (vergangen)', 'description' => null, 'category' => 'termin', 'school' => 'hinterbirch', 'location' => 'Lehrerzimmer', 'starts_at' => $now->subDays(7)->setTime(12, 15)->toAtomString(), 'ends_at' => $now->subDays(7)->setTime(13, 0)->toAtomString(), 'all_day' => false, 'created_by' => $ids['schulleitung']],
        ]);

        $store->replace('documents', [
            ['title' => 'Schulprogramm (Beispiel-Link)', 'description' => 'Platzhalter-Link, durch echtes Dokument ersetzen.', 'category' => 'reglemente', 'kind' => 'link', 'url' => 'https://example.org/schulprogramm', 'file_path' => null, 'file_name' => null, 'file_size' => null, 'created_by' => $ids['schulleitung']],
            ['title' => 'Absenzenformular', 'description' => 'Formular für Urlaubsgesuche.', 'category' => 'formulare', 'kind' => 'link', 'url' => 'https://example.org/absenzen', 'file_path' => null, 'file_name' => null, 'file_size' => null, 'created_by' => $ids['redaktion']],
            ['title' => 'Microsoft 365 Login', 'description' => 'Webmail, Teams, OneDrive.', 'category' => 'links', 'kind' => 'link', 'url' => 'https://www.office.com', 'file_path' => null, 'file_name' => null, 'file_size' => null, 'created_by' => $ids['admin']],
            ['title' => 'Volksschulamt Kanton Zürich', 'description' => null, 'category' => 'links', 'kind' => 'link', 'url' => 'https://www.zh.ch/de/bildung.html', 'file_path' => null, 'file_name' => null, 'file_size' => null, 'created_by' => $ids['admin']],
            ['title' => 'iPad: WLAN einrichten', 'description' => 'Kurzanleitung für Lehrpersonen.', 'category' => 'it', 'kind' => 'link', 'url' => 'https://example.org/ipad-wlan', 'file_path' => null, 'file_name' => null, 'file_size' => null, 'created_by' => $ids['admin']],
        ]);

        $this->components->info('Demo-Daten angelegt in '.$store->path());
        $this->components->bulletList(array_map(
            fn (string $c): string => "{$c}: ".$store->count($c).' Datensätze',
            $collections,
        ));

        return self::SUCCESS;
    }
}
