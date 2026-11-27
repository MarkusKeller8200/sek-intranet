<?php

namespace Plugins\Clicktime;

use App\Plugins\PluginContext;
use App\Support\SimplePdf;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Monatsdaten aus ClickTime (#32) und Änderungen nach dem Monatsabschluss (#97).
 *
 * - Monat: Buchungen (`entries`, höchstens ein Monat pro Abfrage), Kumulative, Salden am Monatsende, Feiertage.
 * - Vollständigkeit: Arbeitstage (Mo–Fr) ohne Buchung, Feiertage ausgenommen.
 * - Genehmigung (#200): Buchungen aus `entries`, Genehmigungen aus `approvals`, Abgleich über die Buchungsnummer
 *   (`number`). Solange ClickTime `/approvals` nicht freigeschaltet hat, bleibt der Status leer.
 * - Abschluss: Stand der Buchungen je Person (Nummer, Tag, Zeit, Stunden, Art, Tätigkeit – ohne Notizen) als Datei
 *   unter storage/app/private/plugins/clicktime/abschluss/<JJJJ-MM>.json. Danach zeigt der Vergleich mit dem
 *   aktuellen Stand, was neu, geändert oder gelöscht ist.
 */
final class Months
{
    public const DIR = 'plugins/clicktime/abschluss';

    /** Verglichene Felder einer Buchung. */
    public const FIELDS = ['day', 'time_from', 'time_to', 'hours', 'type', 'activity'];

    /** Genehmigungsstatus einer Buchung, wenn `/approvals` antwortet (#200). */
    public const APPROVED = 'Genehmigt';

    public const OPEN = 'Offen';

    /** Ob `/approvals` beim letzten Abruf geantwortet hat (null = noch nicht abgefragt). */
    private ?bool $approvalsAvailable = null;

    public function __construct(private readonly PluginContext $ctx) {}

    /** JJJJ-MM prüfen; leer = Vormonat. */
    public static function month(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m', (int) strtotime('first day of last month'));
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $value, $m) !== 1 || (int) $m[2] < 1 || (int) $m[2] > 12) {
            throw new RuntimeException('Monat als JJJJ-MM angeben, z. B. 2026-10.');
        }

        return $value;
    }

    /**
     * @return array{0: string, 1: string} erster und letzter Tag
     */
    public static function range(string $month): array
    {
        return [$month.'-01', date('Y-m-t', (int) strtotime($month.'-01'))];
    }

    /**
     * Buchungen des Monats, nach Person (employee_no) gruppiert und vereinheitlicht. Gelöschte (deleted) fehlen.
     *
     * @param  list<string>  $employees  leer = alle
     * @return array<string, array<string, array<string, mixed>>> Nr. → Buchungsnummer → Buchung
     */
    public function entries(string $month, array $employees = []): array
    {
        [$from, $to] = self::range($month);
        $filters = ["day::GE::{$from}", "day::LE::{$to}"];
        if ($employees !== []) {
            $filters[] = 'employee_no::IN::'.implode(',', $employees);
        }
        $result = [];
        foreach (self::rows($this->expect($this->ctx->call('entries', ['filters' => $filters]))) as $row) {
            if (($row['deleted'] ?? false) === true) {
                continue;
            }
            $entry = self::normalize($row);
            $result[(string) ($row['employee_no'] ?? '')][$entry['number']] = $entry;
        }
        ksort($result);

        // Genehmigungen (#200): eigener Endpunkt, Abgleich über die Buchungsnummer
        $approvals = $this->approvals($month);
        if ($approvals !== null) {
            foreach ($result as $no => $list) {
                foreach ($list as $number => $entry) {
                    $result[$no][$number] = self::withApproval($entry, $approvals[(string) $number] ?? null);
                }
            }
        }

        return $result;
    }

    /** Hat `/approvals` beim letzten Abruf von entries() geantwortet? null = nicht abgefragt. */
    public function approvalsAvailable(): ?bool
    {
        return $this->approvalsAvailable;
    }

    /**
     * Genehmigungen des Monats je Buchungsnummer (ClickTime, angekündigt 27.11.2026). Je Nummer zählt der Datensatz mit
     * der jüngsten Änderung (`last_update`); zurückgenommene (`deleted`) und solche ohne `approved_1_at` gelten als
     * nicht genehmigt und fehlen. null = Endpunkt nicht verfügbar (noch nicht freigeschaltet, Scope fehlt, Fehler) –
     * dann bleiben die Buchungen ohne Genehmigungsstatus.
     *
     * @return array<string, array{by: string, at: string, type: string}>|null
     */
    public function approvals(string $month): ?array
    {
        [$from, $to] = self::range($month);
        try {
            $response = $this->ctx->call('approvals', ['filters' => ["day::GE::{$from}", "day::LE::{$to}"]]);
        } catch (\Exception $e) {
            $this->ctx->log('Genehmigungen nicht abrufbar: '.$e->getMessage());
            $this->approvalsAvailable = false;

            return null;
        }
        if (! $response->successful()) {
            $this->ctx->log('Genehmigungen nicht abrufbar: HTTP '.$response->status().' (Endpunkt /approvals freigeschaltet, Scope APPROVALS_READ vergeben?).');
            $this->approvalsAvailable = false;

            return null;
        }
        $this->approvalsAvailable = true;

        return self::latestApprovals(self::rows($response));
    }

    /**
     * Gültige Genehmigungen aus den Datensätzen von `/approvals`: je Nummer der jüngste (`last_update`), nur nicht
     * zurückgenommene mit Zeitpunkt.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{by: string, at: string, type: string}>
     */
    public static function latestApprovals(array $rows): array
    {
        $latest = [];
        foreach ($rows as $row) {
            $number = (string) ($row['number'] ?? '');
            if ($number === '') {
                continue;
            }
            $update = (string) ($row['last_update'] ?? '');
            if (! isset($latest[$number]) || strcmp($update, (string) ($latest[$number]['last_update'] ?? '')) >= 0) {
                $latest[$number] = $row;
            }
        }
        $result = [];
        foreach ($latest as $number => $row) {
            $at = is_scalar($row['approved_1_at'] ?? null) ? trim((string) $row['approved_1_at']) : '';
            if (in_array($row['deleted'] ?? false, [true, 'true', 1, '1'], true) || $at === '') {
                continue;
            }
            $result[(string) $number] = [
                'by' => is_scalar($row['approved_1_by'] ?? null) ? (string) $row['approved_1_by'] : '',
                'at' => $at,
                'type' => is_scalar($row['entry_type_number'] ?? null) ? (string) $row['entry_type_number'] : '',
            ];
        }

        return $result;
    }

    /**
     * Genehmigung in eine Buchung übernehmen: «Genehmigt» mit Person und Zeitpunkt, sonst «Offen».
     *
     * @param  array<string, mixed>  $entry
     * @param  array{by: string, at: string, type: string}|null  $approval
     * @return array<string, mixed>
     */
    public static function withApproval(array $entry, ?array $approval): array
    {
        return [
            'approval' => $approval !== null ? self::APPROVED : self::OPEN,
            'approved_by' => $approval['by'] ?? '',
            'approved_at' => $approval['at'] ?? '',
        ] + $entry;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{number: string, day: string, time_from: string, time_to: string, hours: float, type: string, type_name: string, category: string, activity: string, notes: string, approval: string, approved_by: string, approved_at: string}
     */
    public static function normalize(array $row): array
    {
        $day = substr((string) ($row['day'] ?? $row['date'] ?? ''), 0, 10);
        // Echte Antwort (05.11.2026): entry_type_name {de, en}, entry_type_number meist null, entry_type_type
        // (WORK, HOLIDAYS, COMPENSATION …), Abwesenheiten und Pausenkorrekturen ohne Zeiten, Pause mit negativen Stunden
        $number = $row['entry_type_number'] ?? $row['entry_type'] ?? null;
        $name = self::text($row['entry_type_name'] ?? '');

        return [
            'number' => (string) ($row['number'] ?? ($row['employee_no'] ?? '').'-'.$day.'-'.($row['time_from'] ?? '')),
            'day' => $day,
            'time_from' => substr((string) ($row['time_from'] ?? ''), 0, 5),
            'time_to' => substr((string) ($row['time_to'] ?? ''), 0, 5),
            'hours' => round((float) ($row['hours'] ?? 0), 2),
            'type' => $number !== null && $number !== '' ? (string) $number : $name,
            'type_name' => $name !== '' ? $name : (string) ($number ?? ''),
            'category' => (string) ($row['entry_type_type'] ?? ''),
            'activity' => (string) ($row['activity_number'] ?? ''),
            'notes' => is_string($row['notes'] ?? null) ? trim($row['notes']) : '',
            // Genehmigungsstatus (#200): aus /approvals (withApproval); «approval_status» nur als Rückfall, falls
            // ClickTime ihn je Buchung mitliefert
            'approval' => self::approval($row['approval_status'] ?? null),
            'approved_by' => '',
            'approved_at' => '',
        ];
    }

    /**
     * «approval_status» als Text: Zeichenkette bzw. Zahl unverändert, Objekt als «status»/«name» oder JSON, leer = ''.
     */
    public static function approval(mixed $value): string
    {
        if (is_array($value)) {
            $text = $value['status'] ?? $value['name'] ?? $value['de'] ?? null;

            return is_scalar($text) ? trim((string) $text) : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Kumulative des Monats je Person: Buchungsart (Nummer, sonst Name) → [Name, Stunden]. Sollzeit = Nummer 0.
     *
     * @param  list<string>  $employees
     * @return array<string, array<int|string, array{name: string, hours: float}>>
     */
    public function cumulatives(string $month, array $employees = []): array
    {
        [$from, $to] = self::range($month);

        return $this->cumulativesBetween($from, $to, $employees);
    }

    /**
     * Kumulative eines frei gewählten Zeitraums (auch ein einzelner Tag, #102).
     *
     * @param  list<string>  $employees
     * @param  list<int>  $types  Lohnartnummern (entry_type), leer = alle
     * @return array<string, array<int|string, array{name: string, hours: float}>>
     */
    public function cumulativesBetween(string $from, string $to, array $employees = [], array $types = []): array
    {
        $filters = ["valid_from::EQ::{$from}", "valid_to::EQ::{$to}"];
        if ($employees !== []) {
            $filters[] = 'employee_no::IN::'.implode(',', $employees);
        }
        // Buchungsarten (Lohnartnummern) einschränken, 0 = Sollzeit (Antwort ClickTime 09.11.2026, #163)
        if ($types !== []) {
            $filters[] = 'entry_type::IN::'.implode(',', $types);
        }
        $result = [];
        foreach (self::rows($this->expect($this->ctx->call('cumulatives', ['filters' => $filters]))) as $row) {
            // Echte Antwort (05.11.2026): je Person {employee_no, first_name, last_name, cumulative_values: [{entry_type_name,
            // entry_type_number (oft null, nur wenn in ClickTime eine Lohnart-Nummer gesetzt ist), hours, hours_weighted}]}
            $values = is_array($row['cumulative_values'] ?? null) ? $row['cumulative_values'] : [$row];
            foreach ($values as $value) {
                if (! is_array($value)) {
                    continue;
                }
                $number = $value['entry_type_number'] ?? $value['entry_type'] ?? null;
                $name = self::text($value['entry_type_name'] ?? $value['name'] ?? '');
                $key = $number !== null && $number !== '' ? (string) $number : $name;
                if ($key === '') {
                    continue;
                }
                $result[(string) ($row['employee_no'] ?? '')][$key] = [
                    'name' => $name !== '' ? $name : ($key === '0' ? 'Sollzeit' : "Art {$key}"),
                    'hours' => round((float) ($value['hours'] ?? $value['value'] ?? 0), 2),
                ];
            }
        }

        return $result;
    }

    /**
     * Sollzeit pro Tag eines Monats (#102/#103): eine Abfrage der Kumulative je Tag (valid_from = valid_to) für alle
     * Personen. Person → Datum → Stunden; Tage ohne gelieferte Sollzeit fehlen.
     *
     * Gezielt mit entry_type 0 (= Sollzeit, #163). Liefert der Filter nichts (in echten Antworten war die Nummer teils
     * leer), für den Rest des Monats ohne Filter weiter und die Sollzeit am Namen erkennen. Arbeitszeitmodell, Pensum
     * und Feiertage sind in der Sollzeit enthalten; Ferien und Abwesenheiten werden nicht abgezogen, sie zählen im Ist
     * wie geleistete Zeit (wie der Saldo in ClickTime).
     *
     * @param  list<string>  $employees
     * @return array<string, array<string, float>>
     */
    public function targets(string $month, array $employees = []): array
    {
        [$from, $to] = self::range($month);
        $result = [];
        $types = [0];
        for ($t = (int) strtotime($from); $t <= (int) strtotime($to); $t = (int) strtotime('+1 day', $t)) {
            $day = date('Y-m-d', $t);
            $rows = $this->cumulativesBetween($day, $day, $employees, $types);
            if ($types !== [] && array_filter($rows, fn (array $c): bool => self::target($c) !== null) === []) {
                // Filter liefert keine Sollzeit: ohne Filter weiter
                $types = [];
                $rows = $this->cumulativesBetween($day, $day, $employees);
            }
            foreach ($rows as $no => $cumulatives) {
                $target = self::target($cumulatives);
                if ($target !== null) {
                    $result[(string) $no][$day] = $target;
                }
            }
        }

        return $result;
    }

    /**
     * Sollzeit aus Kumulativen: Buchungsart Nummer 0, sonst Name «Sollzeit»/«Soll…». Null, wenn keine geliefert wird.
     *
     * @param  array<int|string, array{name: string, hours: float}>  $cumulatives
     */
    public static function target(array $cumulatives): ?float
    {
        if (isset($cumulatives['0'])) {
            return $cumulatives['0']['hours'];
        }
        foreach ($cumulatives as $c) {
            if (str_starts_with(mb_strtolower($c['name']), 'soll')) {
                return $c['hours'];
            }
        }

        return null;
    }

    /**
     * Salden an einem Stichtag je Person.
     *
     * @param  list<string>  $employees
     * @return array<string, list<array{name: string, value: float, unit: string}>>
     */
    public function balances(string $at, array $employees = []): array
    {
        $filters = ["balance_at::EQ::{$at}"];
        if ($employees !== []) {
            $filters[] = 'employee_no::IN::'.implode(',', $employees);
        }
        $result = [];
        foreach (self::rows($this->expect($this->ctx->call('balances', ['filters' => $filters]))) as $row) {
            $result[(string) ($row['employee_no'] ?? '')][] = [
                'name' => self::text($row['name'] ?? $row['balance_name'] ?? 'Saldo '.($row['balance_number'] ?? '')),
                'value' => round((float) ($row['value'] ?? $row['balance'] ?? 0), 2),
                'unit' => (string) ($row['unit'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Feiertage eines Jahres (Tag → Name) aus den aktiven Kalendern.
     *
     * @return array<string, string>
     */
    public function holidays(int $year): array
    {
        $result = [];
        $response = $this->ctx->call('public-holidays', ['filters' => ["year::EQ::{$year}", 'active::EQ::true']]);
        if (! $response->successful()) {
            return [];
        }
        foreach (self::rows($response) as $calendar) {
            $days = is_array($calendar['days'] ?? null) ? $calendar['days'] : (is_array($calendar['holidays'] ?? null) ? $calendar['holidays'] : [$calendar]);
            foreach ($days as $d) {
                if (is_array($d)) {
                    $day = substr((string) ($d['day'] ?? $d['date'] ?? ''), 0, 10);
                    if ($day !== '') {
                        $result[$day] = self::text($d['name'] ?? 'Feiertag');
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Arbeitstage ohne Buchung (Mo–Fr, ohne Feiertage).
     *
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array<string, string>  $holidays
     * @return list<string>
     */
    public static function gaps(string $month, array $entries, array $holidays): array
    {
        $booked = array_flip(array_map(fn (array $e): string => (string) $e['day'], $entries));
        [$from, $to] = self::range($month);
        $gaps = [];
        for ($t = (int) strtotime($from); $t <= (int) strtotime($to); $t = (int) strtotime('+1 day', $t)) {
            $day = date('Y-m-d', $t);
            if ((int) date('N', $t) <= 5 && ! isset($holidays[$day]) && ! isset($booked[$day])) {
                $gaps[] = $day;
            }
        }

        return $gaps;
    }

    // ---------- Abschluss und Änderungen ----------

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $entries
     */
    public static function close(string $month, array $entries, ?string $by): void
    {
        Storage::disk('local')->put(self::DIR."/{$month}.json", (string) json_encode([
            'month' => $month,
            'closed_at' => now()->toIso8601String(),
            'closed_by' => $by,
            // ohne Notizen (Datensparsamkeit)
            'employees' => array_map(fn (array $list): array => array_map(fn (array $e): array => array_diff_key($e, ['notes' => true]), $list), $entries),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @return array{month: string, closed_at: string, closed_by: string|null, employees: array<string, array<string, array<string, mixed>>>}|null
     */
    public static function closing(string $month): ?array
    {
        $path = self::DIR."/{$month}.json";
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }
        $data = json_decode((string) Storage::disk('local')->get($path), true);

        /** @var array{month: string, closed_at: string, closed_by: string|null, employees: array<string, array<string, array<string, mixed>>>}|null */
        return is_array($data) ? $data : null;
    }

    /**
     * Vergleich Abschluss ↔ heute: neu, geändert (alt → neu), gelöscht.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $before
     * @param  array<string, array<string, array<string, mixed>>>  $now
     * @return list<array{employee_no: string, number: string, day: string, change: string, before: string, after: string}>
     */
    public static function diff(array $before, array $now): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($now)]) as $no) {
            $old = $before[$no] ?? [];
            $new = $now[$no] ?? [];
            foreach ($new as $number => $entry) {
                if (! isset($old[$number])) {
                    $changes[] = ['employee_no' => (string) $no, 'number' => (string) $number, 'day' => (string) $entry['day'], 'change' => 'neu', 'before' => '–', 'after' => self::describe($entry)];
                } elseif (self::differs($old[$number], $entry)) {
                    $changes[] = ['employee_no' => (string) $no, 'number' => (string) $number, 'day' => (string) $entry['day'], 'change' => 'geändert', 'before' => self::describe($old[$number]), 'after' => self::describe($entry)];
                }
            }
            foreach ($old as $number => $entry) {
                if (! isset($new[$number])) {
                    $changes[] = ['employee_no' => (string) $no, 'number' => (string) $number, 'day' => (string) $entry['day'], 'change' => 'gelöscht', 'before' => self::describe($entry), 'after' => '–'];
                }
            }
        }
        usort($changes, fn (array $a, array $b): int => [$a['employee_no'], $a['day'], $a['number']] <=> [$b['employee_no'], $b['day'], $b['number']]);

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function differs(array $a, array $b): bool
    {
        foreach (self::FIELDS as $field) {
            if ((string) ($a[$field] ?? '') !== (string) ($b[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $e
     */
    public static function describe(array $e): string
    {
        $time = ($e['time_from'] ?? '') !== '' ? " {$e['time_from']}–{$e['time_to']}" : '';

        return self::hours((float) ($e['hours'] ?? 0)).' h'.$time.' ('.(string) ($e['type_name'] ?? $e['type'] ?? '?').')';
    }

    public static function hours(float $h): string
    {
        return number_format($h, 2, '.', '\'');
    }

    /** Stunden als «HH:MM», z. B. 184.8 → «184:48». */
    public static function hhmm(float $h): string
    {
        $min = (int) round(abs($h) * 60);

        return ($h < 0 && $min > 0 ? '−' : '').sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    // ---------- Monatsblatt (PDF) ----------

    /**
     * @param  array<string, array<string, mixed>>  $entries
     * @param  array<int|string, array{name: string, hours: float}>  $cumulatives
     * @param  list<array{name: string, value: float, unit: string}>  $balances
     * @param  array<string, string>  $holidays
     * @param  list<string>  $gaps
     */
    public static function pdf(string $month, string $person, string $no, array $entries, array $cumulatives, array $balances, array $holidays, array $gaps): string
    {
        $title = 'Monatsblatt '.date('m.Y', (int) strtotime($month.'-01'));
        $pdf = new SimplePdf("{$title} – {$person}");
        $y = 60;
        $pdf->text(50, $y, $title, 16, true);
        $pdf->text(50, $y += 20, "{$person} (ClickTime-Nr. {$no})", 11);
        $pdf->text(50, $y += 14, 'Quelle: ClickTime, abgerufen am '.date('d.m.Y H:i'), 8);
        $y += 24;
        $pdf->text(50, $y, 'Tag', 9, true);
        $pdf->text(130, $y, 'Zeit', 9, true);
        $pdf->textRight(300, $y, 'Stunden', 9, true);
        $pdf->text(320, $y, 'Art', 9, true);
        $pdf->text(380, $y, 'Hinweis', 9, true);
        $pdf->line(50, $y + 4, 545, $y + 4);
        $byDay = [];
        foreach ($entries as $e) {
            $byDay[(string) $e['day']][] = $e;
        }
        [$from, $to] = self::range($month);
        for ($t = (int) strtotime($from); $t <= (int) strtotime($to); $t = (int) strtotime('+1 day', $t)) {
            $day = date('Y-m-d', $t);
            $rows = $byDay[$day] ?? [[]];
            foreach ($rows as $e) {
                $y += 13;
                if ($y > 790) {
                    $pdf->addPage();
                    $y = 60;
                }
                $pdf->text(50, $y, self::weekday($t).' '.date('d.m.', $t), 9);
                if ($e !== []) {
                    $pdf->text(130, $y, ($e['time_from'] ?? '') !== '' ? "{$e['time_from']}–{$e['time_to']}" : '', 9);
                    $pdf->textRight(300, $y, self::hours((float) $e['hours']), 9);
                    $pdf->text(320, $y, SimplePdf::fit((string) ($e['type_name'] ?? $e['type']), 55, 9), 9);
                }
                $note = $holidays[$day] ?? (in_array($day, $gaps, true) ? 'keine Buchung' : '');
                $pdf->text(380, $y, $note, 9);
            }
        }
        $y += 24;
        if ($y > 700) {
            $pdf->addPage();
            $y = 60;
        }
        $pdf->text(50, $y, 'Summen', 11, true);
        foreach ($cumulatives as $type => $c) {
            $pdf->text(50, $y += 14, "{$c['name']} ({$type})", 9);
            $pdf->textRight(300, $y, self::hours($c['hours']).' h', 9);
        }
        $pdf->text(50, $y += 22, 'Salden am '.date('d.m.Y', (int) strtotime($to)), 11, true);
        foreach ($balances as $b) {
            $pdf->text(50, $y += 14, $b['name'], 9);
            $pdf->textRight(300, $y, self::hours($b['value']).' '.$b['unit'], 9);
        }
        if ($gaps !== []) {
            $pdf->text(50, $y += 22, count($gaps).' Arbeitstage ohne Buchung', 9, true);
        }

        return $pdf->output();
    }

    private static function weekday(int $t): string
    {
        return ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'][(int) date('N', $t) - 1];
    }

    // ---------- Hilfen ----------

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(Response $response): array
    {
        $json = $response->json();
        if (is_array($json) && ! array_is_list($json)) {
            $json = $json['data'] ?? $json['items'] ?? $json['content'] ?? [$json];
        }

        return array_values(array_filter(is_array($json) ? $json : [], 'is_array'));
    }

    private function expect(Response $response): Response
    {
        if (! $response->successful()) {
            throw new RuntimeException('ClickTime antwortet mit HTTP '.$response->status().'.');
        }

        return $response;
    }

    private static function text(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value['de'] ?? reset($value);
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
