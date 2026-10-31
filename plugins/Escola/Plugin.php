<?php

namespace Plugins\Escola;

use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\PluginContext;
use App\Plugins\Support\PeopleImport;
use App\Plugins\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;

/**
 * Escola ohne API: Import der Adressliste (Export aus der Escola-Personenverwaltung).
 *
 * Datenschutz: Die Adressliste enthält auch private und besonders schützenswerte
 * Angaben (Gesundheit, AHV-Nummer, Religion …). Gelesen werden nur die Spalten in
 * COLUMNS, alles andere wird sofort verworfen. Private Kontaktangaben nur mit Option. Die Datei selbst wird nicht gespeichert
 * und beim Übernehmen erneut mitgeschickt.
 */
class Plugin extends BasePlugin
{
    /** Einzige Spalten, die gelesen werden. */
    public const COLUMNS = [
        'aktiv/inaktiv', 'Name', 'Vorname', 'Title', 'Abkürzung',
        'E-Mail Geschäft', 'Telefon Geschäft', 'Telefon 2 Geschäft', 'Mobiltelefon Geschäft',
        'Strasse Geschäft', 'PLZ Geschäft', 'Ort Geschäft',
        'Funktion', 'Rollen', 'Abteilung (Firma/Schule)', 'Eintritt', 'Austritt',
        'Escola-ID', 'Fremd-ID', 'Benutzername',
        ...self::PRIVATE_COLUMNS,
    ];

    /** Nur mit Option «Private Angaben übernehmen». */
    public const PRIVATE_COLUMNS = [
        'E-Mail privat', 'Telefon', 'Mobiltelefon', 'Strasse', 'PLZ', 'Ort',
        'Notfall-Telefon: Person', 'Notfall-Telefon: Nummer',
    ];

    /** Rollen, die nicht zum Personal der Schule gehören. */
    private const AUTHORITY_ROLES = ['Schulbehörden-Mitglieder', 'RPK'];

    private const MAX_KB = 5120;

    public function test(PluginContext $ctx): array
    {
        return ['ok' => true, 'message' => 'Keine Verbindung nötig – Import per Datei (Aktion unten).'];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'import-file' => $this->importFile($ctx, $options),
            default => parent::action($action, $ctx, $options),
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function importFile(PluginContext $ctx, array $options): ActionResult
    {
        $file = $options['file'] ?? null;
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return ActionResult::warning('Bitte die Escola-Adressliste wählen (.xlsx oder .csv).');
        }
        if ($file->getSize() > self::MAX_KB * 1024) {
            return ActionResult::warning('Die Datei ist grösser als '.(self::MAX_KB / 1024).' MB.');
        }

        // Nur die nötigen Spalten lesen; private nur mit Option
        $includePrivate = ($options['include_private'] ?? false) === true;
        $columns = $includePrivate ? self::COLUMNS : array_values(array_diff(self::COLUMNS, self::PRIVATE_COLUMNS));
        $table = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalName(), $columns);
        $missing = array_values(array_diff(['Name', 'Vorname', 'E-Mail Geschäft', 'Rollen'], $table['header']));
        if ($missing !== []) {
            return ActionResult::warning('Das sieht nicht nach einer Escola-Adressliste aus. Es fehlen die Spalten: '.implode(', ', $missing).'.');
        }

        $includeAuthorities = ($options['include_authorities'] ?? false) === true;
        $people = [];
        $withoutEmail = 0;
        $authorities = 0;

        foreach ($table['rows'] as $row) {
            $roles = self::roles($row['Rollen'] ?? '');

            if (! $includeAuthorities && $roles !== [] && array_diff($roles, self::AUTHORITY_ROLES) === []) {
                $authorities++;

                continue;
            }
            if (($row['E-Mail Geschäft'] ?? '') === '') {
                $withoutEmail++;

                continue;
            }

            $value = fn (string $column): ?string => ($row[$column] ?? '') !== '' ? $row[$column] : null;
            $person = [
                'title' => $value('Title'),
                'first_name' => $value('Vorname'),
                'last_name' => $value('Name'),
                'short' => $value('Abkürzung'),
                'email' => $row['E-Mail Geschäft'],
                'function' => $value('Funktion') ?? self::functionFromRoles($roles),
                'department' => $value('Abteilung (Firma/Schule)'),
                'escola_roles' => $value('Rollen'),
                'phone' => $value('Telefon Geschäft') ?? $value('Mobiltelefon Geschäft'),
                'phone2' => $value('Telefon 2 Geschäft'),
                'mobile' => $value('Mobiltelefon Geschäft'),
                'office_street' => $value('Strasse Geschäft'),
                'office_zip' => $value('PLZ Geschäft'),
                'office_city' => $value('Ort Geschäft'),
                'entry_date' => $value('Eintritt'),
                'exit_date' => $value('Austritt'),
                'escola_id' => $value('Escola-ID'),
                '_external_id' => $value('Escola-ID'),
                'external_id' => $value('Fremd-ID'),
                'username' => $value('Benutzername'),
                'school' => self::school($roles),
                'active' => mb_strtolower($row['aktiv/inaktiv'] ?? 'aktiv') !== 'inaktiv',
            ];

            if ($includePrivate) {
                $person += [
                    'private_email' => $value('E-Mail privat'),
                    'private_phone' => $value('Telefon'),
                    'private_mobile' => $value('Mobiltelefon'),
                    'private_street' => $value('Strasse'),
                    'private_zip' => $value('PLZ'),
                    'private_city' => $value('Ort'),
                    'emergency_name' => $value('Notfall-Telefon: Person'),
                    'emergency_phone' => $value('Notfall-Telefon: Nummer'),
                ];
            }

            $people[] = $person;
        }

        $ignored = count(array_diff($table['header'], $columns));
        $ctx->log(sprintf(
            '%d Zeilen gelesen, %d Spalten verworfen. Ausgelassen: %d ohne Geschäfts-E-Mail, %d Behörde/RPK.',
            count($table['rows']), $ignored, $withoutEmail, $authorities,
        ));

        $import = new PeopleImport($ctx->users(), 'Import '.$ctx->manifest->name(), 'escola');
        $rows = $import->plan($people, 'Escola');

        if (($options['_apply'] ?? false) === true) {
            $done = $import->apply($rows, array_values(array_map('strval', (array) ($options['_selected'] ?? []))));

            return ActionResult::ok("{$done['created']} Personen angelegt, {$done['updated']} geändert.");
        }

        $counts = array_count_values(array_column($rows, '_change'));
        $skipped = array_filter([
            $withoutEmail > 0 ? "{$withoutEmail} ohne Geschäfts-E-Mail" : null,
            $authorities > 0 ? "{$authorities} Behörde/RPK" : null,
        ]);

        return ActionResult::preview(
            sprintf('%d neu, %d mit Änderungen, %d gleich, %d nur im Intranet.', $counts['neu'] ?? 0, $counts['ändern'] ?? 0, $counts['gleich'] ?? 0, $counts['fehlt dort'] ?? 0)
                .($skipped !== [] ? ' Ausgelassen: '.implode(', ', $skipped).'.' : ''),
            PeopleImport::columns(),
            $rows,
        );
    }

    /**
     * @return list<string>
     */
    private static function roles(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $r): bool => $r !== ''));
    }

    /**
     * Schulhaus aus den Escola-Rollen («Lehrperson Hinterbirch», «Schulverwaltung» …).
     *
     * @param  list<string>  $roles
     */
    private static function school(array $roles): string
    {
        $text = implode(' ', $roles);
        $hinterbirch = str_contains($text, 'Hinterbirch');
        $mettmenriet = str_contains($text, 'Mettmenriet');

        return match (true) {
            $hinterbirch && ! $mettmenriet => 'hinterbirch',
            $mettmenriet && ! $hinterbirch => 'mettmenriet',
            ! $hinterbirch && str_contains($text, 'Schulverwaltung') => 'verwaltung',
            default => 'gesamt',
        };
    }

    /**
     * Funktion aus der ersten fachlichen Rolle, ohne Schulhaus und ohne Admin-Rollen.
     *
     * @param  list<string>  $roles
     */
    private static function functionFromRoles(array $roles): ?string
    {
        foreach ($roles as $role) {
            if (str_contains($role, 'dministrator')) {
                continue;
            }
            $clean = trim(str_replace(['Hinterbirch', 'Mettmenriet'], '', $role));
            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
    }
}
