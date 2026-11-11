<?php

namespace Plugins\Clicktime;

use App\Data\Contracts\UserRepository;
use App\Modules\ModuleRegistry;
use App\Plugins\ActionResult;
use App\Plugins\BasePlugin;
use App\Plugins\DemoResponder;
use App\Plugins\PluginContext;
use App\Plugins\PluginManager;
use App\Support\DocumentAccess;
use App\Support\Notifications;
use App\Support\PersonLinks;
use App\Support\PersonTypes;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Modules\Zeiterfassung\Buchungsarten;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use RuntimeException;

/**
 * ClickTime (Zeiterfassung, Schweiz), API V3. Meilenstein «ClickTime – Integration» (#27). Quelle: dev.clicktime.ch
 * (OpenAPI-Export der Postman-Sammlung, 05.11.2026).
 *
 * Anmeldung: OAuth 2 Client Credentials (Token 1 Stunde, zwischengespeichert in PluginContext). Basis-Adresse
 * …/api/v3/customers/{customer}. Filter als `filters=feld::OP::wert`, mehrfach (im Manifest «repeat», im API-Tester
 * mit «|» getrennt).
 *
 * Demo-Modus: erfundene Daten für alle Endpunkte. Ausserhalb der Schweiz immer an; mit «Endtest» gehen dort nur
 * Aufrufe ohne Personendaten an ClickTime (Endpunkte mit «personal_data» sperrt der Kern).
 *
 * Die eigene Zeiterfassung (Modul) funktioniert ohne dieses Plugin; ClickTime ergänzt sie (Variante A oder C, #27).
 */
class Plugin extends BasePlugin implements DemoResponder
{
    public const SYSTEM = 'clicktime';

    private const DEMO_CACHE = 'clicktime-demo:';

    /** Erfundene Mitarbeitende (E-Mails wie die Demo-Personen des Intranets, Nr. 5 ohne Treffer). */
    private const DEMO_EMPLOYEES = [
        ['employee_no' => 1, 'first_name' => 'Andrea', 'last_name' => 'Muster', 'email' => 'andrea.muster@sekbuelach.example', 'department_number' => '30', 'active' => true],
        ['employee_no' => 2, 'first_name' => 'Beat', 'last_name' => 'Beispiel', 'email' => 'beat.beispiel@sekbuelach.example', 'department_number' => '10', 'active' => true],
        ['employee_no' => 3, 'first_name' => 'Daniel', 'last_name' => 'Probe', 'email' => 'daniel.probe@sekbuelach.example', 'department_number' => '20', 'active' => true],
        ['employee_no' => 4, 'first_name' => 'Eva', 'last_name' => 'Fiktiv', 'email' => 'eva.fiktiv@sekbuelach.example', 'department_number' => '40', 'active' => true],
        ['employee_no' => 5, 'first_name' => 'Zora', 'last_name' => 'Unbekannt', 'email' => 'zora.unbekannt@sekbuelach.example', 'department_number' => '20', 'active' => true],
    ];

    private const DEMO_DEPARTMENTS = [
        ['number' => '10', 'name' => 'Schulleitung', 'managers' => [2]],
        ['number' => '20', 'name' => 'Lehrpersonen Hinterbirch', 'managers' => [2]],
        ['number' => '30', 'name' => 'Schulverwaltung', 'managers' => [1]],
        ['number' => '40', 'name' => 'Lehrpersonen Mettmenriet', 'managers' => [2]],
    ];

    public function demoActive(PluginContext $ctx): bool
    {
        if ($ctx->setting('demo', true) !== false) {
            return true;
        }

        return ! self::swissServer() && $ctx->setting('endtest', false) !== true;
    }

    public function status(PluginContext $ctx): ?string
    {
        if ($ctx->setting('demo', true) !== false) {
            return 'Demo-Modus (erfundene Daten).';
        }
        if (! self::swissServer()) {
            return $ctx->setting('endtest', false) === true
                ? 'ENDTEST: echte Aufrufe an ClickTime, aber nur ohne Personendaten (Abteilungen, Feiertage, Projekte). Danach ausschalten.'
                : 'Demo-Modus (Server ausserhalb der Schweiz; für den Endtest ohne Personendaten «Endtest» einschalten).';
        }

        return null;
    }

    public function test(PluginContext $ctx): array
    {
        try {
            $response = $ctx->call('departments');
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if (! $response->successful()) {
            return ['ok' => false, 'message' => self::failure($response)];
        }

        $count = count(self::list($response));

        return ['ok' => true, 'message' => ($ctx->demo() ? 'Demo-Modus: ' : 'Verbunden mit ClickTime: ')."{$count} Abteilungen."];
    }

    public function action(string $action, PluginContext $ctx, array $options): ActionResult
    {
        return match ($action) {
            'departments' => $this->departments($ctx),
            'employees' => $this->employees($ctx),
            'match' => $this->match($ctx, $options),
            'person-mapping' => $this->personMapping($ctx, $options),
            'employee-create' => $this->employeeCreate($ctx, $options),
            'lookup' => $this->lookup($ctx, trim((string) ($options['email'] ?? ''))),
            'balances' => $this->balances($ctx, $options),
            'month' => $this->monthSheet($ctx, $options),
            'targets' => $this->targets($ctx, $options),
            'close' => $this->closeMonth($ctx, $options),
            'changes' => $this->changes($ctx, $options),
            'mapping' => $this->mapping($ctx, $options),
            'compare' => $this->compare($ctx, $options),
            'import' => $this->import($ctx, $options),
            default => parent::action($action, $ctx, $options),
        };
    }

    // ---------- Aktionen ----------

    private function departments(PluginContext $ctx): ActionResult
    {
        $rows = array_map(fn (array $d): array => [
            'number' => (string) ($d['number'] ?? ''),
            'name' => self::text($d['name'] ?? ''),
        ], self::list(self::expect($ctx->call('departments'))));

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').count($rows).' Abteilungen.', [
            ['key' => 'number', 'label' => 'Nummer'],
            ['key' => 'name', 'label' => 'Abteilung'],
        ], $rows);
    }

    private function employees(PluginContext $ctx): ActionResult
    {
        $departments = $this->departmentNames($ctx);
        $managers = $this->managersByDepartment($ctx, array_keys($departments));
        $links = app(PersonLinks::class);
        $names = app(UserRepository::class)->names();
        $rows = [];
        foreach ($this->allEmployees($ctx) as $e) {
            $no = (string) ($e['employee_no'] ?? '');
            $dept = (string) ($e['department_number'] ?? '');
            $person = $links->findPerson(self::SYSTEM, $no);
            $rows[] = [
                'no' => $no,
                'name' => self::fullName($e),
                'email' => (string) ($e['email'] ?? ''),
                'department' => $departments[$dept] ?? $dept,
                'managers' => implode(', ', $managers[$dept] ?? []),
                'person' => $person !== null ? ($names[$person] ?? $person) : '–',
            ];
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').count($rows).' Mitarbeitende.', [
            ['key' => 'no', 'label' => 'Nr.'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'email', 'label' => 'E-Mail'],
            ['key' => 'department', 'label' => 'Abteilung'],
            ['key' => 'managers', 'label' => 'Vorgesetzte'],
            ['key' => 'person', 'label' => 'Intranet-Person'],
        ], $rows);
    }

    /**
     * Mapping der Personen (#202): Intranet-Person (Name, ID) ↔ ClickTime (Name, Nr.) aus der Datenherkunft.
     * Anfangs nur Personen mit Zuordnung; mit «Alle anzeigen» auch ClickTime-Mitarbeitende ohne Intranet-Person und
     * Intranet-Personal ohne ClickTime. Es wird nichts geändert.
     *
     * @param  array<string, mixed>  $options
     */
    private function personMapping(PluginContext $ctx, array $options): ActionResult
    {
        $all = in_array($options['all'] ?? false, [true, 1, '1', 'true'], true);
        $people = [];
        foreach (app(UserRepository::class)->all() as $u) {
            $people[(string) $u['id']] = $u;
        }
        $employees = [];
        foreach ($this->allEmployees($ctx) as $e) {
            $no = (string) ($e['employee_no'] ?? '');
            if ($no !== '') {
                $employees[$no] = $e;
            }
        }

        $rows = [];
        $linkedPeople = [];
        $linkedNos = [];
        foreach (app(PersonLinks::class)->all() as $link) {
            if (($link['system'] ?? null) !== self::SYSTEM || ($link['direction'] ?? 'source') !== 'source') {
                continue;
            }
            $no = (string) ($link['external_id'] ?? '');
            $personId = (string) ($link['person_id'] ?? '');
            if ($no === '' || $personId === '') {
                continue;
            }
            $person = $people[$personId] ?? null;
            $employee = $employees[$no] ?? null;
            $rows[] = [
                'person' => $person !== null ? (string) ($person['name'] ?? '') : '–',
                'person_id' => $personId,
                'employee' => $employee !== null ? self::fullName($employee) : '–',
                'no' => $no,
                'status' => match (true) {
                    $person === null => 'Intranet-Person fehlt',
                    $employee === null => 'in ClickTime nicht gefunden',
                    default => 'zugeordnet',
                },
            ];
            $linkedPeople[$personId] = true;
            $linkedNos[$no] = true;
        }
        $linked = count($rows);

        if ($all) {
            foreach ($employees as $no => $employee) {
                if (! isset($linkedNos[(string) $no])) {
                    $rows[] = ['person' => '–', 'person_id' => '', 'employee' => self::fullName($employee), 'no' => (string) $no, 'status' => 'ohne Intranet-Person'];
                }
            }
            foreach ($people as $personId => $person) {
                $staff = in_array(PersonTypes::of($person), ['lehrperson', 'schulleitung', 'mitarbeitende'], true);
                if (isset($linkedPeople[$personId]) || ! $staff || ($person['active'] ?? true) === false) {
                    continue;
                }
                $rows[] = ['person' => (string) ($person['name'] ?? ''), 'person_id' => (string) $personId, 'employee' => '–', 'no' => '', 'status' => 'ohne ClickTime'];
            }
        }
        usort($rows, fn (array $a, array $b): int => strnatcasecmp($a['person'] !== '–' ? $a['person'] : $a['employee'], $b['person'] !== '–' ? $b['person'] : $b['employee']));

        $counts = array_count_values(array_column($rows, 'status'));
        $details = implode(', ', array_map(fn (string $k, int $n): string => "{$n} {$k}", array_keys($counts), $counts));

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '')."{$linked} Zuordnungen".($details !== '' ? " ({$details})" : '').'.'
            .($all ? '' : ' Häkchen «Alle anzeigen» zeigt auch Personen ohne Zuordnung.'), [
                ['key' => 'person', 'label' => 'Intranet-Person'],
                ['key' => 'person_id', 'label' => 'Intranet-ID'],
                ['key' => 'employee', 'label' => 'ClickTime-Person'],
                ['key' => 'no', 'label' => 'ClickTime-Nr.'],
                ['key' => 'status', 'label' => 'Status'],
            ], $rows);
    }

    /**
     * Abgleich (#30): zuerst über die Datenherkunft (System clicktime, ID = employee_no), sonst über die E-Mail.
     * Übernehmen speichert nur die Datenherkunft; es wird nichts angelegt, geändert oder gelöscht.
     *
     * @param  array<string, mixed>  $options
     */
    private function match(PluginContext $ctx, array $options): ActionResult
    {
        $links = app(PersonLinks::class);
        $users = app(UserRepository::class);
        $byEmail = [];
        foreach ($users->all() as $u) {
            $mail = mb_strtolower(trim((string) ($u['email'] ?? '')));
            if ($mail !== '') {
                $byEmail[$mail] = $u;
            }
        }
        $names = $users->names();

        $rows = [];
        foreach ($this->allEmployees($ctx) as $e) {
            $no = (string) ($e['employee_no'] ?? '');
            if ($no === '') {
                continue;
            }
            $email = mb_strtolower(trim((string) ($e['email'] ?? '')));
            $linked = $links->findPerson(self::SYSTEM, $no);
            $match = $email !== '' ? ($byEmail[$email] ?? null) : null;
            [$change, $person, $personId] = match (true) {
                $linked !== null => ['zugeordnet', $names[$linked] ?? $linked, $linked],
                $match !== null => ['zuordnen', (string) ($match['name'] ?? ''), (string) $match['id']],
                default => ['ohne Treffer', '–', null],
            };
            $rows[] = [
                '_key' => $no,
                '_change' => $change,
                '_person' => $personId,
                'no' => $no,
                'name' => self::fullName($e),
                'email' => (string) ($e['email'] ?? ''),
                'person' => $person,
                'status' => $change,
            ];
        }

        if (($options['_apply'] ?? false) === true) {
            $selected = array_map('strval', (array) ($options['_selected'] ?? []));
            $done = 0;
            foreach ($rows as $row) {
                if ($row['_change'] === 'zuordnen' && in_array($row['_key'], $selected, true) && is_string($row['_person'])) {
                    $links->recordSync($row['_person'], self::SYSTEM, 'source', $row['no']);
                    $done++;
                }
            }
            $ctx->log("{$done} Personen mit ClickTime verknüpft (Datenherkunft).");

            return ActionResult::ok("{$done} Personen mit ClickTime verknüpft (Datenherkunft). Angelegt oder gelöscht wurde nichts.");
        }

        $counts = array_count_values(array_column($rows, '_change'));
        $public = array_map(fn (array $r): array => array_diff_key($r, ['_person' => true]), $rows);

        return ActionResult::preview(
            sprintf('%s%d zugeordnet, %d neu zuzuordnen (gleiche E-Mail), %d ohne Treffer. Übernehmen speichert nur die Zuordnung.', $ctx->demo() ? 'Demo-Modus: ' : '', $counts['zugeordnet'] ?? 0, $counts['zuordnen'] ?? 0, $counts['ohne Treffer'] ?? 0),
            [
                ['key' => 'no', 'label' => 'Nr.'],
                ['key' => 'name', 'label' => 'Name in ClickTime'],
                ['key' => 'email', 'label' => 'E-Mail'],
                ['key' => 'person', 'label' => 'Intranet-Person'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $public,
        );
    }

    /**
     * Mitarbeitende Person in ClickTime anlegen (#31). Vorschau: Daten, die gesendet werden, und Dubletten-Prüfung
     * (Antwort ClickTime 09.11.2026: abgelehnt wird bei gleicher Personalnummer, gleichem Benutzernamen oder gleicher
     * Badge-Nummer; im Intranet zusätzlich gleiche E-Mail und bestehende Datenherkunft). Treffer → nur verbinden.
     * Übernehmen: POST /employees, employee_no aus der Antwort in der Datenherkunft speichern.
     *
     * @param  array<string, mixed>  $options
     */
    private function employeeCreate(PluginContext $ctx, array $options): ActionResult
    {
        $email = mb_strtolower(trim((string) ($options['person'] ?? '')));
        $person = null;
        foreach (app(UserRepository::class)->all() as $u) {
            if (mb_strtolower(trim((string) ($u['email'] ?? ''))) === $email && $email !== '') {
                $person = $u;
            }
        }
        if ($person === null) {
            return ActionResult::warning('Keine Person mit dieser E-Mail im Intranet.');
        }
        $links = app(PersonLinks::class);
        foreach ($links->all() as $link) {
            if (($link['person_id'] ?? null) === $person['id'] && ($link['system'] ?? null) === self::SYSTEM && (string) ($link['external_id'] ?? '') !== '') {
                return ActionResult::warning('Diese Person ist bereits mit ClickTime verbunden (Nr. '.(string) $link['external_id'].').');
            }
        }
        $first = trim((string) ($person['first_name'] ?? ''));
        $last = trim((string) ($person['last_name'] ?? ''));
        if ($first === '' || $last === '') {
            [$first, $last] = array_pad(explode(' ', trim((string) ($person['name'] ?? '')), 2), 2, '');
        }
        $payload = array_filter([
            'employee_no' => preg_match('/^\d+$/', trim((string) ($options['employee_no'] ?? ''))) === 1 ? (int) $options['employee_no'] : null,
            'first_name' => $first,
            'last_name' => $last,
            'email' => (string) $person['email'],
            'username' => trim((string) ($options['username'] ?? '')) !== '' ? trim((string) $options['username']) : (string) $person['email'],
            'batch_number' => trim((string) ($options['batch_number'] ?? '')) !== '' ? trim((string) $options['batch_number']) : null,
            'department_number' => trim((string) ($options['department'] ?? '')) !== '' ? trim((string) $options['department']) : null,
        ], fn ($v): bool => $v !== null && $v !== '');

        // Dubletten: gleiche Personalnummer, Benutzername, Badge-Nummer oder E-Mail
        $match = null;
        foreach ($this->allEmployees($ctx) as $e) {
            $same = fn (string $field): bool => isset($payload[$field]) && mb_strtolower(trim((string) ($e[$field] ?? ''))) === mb_strtolower((string) $payload[$field]);
            if ($same('employee_no') || $same('username') || $same('batch_number') || $same('email')) {
                $match = $e;
                break;
            }
        }
        $row = ['_key' => (string) $person['id'], 'person' => (string) ($person['name'] ?? ''), 'email' => (string) $person['email'],
            'username' => (string) ($payload['username'] ?? ''), 'employee_no' => isset($payload['employee_no']) ? (string) $payload['employee_no'] : 'von ClickTime',
            'status' => $match !== null ? 'verbinden mit Nr. '.(string) ($match['employee_no'] ?? '?').' ('.self::fullName($match).')' : 'neu anlegen'];

        if (($options['_apply'] ?? false) === true) {
            if ($match !== null) {
                $links->recordSync((string) $person['id'], self::SYSTEM, 'source', (string) ($match['employee_no'] ?? ''));
                $ctx->log('Mit bestehender ClickTime-Person verbunden (keine Dublette angelegt).');

                return ActionResult::ok('In ClickTime gibt es die Person schon (Nr. '.(string) ($match['employee_no'] ?? '?').') – nur verbunden, nichts angelegt.');
            }
            // ClickTime erwartet eine Liste von Mitarbeitenden
            $response = $ctx->call('employees-create', [], [$payload]);
            if (! $response->successful()) {
                $json = $response->json();
                $message = is_array($json) ? (string) ($json['message'] ?? $json['error'] ?? '') : '';

                return ActionResult::warning('ClickTime lehnt das Anlegen ab (HTTP '.$response->status().')'.($message !== '' ? ": {$message}" : '')
                    .' – Personalnummer, Benutzername oder Badge-Nummer schon vergeben? Schreibrechte freigeschaltet?');
            }
            $created = self::list($response)[0] ?? [];
            $no = (string) ($created['employee_no'] ?? '');
            if ($no === '') {
                return ActionResult::warning('Angelegt, aber die Antwort enthält keine Personalnummer – bitte mit «Mitarbeitende abgleichen» verbinden.');
            }
            $links->recordSync((string) $person['id'], self::SYSTEM, 'source', $no);
            $ctx->log("Person in ClickTime angelegt (Nr. {$no}).");

            return ActionResult::ok("In ClickTime angelegt: Nr. {$no}, in der Datenherkunft gespeichert.".($ctx->demo() ? ' (Demo-Modus)' : ''));
        }

        return ActionResult::preview(
            ($ctx->demo() ? 'Demo-Modus: ' : '').($match !== null
                ? 'Diese Person gibt es in ClickTime schon – Übernehmen verbindet nur (keine Dublette).'
                : 'Übernehmen legt die Person in ClickTime an und speichert die Personalnummer in der Datenherkunft. Arbeitsplan und Berechtigungen danach in ClickTime zuweisen.'),
            [
                ['key' => 'person', 'label' => 'Person'],
                ['key' => 'email', 'label' => 'E-Mail'],
                ['key' => 'username', 'label' => 'Benutzername'],
                ['key' => 'employee_no', 'label' => 'Personalnummer'],
                ['key' => 'status', 'label' => 'Aktion'],
            ],
            [$row],
        );
    }

    private function lookup(PluginContext $ctx, string $needle): ActionResult
    {
        if ($needle === '') {
            return ActionResult::warning('Bitte E-Mail oder ClickTime-Nummer angeben.');
        }
        $employee = null;
        foreach ($this->allEmployees($ctx) as $e) {
            if ((string) ($e['employee_no'] ?? '') === $needle || strcasecmp((string) ($e['email'] ?? ''), $needle) === 0) {
                $employee = $e;
            }
        }
        if ($employee === null) {
            return ActionResult::warning("«{$needle}» ist in ClickTime nicht zu finden.");
        }
        $no = (string) $employee['employee_no'];
        $dept = (string) ($employee['department_number'] ?? '');
        $departments = $this->departmentNames($ctx);
        $managers = $dept !== '' ? ($this->managersByDepartment($ctx, [$dept])[$dept] ?? []) : [];
        $employments = self::list($ctx->call('employee-employments', ['employee_no' => $no]));
        $roles = self::list($ctx->call('employee-roles', ['employee_no' => $no]));
        $groups = self::list($ctx->call('employee-permission-groups', ['employee_no' => $no]));

        $rows = [
            ['what' => 'ClickTime-Nr.', 'value' => $no],
            ['what' => 'Name', 'value' => self::fullName($employee)],
            ['what' => 'E-Mail', 'value' => (string) ($employee['email'] ?? '')],
            ['what' => 'Abteilung', 'value' => $departments[$dept] ?? $dept],
            ['what' => 'Vorgesetzte', 'value' => implode(', ', $managers) ?: '–'],
            ['what' => 'Anstellungen', 'value' => implode('; ', array_map(fn (array $a): string => self::summary($a), $employments)) ?: '–'],
            ['what' => 'Rollen', 'value' => implode(', ', array_map(fn (array $r): string => self::text($r['name'] ?? $r['number'] ?? ''), $roles)) ?: '–'],
            ['what' => 'Berechtigungsgruppen', 'value' => implode(', ', array_map(fn (array $g): string => self::text($g['name'] ?? $g['number'] ?? ''), $groups)) ?: '–'],
        ];

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').self::fullName($employee).' in ClickTime.', [
            ['key' => 'what', 'label' => 'Angabe'],
            ['key' => 'value', 'label' => 'Wert'],
        ], $rows);
    }

    // ---------- Salden und Monatsblätter (#32) ----------

    /**
     * @param  array<string, mixed>  $options
     */
    private function balances(PluginContext $ctx, array $options): ActionResult
    {
        $at = trim((string) ($options['date'] ?? '')) ?: date('Y-m-t', (int) strtotime('first day of last month'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $at) !== 1) {
            return ActionResult::warning('Stichtag als JJJJ-MM-TT angeben, z. B. 2026-10-31.');
        }
        $needle = trim((string) ($options['person'] ?? ''));
        $people = $this->people($ctx, $needle);
        $balances = (new Months($ctx))->balances($at, $needle !== '' ? array_map('strval', array_keys($people)) : []);
        $rows = [];
        foreach ($people as $no => $name) {
            foreach ($balances[$no] ?? [] as $b) {
                $rows[] = ['no' => $no, 'name' => $name, 'balance' => $b['name'], 'value' => Months::hours($b['value']).' '.$b['unit']];
            }
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '').'Salden am '.date('d.m.Y', (int) strtotime($at)).' für '.count($people).' Personen.', [
            ['key' => 'no', 'label' => 'Nr.'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'balance', 'label' => 'Saldo'],
            ['key' => 'value', 'label' => 'Wert'],
        ], $rows);
    }

    /**
     * Sollzeit pro Tag (#102): je Tag des Monats eine Abfrage der Kumulative (valid_from = valid_to = Tag), Sollzeit =
     * Buchungsart 0. Zur Kontrolle die Summe der Tage gegen die Sollzeit des ganzen Monats.
     *
     * @param  array<string, mixed>  $options
     */
    private function targets(PluginContext $ctx, array $options): ActionResult
    {
        $month = Months::month((string) ($options['month'] ?? ''));
        $needle = trim((string) ($options['person'] ?? ''));
        $people = $this->people($ctx, $needle);
        $filter = $needle !== '' ? array_map('strval', array_keys($people)) : [];
        $months = new Months($ctx);
        $holidays = $months->holidays((int) substr($month, 0, 4));
        $weekdays = ['SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA'];

        $targets = $months->targets($month, $filter);
        $rows = [];
        $sum = [];
        [$from, $to] = Months::range($month);
        for ($t = (int) strtotime($from); $t <= (int) strtotime($to); $t = (int) strtotime('+1 day', $t)) {
            $day = date('Y-m-d', $t);
            foreach ($people as $no => $name) {
                $target = $targets[(string) $no][$day] ?? null;
                $sum[$no] = ($sum[$no] ?? 0.0) + (float) $target;
                $rows[] = [
                    'no' => (string) $no,
                    'name' => $name,
                    'weekday' => $weekdays[(int) date('w', $t)],
                    'day' => date('d.m.', $t),
                    'target' => $target === null ? '–' : Months::hhmm($target),
                    'hours' => $target === null ? '–' : Months::hours($target),
                    'note' => $holidays[$day] ?? '',
                ];
            }
        }

        // Kontrolle: Summe der Tage = Sollzeit des Monats?
        $monthly = $months->cumulatives($month, $filter);
        $checks = [];
        foreach ($people as $no => $name) {
            $total = Months::target($monthly[(string) $no] ?? []);
            $ok = $total !== null && abs($total - $sum[$no]) < 0.01;
            $checks[] = "{$name}: Summe Tage ".Months::hhmm($sum[$no]).', Monat '.($total === null ? '–' : Months::hhmm($total)).($ok ? ' ✓' : ' – weicht ab');
        }
        $days = (int) date('t', (int) strtotime($from));

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '')."Sollzeit pro Tag {$month} ({$days} Abfragen). ".implode('; ', $checks).'.', [
            ['key' => 'no', 'label' => 'Nr.'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'weekday', 'label' => 'Tag'],
            ['key' => 'day', 'label' => 'Datum'],
            ['key' => 'target', 'label' => 'Soll (HH:MM)'],
            ['key' => 'hours', 'label' => 'Soll (h)'],
            ['key' => 'note', 'label' => 'Feiertag'],
        ], $rows);
    }

    /**
     * Monatsblatt: für eine Person die Tage mit Buchungen, Lücken und Feiertagen; für alle eine Übersicht. Mit «pdf»
     * wird je Person ein Monatsblatt in «Dokumente & Links» abgelegt (nur Verwaltung sichtbar).
     *
     * @param  array<string, mixed>  $options
     */
    private function monthSheet(PluginContext $ctx, array $options): ActionResult
    {
        $month = Months::month((string) ($options['month'] ?? ''));
        $needle = trim((string) ($options['person'] ?? ''));
        $people = $this->people($ctx, $needle);
        $filter = $needle !== '' ? array_map('strval', array_keys($people)) : [];
        $months = new Months($ctx);
        $entries = $months->entries($month, $filter);
        $cumulatives = $months->cumulatives($month, $filter);
        $balances = $months->balances(Months::range($month)[1], $filter);
        $holidays = $months->holidays((int) substr($month, 0, 4));

        $stored = 0;
        if (($options['pdf'] ?? false) === true) {
            $store = app(DocumentAccess::class);
            foreach ($people as $no => $name) {
                $source = "clicktime:monatsblatt:{$month}:{$no}";
                if ($store->bySource($source) !== null) {
                    continue;
                }
                $pdf = Months::pdf($month, $name, $no, $entries[$no] ?? [], $cumulatives[$no] ?? [], $balances[$no] ?? [], $holidays, Months::gaps($month, $entries[$no] ?? [], $holidays));
                $store->store('Monatsblatt '.date('m.Y', (int) strtotime($month.'-01')).' – '.$name, "monatsblatt-{$month}-{$no}.pdf", $pdf, 'monatsblaetter', 'manage-signatures', $source, 'Aus ClickTime.', $ctx->user);
                $stored++;
            }
        }
        $suffix = $stored > 0 ? " {$stored} Monatsblätter in «Dokumente & Links» abgelegt." : '';

        if (count($people) === 1) {
            $no = (string) array_key_first($people);
            $gaps = Months::gaps($month, $entries[$no] ?? [], $holidays);
            $rows = [];
            $byDay = [];
            foreach ($entries[$no] ?? [] as $e) {
                $byDay[(string) $e['day']][] = $e;
            }
            [$from, $to] = Months::range($month);
            for ($t = (int) strtotime($from); $t <= (int) strtotime($to); $t = (int) strtotime('+1 day', $t)) {
                $day = date('Y-m-d', $t);
                $note = $holidays[$day] ?? (in_array($day, $gaps, true) ? 'keine Buchung' : '');
                foreach ($byDay[$day] ?? [[]] as $e) {
                    if ($e === [] && $note === '') {
                        continue;
                    }
                    $rows[] = [
                        'day' => date('d.m.Y', $t),
                        'time' => ($e['time_from'] ?? '') !== '' ? $e['time_from'].'–'.$e['time_to'] : '',
                        'hours' => $e !== [] ? Months::hours((float) $e['hours']) : '',
                        'type' => (string) ($e['type_name'] ?? $e['type'] ?? ''),
                        'note' => $note,
                    ];
                }
            }
            $sums = implode(', ', array_map(fn (array $c): string => $c['name'].' '.Months::hours($c['hours']).' h', $cumulatives[$no] ?? []));
            $saldo = implode(', ', array_map(fn (array $b): string => $b['name'].' '.Months::hours($b['value']).' '.$b['unit'], $balances[$no] ?? []));

            return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '')."Monatsblatt {$month} – {$people[$no]}: ".count($gaps).' Arbeitstage ohne Buchung. '
                .($sums !== '' ? "Summen: {$sums}. " : '').($saldo !== '' ? "Salden: {$saldo}." : '').$suffix, [
                    ['key' => 'day', 'label' => 'Tag'],
                    ['key' => 'time', 'label' => 'Zeit'],
                    ['key' => 'hours', 'label' => 'Stunden'],
                    ['key' => 'type', 'label' => 'Art'],
                    ['key' => 'note', 'label' => 'Hinweis'],
                ], $rows);
        }

        $rows = [];
        foreach ($people as $no => $name) {
            $c = $cumulatives[$no] ?? [];
            $rows[] = [
                'no' => $no,
                'name' => $name,
                'hours' => Months::hours(array_sum(array_map(fn (array $e): float => (float) $e['hours'], $entries[$no] ?? []))),
                'target' => isset($c[0]) ? Months::hours($c[0]['hours']) : '–',
                'balances' => implode(', ', array_map(fn (array $b): string => $b['name'].' '.Months::hours($b['value']).' '.$b['unit'], $balances[$no] ?? [])),
                'gaps' => (string) count(Months::gaps($month, $entries[$no] ?? [], $holidays)),
            ];
        }

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '')."Monat {$month}: ".count($rows).' Personen.'.$suffix, [
            ['key' => 'no', 'label' => 'Nr.'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'hours', 'label' => 'Gebucht (h)'],
            ['key' => 'target', 'label' => 'Soll (h)'],
            ['key' => 'balances', 'label' => 'Salden Monatsende'],
            ['key' => 'gaps', 'label' => 'Tage ohne Buchung'],
        ], $rows);
    }

    // ---------- Monatsabschluss und Änderungen (#97) ----------

    /**
     * Stand der Buchungen eines Monats festhalten (Monatsabschluss im Intranet). Ein erneuter Abschluss ersetzt den Stand.
     *
     * @param  array<string, mixed>  $options
     */
    private function closeMonth(PluginContext $ctx, array $options): ActionResult
    {
        $month = Months::month((string) ($options['month'] ?? ''));
        $people = $this->people($ctx, '');
        $entries = (new Months($ctx))->entries($month);
        $before = Months::closing($month);
        Months::close($month, $entries, $ctx->user !== null ? (string) $ctx->user->getAuthIdentifier() : null);
        if ($ctx->demo()) {
            // Demo: nach dem Abschluss simuliert die Demo eine Korrektur (geändert, nachgetragen, gelöscht)
            Cache::put(self::DEMO_CACHE.'closed:'.$month, true, now()->addDays(7));
        }
        $ctx->log("Monat {$month} abgeschlossen: ".array_sum(array_map('count', $entries)).' Buchungen festgehalten.');

        $rows = array_map(fn (string $no): array => [
            'no' => $no,
            'name' => $people[$no] ?? $no,
            'entries' => (string) count($entries[$no] ?? []),
            'hours' => Months::hours(array_sum(array_map(fn (array $e): float => (float) $e['hours'], $entries[$no] ?? []))),
        ], array_keys($entries));

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '')."Monat {$month} abgeschlossen".($before !== null ? ' (früherer Abschluss ersetzt)' : '')
            .'. Änderungen danach zeigt «Änderungen seit Abschluss».', [
                ['key' => 'no', 'label' => 'Nr.'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'entries', 'label' => 'Buchungen'],
                ['key' => 'hours', 'label' => 'Stunden'],
            ], $rows);
    }

    /**
     * Änderungen seit dem Abschluss: heutiger Stand gegen den festgehaltenen (neu, geändert, gelöscht). Optional
     * Hinweis an die Verwaltung (Recht «Unterschriften verwalten») und die Vorgesetzten der betroffenen Personen.
     *
     * @param  array<string, mixed>  $options
     */
    private function changes(PluginContext $ctx, array $options): ActionResult
    {
        $month = Months::month((string) ($options['month'] ?? ''));
        $closing = Months::closing($month);
        if ($closing === null) {
            return ActionResult::warning("Monat {$month} ist noch nicht abgeschlossen (Aktion «Monat abschliessen»).");
        }
        $people = $this->people($ctx, '');
        $changes = Months::diff($closing['employees'], (new Months($ctx))->entries($month));
        $closedAt = date('d.m.Y H:i', (int) strtotime($closing['closed_at']));
        if ($changes === []) {
            return ActionResult::ok(($ctx->demo() ? 'Demo-Modus: ' : '')."Monat {$month}: keine Änderungen seit dem Abschluss am {$closedAt}.");
        }

        $notified = 0;
        if (($options['notify'] ?? false) === true) {
            $affected = array_values(array_unique(array_column($changes, 'employee_no')));
            $names = implode(', ', array_map(fn (string $no): string => $people[$no] ?? $no, $affected));
            $title = "ClickTime: Änderungen nach Abschluss {$month}";
            $body = count($changes)." Buchungen wurden nach dem Abschluss am {$closedAt} geändert ({$names}).";
            $notifications = app(Notifications::class);
            $actor = $ctx->user !== null ? (string) $ctx->user->getAuthIdentifier() : null;
            $notified += $notifications->toAbility('manage-signatures', 'clicktime.changes', $title, $body, '/plugins/clicktime', $actor);
            $notified += $notifications->toUsers($this->managerPersons($ctx, $affected), 'clicktime.changes', $title, $body, '/plugins/clicktime', $actor);
        }

        $rows = array_map(fn (array $c): array => [
            'no' => $c['employee_no'],
            'name' => $people[$c['employee_no']] ?? $c['employee_no'],
            'day' => date('d.m.Y', (int) strtotime($c['day'])),
            'change' => $c['change'],
            'before' => $c['before'],
            'after' => $c['after'],
        ], $changes);
        $counts = array_count_values(array_column($changes, 'change'));

        return ActionResult::table(($ctx->demo() ? 'Demo-Modus: ' : '')."Monat {$month}, abgeschlossen am {$closedAt}: "
            .($counts['neu'] ?? 0).' neu, '.($counts['geändert'] ?? 0).' geändert, '.($counts['gelöscht'] ?? 0).' gelöscht.'
            .($notified > 0 ? " Hinweis an {$notified} Personen." : ''), [
                ['key' => 'no', 'label' => 'Nr.'],
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'day', 'label' => 'Tag'],
                ['key' => 'change', 'label' => 'Änderung'],
                ['key' => 'before', 'label' => 'Beim Abschluss'],
                ['key' => 'after', 'label' => 'Heute'],
            ], $rows);
    }

    /**
     * Personen: eine (E-Mail oder Nummer) oder alle; Nr. → Name.
     *
     * @return array<string, string>
     */
    private function people(PluginContext $ctx, string $needle): array
    {
        $people = [];
        foreach ($this->allEmployees($ctx) as $e) {
            $no = (string) ($e['employee_no'] ?? '');
            if ($no !== '' && ($needle === '' || $no === $needle || strcasecmp((string) ($e['email'] ?? ''), $needle) === 0)) {
                $people[$no] = self::fullName($e);
            }
        }
        if ($people === []) {
            throw new RuntimeException($needle !== '' ? "«{$needle}» ist in ClickTime nicht zu finden." : 'ClickTime liefert keine Mitarbeitenden.');
        }

        return $people;
    }

    /**
     * Intranet-Personen der Vorgesetzten (über die Abteilungen und die Datenherkunft).
     *
     * @param  list<string>  $employees
     * @return list<string>
     */
    private function managerPersons(PluginContext $ctx, array $employees): array
    {
        $departments = [];
        foreach ($this->allEmployees($ctx) as $e) {
            if (in_array((string) ($e['employee_no'] ?? ''), $employees, true) && (string) ($e['department_number'] ?? '') !== '') {
                $departments[(string) $e['department_number']] = true;
            }
        }
        $links = app(PersonLinks::class);
        $ids = [];
        foreach (array_keys($departments) as $number) {
            $response = $ctx->call('department-managers', ['department' => (string) $number]);
            foreach ($response->successful() ? self::list($response) : [] as $m) {
                $id = $links->findPerson(self::SYSTEM, (string) ($m['employee_no'] ?? ''));
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    // ---------- Abgleich mit der eigenen Zeiterfassung (#100) ----------

    /**
     * Buchungsarten der Zeiterfassung (Modul), leer wenn das Modul aus ist.
     *
     * @return array<string, array{name: string, art: string, aktiv: bool}>
     */
    private static function ownTypes(): array
    {
        if (! class_exists(Buchungsarten::class) || ! app(ModuleRegistry::class)->enabled('zeiterfassung')) {
            return [];
        }

        return app(Buchungsarten::class)->liste();
    }

    /**
     * @return array<string, array{name: string, target: string}>
     */
    private static function mappingOf(PluginContext $ctx): array
    {
        return Mapping::parse((string) $ctx->setting('mapping', Mapping::DEFAULT));
    }

    /**
     * Mapping-Tabelle prüfen und ergänzen: alle Buchungsarten aus ClickTime (Kumulative der letzten 12 Monate, mit
     * Kategorie aus den Buchungen des Vormonats), aktuelle Zuordnung und Vorschlag. Übernehmen schreibt die gewählten
     * Vorschläge in die Einstellung «Zuordnung der Buchungsarten».
     *
     * @param  array<string, mixed>  $options
     */
    private function mapping(PluginContext $ctx, array $options): ActionResult
    {
        $own = self::ownTypes();
        if ($own === []) {
            return ActionResult::warning('Das Modul Zeiterfassung ist ausgeschaltet – ohne seine Buchungsarten gibt es nichts zuzuordnen.');
        }
        $names = [];
        $from = date('Y-m-01', (int) strtotime('first day of -11 months'));
        $to = date('Y-m-t');
        foreach (self::list(self::expect($ctx->call('cumulatives', ['filters' => ["valid_from::EQ::{$from}", "valid_to::EQ::{$to}"]]))) as $row) {
            foreach (is_array($row['cumulative_values'] ?? null) ? $row['cumulative_values'] : [] as $v) {
                $name = self::text($v['entry_type_name'] ?? '');
                if (is_array($v) && $name !== '') {
                    $names[Mapping::key($name)] ??= ['name' => $name, 'number' => $v['entry_type_number'] ?? null, 'category' => null];
                }
            }
        }
        $month = Months::month('');
        [$mFrom, $mTo] = Months::range($month);
        foreach (self::list($ctx->call('entries', ['filters' => ["day::GE::{$mFrom}", "day::LE::{$mTo}"]])) as $e) {
            $name = self::text($e['entry_type_name'] ?? '');
            if ($name !== '') {
                $names[Mapping::key($name)] ??= ['name' => $name, 'number' => $e['entry_type_number'] ?? null, 'category' => null];
                $names[Mapping::key($name)]['category'] ??= is_string($e['entry_type_type'] ?? null) ? $e['entry_type_type'] : null;
            }
        }

        $mapping = self::mappingOf($ctx);
        $label = fn (?string $t): string => match (true) {
            $t === null || $t === '' => '–',
            $t === Mapping::PAUSE => 'Pause des Tages',
            $t === Mapping::SKIP => 'nicht übernehmen',
            isset($own[$t]) => $own[$t]['name'],
            default => "«{$t}» (unbekannt)",
        };
        $rows = [];
        foreach ($names as $key => $n) {
            $current = $mapping[$key]['target'] ?? null;
            $valid = $current !== null && ($current === Mapping::PAUSE || $current === Mapping::SKIP || isset($own[$current]));
            $suggest = $valid ? $current : Mapping::suggest($n['name'], $n['category'], $own);
            $rows[] = [
                '_key' => $key,
                '_change' => $valid ? 'gleich' : ($suggest !== null ? 'neu' : 'offen'),
                '_target' => $suggest,
                'name' => $n['name'],
                'category' => (string) ($n['category'] ?? ''),
                'current' => $label($current),
                'suggest' => $label($suggest),
                'status' => $valid ? 'zugeordnet' : ($suggest !== null ? 'Vorschlag' : 'von Hand zuordnen'),
            ];
        }

        if (($options['_apply'] ?? false) === true) {
            $selected = array_map('strval', (array) ($options['_selected'] ?? []));
            $done = 0;
            foreach ($rows as $r) {
                if ($r['_change'] === 'neu' && in_array($r['_key'], $selected, true) && is_string($r['_target'])) {
                    $mapping[$r['_key']] = ['name' => $r['name'], 'target' => $r['_target']];
                    $done++;
                }
            }
            $manager = app(PluginManager::class);
            $manager->saveSettings($ctx->manifest->id(), ['mapping' => Mapping::format($mapping)] + $manager->state($ctx->manifest->id())['settings']);

            return ActionResult::ok("{$done} Buchungsarten zugeordnet. Die Zuordnung lässt sich in den Einstellungen von Hand ändern.");
        }

        $counts = array_count_values(array_column($rows, 'status'));

        return ActionResult::preview(
            ($ctx->demo() ? 'Demo-Modus: ' : '').count($rows).' Buchungsarten in ClickTime: '.($counts['zugeordnet'] ?? 0).' zugeordnet, '
                .($counts['Vorschlag'] ?? 0).' mit Vorschlag, '.($counts['von Hand zuordnen'] ?? 0).' von Hand zuordnen (Einstellungen).',
            [
                ['key' => 'name', 'label' => 'Buchungsart ClickTime'],
                ['key' => 'category', 'label' => 'Kategorie'],
                ['key' => 'current', 'label' => 'Zuordnung heute'],
                ['key' => 'suggest', 'label' => 'Vorschlag'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            array_map(fn (array $r): array => array_diff_key($r, ['_target' => true]), $rows),
        );
    }

    /**
     * Vergleich je Person und Tag: Stunden je Buchungsart in ClickTime (über die Mapping-Tabelle) gegen die eigene
     * Zeiterfassung. Pause wird von der Arbeit des Tages abgezogen. Nichts wird geändert.
     *
     * @param  array<string, mixed>  $options
     */
    private function compare(PluginContext $ctx, array $options): ActionResult
    {
        $own = self::ownTypes();
        if ($own === []) {
            return ActionResult::warning('Das Modul Zeiterfassung ist ausgeschaltet.');
        }
        $month = Months::month((string) ($options['month'] ?? ''));
        $needle = trim((string) ($options['person'] ?? ''));
        $people = $this->people($ctx, $needle);
        $links = app(PersonLinks::class);
        $mapping = self::mappingOf($ctx);
        $entries = (new Months($ctx))->entries($month, $needle !== '' ? array_map('strval', array_keys($people)) : []);
        $repo = app(EintragRepository::class);

        $rows = [];
        $unmapped = [];
        $counts = ['gleich' => 0, 'Abweichung' => 0, 'nur ClickTime' => 0, 'nur Zeiterfassung' => 0];
        $withoutPerson = [];
        foreach ($people as $no => $name) {
            $personId = $links->findPerson(self::SYSTEM, (string) $no);
            if ($personId === null) {
                $withoutPerson[] = $name;

                continue;
            }
            // ClickTime: Tag → Buchungsart → Stunden
            $ct = [];
            $pause = [];
            foreach ($entries[(string) $no] ?? [] as $e) {
                $target = Mapping::target($mapping, (string) $e['type'], (string) $e['type_name']);
                if ($target === null || ($target !== Mapping::PAUSE && $target !== Mapping::SKIP && ! isset($own[$target]))) {
                    $unmapped[(string) $e['type_name']] = true;
                    $target = '?'.$e['type_name'];
                }
                if ($target === Mapping::SKIP) {
                    continue;
                }
                if ($target === Mapping::PAUSE) {
                    $pause[$e['day']] = ($pause[$e['day']] ?? 0) + (float) $e['hours'];

                    continue;
                }
                $ct[$e['day']][$target] = ($ct[$e['day']][$target] ?? 0) + (float) $e['hours'];
            }
            foreach ($pause as $day => $h) {
                // Pause (negativ) von der grössten Arbeit-Buchungsart des Tages abziehen
                $work = array_filter($ct[$day] ?? [], fn (string $k): bool => ($own[$k]['art'] ?? '') === 'arbeit', ARRAY_FILTER_USE_KEY);
                arsort($work);
                $key = (string) (array_key_first($work) ?? 'arbeitszeit');
                $ct[$day][$key] = ($ct[$day][$key] ?? 0) + $h;
            }
            // Zeiterfassung
            $mine = [];
            foreach ($repo->fuerPersonMonat($personId, $month) as $e) {
                $b = Buchungsarten::von($e);
                $mine[(string) $e['datum']][$b] = ($mine[(string) $e['datum']][$b] ?? 0) + (float) $e['stunden'];
            }
            $days = array_unique([...array_keys($ct), ...array_keys($mine)]);
            sort($days);
            foreach ($days as $day) {
                $a = array_map(fn (float $h): float => round($h, 2), $ct[$day] ?? []);
                $b = array_map(fn (float $h): float => round($h, 2), $mine[$day] ?? []);
                $status = match (true) {
                    $b === [] => 'nur ClickTime',
                    $a === [] => 'nur Zeiterfassung',
                    self::sameHours($a, $b) => 'gleich',
                    default => 'Abweichung',
                };
                $counts[$status]++;
                if ($status === 'gleich' && ($options['all'] ?? false) !== true) {
                    continue;
                }
                $describe = fn (array $x): string => implode(', ', array_map(fn (string $k, float $h): string => (str_starts_with($k, '?') ? substr($k, 1).' (nicht zugeordnet)' : ($own[$k]['name'] ?? $k)).' '.Months::hours($h), array_keys($x), $x)) ?: '–';
                $rows[] = ['no' => (string) $no, 'name' => $name, 'day' => date('d.m.Y', (int) strtotime($day)), 'clicktime' => $describe($a), 'own' => $describe($b), 'status' => $status];
            }
        }

        $message = ($ctx->demo() ? 'Demo-Modus: ' : '')."Monat {$month}: {$counts['gleich']} Tage gleich, {$counts['Abweichung']} mit Abweichung, "
            ."{$counts['nur ClickTime']} nur in ClickTime, {$counts['nur Zeiterfassung']} nur in der Zeiterfassung.";
        if ($unmapped !== []) {
            $message .= ' Nicht zugeordnet: '.implode(', ', array_keys($unmapped)).' (Aktion «Buchungsarten zuordnen»).';
        }
        if ($withoutPerson !== []) {
            $message .= ' Ohne Intranet-Person (zuerst «Mitarbeitende abgleichen»): '.implode(', ', $withoutPerson).'.';
        }

        return ActionResult::table($message, [
            ['key' => 'no', 'label' => 'Nr.'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'day', 'label' => 'Tag'],
            ['key' => 'clicktime', 'label' => 'ClickTime'],
            ['key' => 'own', 'label' => 'Zeiterfassung'],
            ['key' => 'status', 'label' => 'Status'],
        ], $rows);
    }

    /**
     * @param  array<string, float>  $a
     * @param  array<string, float>  $b
     */
    private static function sameHours(array $a, array $b): bool
    {
        foreach (array_unique([...array_keys($a), ...array_keys($b)]) as $k) {
            if (abs(($a[$k] ?? 0) - ($b[$k] ?? 0)) > 0.02) {
                return false;
            }
        }

        return true;
    }

    // ---------- Übernehmen in die Zeiterfassung (#101) ----------

    /**
     * Buchungen eines Monats einer Person in die eigene Zeiterfassung übernehmen; ohne «apply» nur Vorschau.
     *
     * @param  array<string, mixed>  $options
     */
    private function import(PluginContext $ctx, array $options): ActionResult
    {
        if (self::ownTypes() === []) {
            return ActionResult::warning('Das Modul Zeiterfassung ist ausgeschaltet.');
        }
        $month = Months::month((string) ($options['month'] ?? ''));
        $needle = trim((string) ($options['person'] ?? ''));
        if ($needle === '') {
            return ActionResult::warning('Bitte eine Person angeben (E-Mail oder ClickTime-Nummer).');
        }
        $people = $this->people($ctx, $needle);
        $no = (string) array_key_first($people);
        $personId = app(PersonLinks::class)->findPerson(self::SYSTEM, $no);
        if ($personId === null) {
            return ActionResult::warning("{$people[$no]} (ClickTime-Nr. {$no}) ist keiner Intranet-Person zugeordnet – zuerst «Mitarbeitende abgleichen» oder die Datenherkunft bei der Person eintragen.");
        }
        $apply = ($options['apply'] ?? false) === true;
        $result = (new Importer($ctx))->run($personId, $no, $month, $apply);

        return ActionResult::table(self::importMessage($ctx, $people[$no], $month, $result, $apply), [
            ['key' => 'day', 'label' => 'Tag'],
            ['key' => 'change', 'label' => 'Änderung'],
            ['key' => 'before', 'label' => 'Zeiterfassung bisher'],
            ['key' => 'after', 'label' => 'Aus ClickTime'],
        ], array_map(fn (array $c): array => ['day' => date('d.m.Y', (int) strtotime($c['day']))] + $c, $result['changes']));
    }

    /**
     * @param  array{changes: list<array<string, string>>, counts: array<string, int>, created_types: list<string>, targets?: array{days: int, hours: float}}  $result
     */
    public static function importMessage(PluginContext $ctx, string $name, string $month, array $result, bool $apply): string
    {
        $c = $result['counts'];
        $message = ($ctx->demo() ? 'Demo-Modus: ' : '').($apply ? 'Übernommen' : 'Vorschau')." {$month} – {$name}: {$c['neu']} neu, {$c['geändert']} geändert, "
            ."{$c['gelöscht']} gelöscht, {$c['unverändert']} unverändert.";
        if ($result['created_types'] !== []) {
            $message .= ' Neue Buchungsarten'.($apply ? '' : ' (beim Übernehmen)').': '.implode(', ', array_map(fn (string $n): string => "{$n} (clicktime)", $result['created_types'])).'.';
        }

        if (($result['targets']['days'] ?? 0) > 0) {
            $message .= ' Sollzeit: '.$result['targets']['days'].' Tage, '.Months::hhmm($result['targets']['hours']).($apply ? ' gespeichert.' : ' (beim Übernehmen).');
        }

        return $message.($apply ? '' : ' Zum Übernehmen «Übernehmen» ankreuzen.');
    }

    // ---------- Daten ----------

    /**
     * @return list<array<string, mixed>>
     */
    private function allEmployees(PluginContext $ctx): array
    {
        return self::list(self::expect($ctx->call('employees')));
    }

    /**
     * @return array<string, string> Nummer → Name
     */
    private function departmentNames(PluginContext $ctx): array
    {
        $names = [];
        foreach (self::list(self::expect($ctx->call('departments'))) as $d) {
            $names[(string) ($d['number'] ?? '')] = self::text($d['name'] ?? '');
        }

        return $names;
    }

    /**
     * @param  list<string>  $numbers
     * @return array<string, list<string>> Abteilung → Namen der Vorgesetzten
     */
    private function managersByDepartment(PluginContext $ctx, array $numbers): array
    {
        $result = [];
        foreach ($numbers as $number) {
            if ($number === '') {
                continue;
            }
            $response = $ctx->call('department-managers', ['department' => $number]);
            if ($response->successful()) {
                $result[$number] = array_map(fn (array $m): string => self::fullName($m), self::list($response));
            }
        }

        return $result;
    }

    // ---------- Demo ----------

    public function demoResponse(array $endpoint, array $pathParams, array $query, ?array $body, PluginContext $ctx): array
    {
        $id = (string) $endpoint['id'];
        $method = strtoupper((string) ($endpoint['method'] ?? 'GET'));
        if ($method === 'DELETE') {
            return ['status' => 204];
        }
        if ($id === 'employees-create') {
            return self::demoCreateEmployee($body ?? []);
        }
        if ($method !== 'GET') {
            // Schreiben im Demo-Modus: wie angenommen, gesendete Daten zurück
            return ['status' => 200, 'json' => $body ?? []];
        }
        $filters = self::filters($query['filters'] ?? []);
        $employees = self::demoEmployees($filters);
        $no = (int) ($pathParams['employee_no'] ?? 0);

        return match ($id) {
            'departments' => ['status' => 200, 'json' => array_map(fn (array $d): array => ['number' => $d['number'], 'name' => $d['name']], self::DEMO_DEPARTMENTS)],
            'department-managers' => self::demoManagers((string) ($pathParams['department'] ?? '')),
            'employees' => ['status' => 200, 'json' => $employees],
            'employee' => self::demoOne($no),
            'employee-employments' => self::demoOne($no) + ['json' => [['valid_from' => '2019-08-01', 'valid_to' => null, 'workload_percent' => $no === 3 ? 80 : 100, 'work_schedule' => $no === 3 ? 'Lehrperson (Berufsauftrag)' : 'Verwaltung 42 h']]],
            'employee-permission-groups' => self::demoOne($no) + ['json' => [['number' => 10, 'name' => 'Mitarbeitende']]],
            'employee-roles' => self::demoOne($no) + ['json' => [['number' => 1, 'name' => $no === 2 ? 'Vorgesetzte' : 'Benutzer']]],
            'entries' => ['status' => 200, 'json' => self::demoEntries($filters, $employees)],
            'cumulatives' => ['status' => 200, 'json' => self::demoCumulatives($employees, $filters)],
            'balances' => ['status' => 200, 'json' => self::demoBalances($employees, $filters['balance_at'][1] ?? '2026-10-31')],
            'expenses' => ['status' => 200, 'json' => []],
            'public-holidays' => ['status' => 200, 'json' => [['number' => 1, 'name' => 'Kanton Zürich', 'year' => (int) ($filters['year'][1] ?? 2026), 'active' => true, 'days' => [
                ['day' => '2026-01-01', 'name' => 'Neujahr'], ['day' => '2026-04-03', 'name' => 'Karfreitag'], ['day' => '2026-08-01', 'name' => 'Bundesfeier'], ['day' => '2026-12-25', 'name' => 'Weihnachten'],
            ]]]],
            'entities' => ['status' => 200, 'json' => [['number' => '1001', 'name' => ['de' => 'Unterricht', 'en' => 'Teaching'], 'status' => 'ACTIVE'], ['number' => '1002', 'name' => ['de' => 'Schulentwicklung', 'en' => 'School development'], 'status' => 'ACTIVE']]],
            'entity' => ['status' => 200, 'json' => ['number' => (string) ($pathParams['entity'] ?? '1001'), 'name' => ['de' => 'Unterricht', 'en' => 'Teaching'], 'status' => 'ACTIVE']],
            'entity-activities' => ['status' => 200, 'json' => [['number' => 2000, 'name' => 'Unterricht vor Ort', 'internal_rate' => null, 'external_rate' => null]]],
            'entity-categories' => ['status' => 200, 'json' => [['number' => 1, 'name' => 'Berufsauftrag']]],
            'clients' => ['status' => 200, 'json' => [['number' => 4, 'name' => 'Musterberg AG (erfunden)', 'city' => 'Zürich', 'active' => true]]],
            'addresses', 'orders', 'order-date-groups', 'order-dates' => ['status' => 200, 'json' => []],
            'address', 'order', 'order-date-group', 'order-date' => ['status' => 404, 'json' => ['message' => 'Nicht gefunden (Demo).']],
            default => ['status' => 200, 'json' => []],
        };
    }

    /**
     * Anlegen im Demo-Modus (#31): Ablehnung bei gleicher Personalnummer, gleichem Benutzernamen oder gleicher
     * Badge-Nummer (wie ClickTime), sonst die neue Person mit nächster Nummer.
     *
     * @param  array<mixed>  $body
     * @return array{status: int, json: mixed}
     */
    private static function demoCreateEmployee(array $body): array
    {
        $new = is_array($body[0] ?? null) ? $body[0] : $body;
        foreach (self::DEMO_EMPLOYEES as $e) {
            if ((isset($new['employee_no']) && (int) $new['employee_no'] === $e['employee_no'])
                || mb_strtolower((string) ($new['username'] ?? '')) === mb_strtolower($e['email'])) {
                return ['status' => 422, 'json' => ['message' => 'employee_no or username already exists']];
            }
        }

        return ['status' => 201, 'json' => [['employee_no' => (int) ($new['employee_no'] ?? count(self::DEMO_EMPLOYEES) + 1)] + $new + ['active' => true]]];
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $filters
     * @return list<array<string, mixed>>
     */
    private static function demoEmployees(array $filters): array
    {
        $list = self::DEMO_EMPLOYEES;
        if (isset($filters['employee_no'])) {
            $wanted = array_map('intval', explode(',', $filters['employee_no'][1]));
            $list = array_values(array_filter($list, fn (array $e): bool => in_array($e['employee_no'], $wanted, true)));
        }
        foreach (['last_name', 'first_name'] as $field) {
            if (isset($filters[$field])) {
                $list = array_values(array_filter($list, fn (array $e): bool => stripos((string) $e[$field], $filters[$field][1]) !== false));
            }
        }

        return $list;
    }

    /**
     * @return array{status: int, json?: mixed}
     */
    private static function demoOne(int $no): array
    {
        foreach (self::DEMO_EMPLOYEES as $e) {
            if ($e['employee_no'] === $no) {
                return ['status' => 200, 'json' => $e];
            }
        }

        return ['status' => 404, 'json' => ['message' => "Mitarbeitende Person {$no} nicht gefunden (Demo)."]];
    }

    /**
     * @return array{status: int, json: mixed}
     */
    private static function demoManagers(string $department): array
    {
        foreach (self::DEMO_DEPARTMENTS as $d) {
            if ($d['number'] === $department) {
                return ['status' => 200, 'json' => array_values(array_filter(self::DEMO_EMPLOYEES, fn (array $e): bool => in_array($e['employee_no'], $d['managers'], true)))];
            }
        }

        return ['status' => 404, 'json' => ['message' => "Abteilung {$department} nicht gefunden (Demo)."]];
    }

    /**
     * Arbeitstage des Zeitraums mit 8,4 h; am 3. Arbeitstag fehlt ein Eintrag (Lücke für die Vollständigkeitsprüfung).
     *
     * @param  array<string, array{0: string, 1: string}>  $filters
     * @param  list<array<string, mixed>>  $employees
     * @return list<array<string, mixed>>
     */
    private static function demoEntries(array $filters, array $employees): array
    {
        $from = $filters['day'][1] ?? '2026-10-01';
        $to = $filters['day:LE'][1] ?? date('Y-m-t', (int) strtotime($from));
        $start = (int) strtotime($from);
        $end = min((int) strtotime($to), (int) strtotime($from.' +1 month -1 day'));
        // Nach einem Abschluss (Demo) eine Korrektur simulieren: Person 1 – erste Buchung gekürzt, Lücke nachgetragen,
        // letzte Buchung gelöscht
        $corrected = Cache::get(self::DEMO_CACHE.'closed:'.substr($from, 0, 7)) === true;
        $entries = [];
        foreach (array_slice($employees, 0, 3) as $e) {
            $no = (int) $e['employee_no'];
            $workday = 0;
            $mine = [];
            for ($t = $start; $t <= $end; $t = (int) strtotime('+1 day', $t)) {
                if ((int) date('N', $t) >= 6) {
                    continue;
                }
                $workday++;
                $entry = [
                    'number' => $no * 100000 + (int) date('md', $t), 'employee_no' => $no, 'day' => date('Y-m-d', $t),
                    'time_from' => '07:30:00', 'time_to' => '16:24:00', 'hours' => 8.4, 'time_zone' => 'Europe/Zurich',
                    // wie die echte Antwort: Nummer leer, Name mehrsprachig, Kategorie in entry_type_type
                    'entry_type_number' => null, 'entry_type_name' => ['de' => 'Arbeitszeit', 'en' => 'Working hours'],
                    'entry_type_short' => null, 'entry_type_type' => 'WORK', 'activity_number' => null, 'source' => 'PC',
                    'notes' => null, 'deleted' => false,
                    // Werte geraten (#200): erste Monatshälfte genehmigt, danach leer
                    'approval_status' => (int) date('j', $t) <= 15 ? 'APPROVED' : null, 'last_update' => date('Y-m-d H:i:s', $t + 64800),
                ];
                if ($workday === 3 && ! ($corrected && $no === 1)) {
                    continue;
                }
                if ($corrected && $no === 1 && $workday === 1) {
                    $entry = ['time_to' => '13:30:00', 'hours' => 6.0] + $entry;
                }
                $mine[] = $entry;
            }
            if ($corrected && $no === 1) {
                array_pop($mine);
            }
            $entries = [...$entries, ...$mine];
        }

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @return list<array<string, mixed>>
     */
    private static function demoBalances(array $employees, string $at): array
    {
        $rows = [];
        foreach ($employees as $e) {
            $rows[] = ['employee_no' => $e['employee_no'], 'balance_number' => 10, 'name' => 'Gleitzeit', 'value' => -0.4, 'unit' => 'h', 'balance_at' => $at];
            $rows[] = ['employee_no' => $e['employee_no'], 'balance_number' => 20, 'name' => 'Ferien', 'value' => 15.5, 'unit' => 'd', 'balance_at' => $at];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $employees
     * @param  array<string, array{0: string, 1: string}>  $filters
     * @return list<array<string, mixed>>
     */
    private static function demoCumulatives(array $employees, array $filters = []): array
    {
        // Einzelner Tag (#102): Sollzeit Mo–Fr 8.4 h (Pensum 80 % bei Nr. 3), Wochenende und Feiertage 0
        $from = (string) ($filters['valid_from'][1] ?? '');
        if ($from !== '' && $from === (string) ($filters['valid_to'][1] ?? '')) {
            $free = (int) date('N', (int) strtotime($from)) >= 6 || in_array(substr($from, 5), ['01-01', '01-02', '05-01', '08-01', '12-25', '12-26'], true);

            return array_map(fn (array $e): array => [
                'cumulative_values' => [['entry_type_name' => 'Sollzeit', 'entry_type_number' => 0, 'hours' => $free ? 0.0 : ((int) $e['employee_no'] === 3 ? 6.72 : 8.4),
                    'hours_weighted' => null, 'valid_from' => $from, 'valid_to' => $from]],
                'employee_no' => $e['employee_no'], 'first_name' => $e['first_name'], 'last_name' => $e['last_name'],
            ], $employees);
        }

        // Aufbau wie die echte Antwort (05.11.2026): je Person cumulative_values, Nummer meist null
        $types = [['Arbeitszeit', null, 176.4], ['Bezahlte Absenzen', null, 0], ['Ferien', null, 8.4], ['Homeoffice', 1, 0],
            ['Kompensation', null, 0], ['Krankheit', null, 0], ['Pause', null, -1], ['Unfall', null, 0], ['Weiterbildung', null, 0],
            ['Sollzeit', 0, 184.8]];
        $rows = [];
        foreach ($employees as $e) {
            $rows[] = [
                'cumulative_values' => array_map(fn (array $t): array => [
                    'entry_type_name' => $t[0], 'entry_type_number' => $t[1], 'hours' => $t[1] === 0 && (int) $e['employee_no'] === 3 ? round($t[2] * 0.8, 2) : $t[2], 'hours_weighted' => $t[1] === 0 ? null : $t[2],
                    'valid_from' => '2026-10-01', 'valid_to' => '2026-10-31',
                ], $types),
                'employee_no' => $e['employee_no'],
                'first_name' => $e['first_name'],
                'last_name' => $e['last_name'],
            ];
        }

        return $rows;
    }

    // ---------- Hilfen ----------

    /**
     * Filter «feld::OP::wert» (Liste oder mit «|» getrennt) → feld → [OP, wert]; «day» mit LE als «day:LE».
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function filters(mixed $value): array
    {
        $items = is_array($value) ? $value : explode('|', (string) $value);
        $result = [];
        foreach ($items as $item) {
            $parts = explode('::', trim((string) $item), 3);
            if (count($parts) !== 3) {
                continue;
            }
            [$field, $op, $v] = $parts;
            $key = isset($result[$field]) ? $field.':'.strtoupper($op) : $field;
            $result[$key] = [strtoupper($op), $v];
        }

        return $result;
    }

    /**
     * Antwort als Liste von Datensätzen (Liste direkt oder unter «data»/«items»).
     *
     * @return list<array<string, mixed>>
     */
    private static function list(Response $response): array
    {
        $json = $response->json();
        if (is_array($json) && ! array_is_list($json)) {
            $json = $json['data'] ?? $json['items'] ?? $json['content'] ?? [$json];
        }

        return array_values(array_filter(is_array($json) ? $json : [], 'is_array'));
    }

    private static function expect(Response $response): Response
    {
        if (! $response->successful()) {
            throw new RuntimeException(self::failure($response));
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $e
     */
    private static function fullName(array $e): string
    {
        $name = trim(self::text($e['first_name'] ?? '').' '.self::text($e['last_name'] ?? ''));

        return $name !== '' ? $name : self::text($e['full_name'] ?? $e['name'] ?? ($e['employee_no'] ?? ''));
    }

    /** Text oder mehrsprachiger Name ({de, en}). */
    private static function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['de'] ?? reset($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function summary(array $row): string
    {
        $parts = [];
        foreach ($row as $key => $value) {
            if (is_scalar($value) && $value !== '') {
                $parts[] = $key.': '.(is_bool($value) ? ($value ? 'ja' : 'nein') : (string) $value);
            }
        }

        return implode(', ', $parts);
    }

    private static function swissServer(): bool
    {
        return (bool) config('intranet.server_in_switzerland');
    }

    private static function failure(Response $response): string
    {
        $message = $response->json('message') ?? $response->json('error_description') ?? $response->json('error');

        return match ($response->status()) {
            401 => 'ClickTime: Anmeldung abgelehnt (HTTP 401) – client_id, client_secret und Kundennummer prüfen.',
            403 => 'ClickTime: keine Berechtigung (HTTP 403) – Scope des OpenAPI-Clients prüfen'.(is_string($message) ? ": {$message}" : '.'),
            404 => 'ClickTime: nicht gefunden (HTTP 404)'.(is_string($message) ? ": {$message}" : '.'),
            429 => 'ClickTime: zu viele Anfragen (HTTP 429) – später erneut versuchen.',
            default => 'ClickTime antwortet mit HTTP '.$response->status().(is_string($message) ? ": {$message}" : '.'),
        };
    }
}
