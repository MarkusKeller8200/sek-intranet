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
 * - Abschluss: Stand der Buchungen je Person (Nummer, Tag, Zeit, Stunden, Art, Tätigkeit – ohne Notizen) als Datei
 *   unter storage/app/private/plugins/clicktime/abschluss/<JJJJ-MM>.json. Danach zeigt der Vergleich mit dem
 *   aktuellen Stand, was neu, geändert oder gelöscht ist.
 */
final class Months
{
    public const DIR = 'plugins/clicktime/abschluss';

    /** Verglichene Felder einer Buchung. */
    public const FIELDS = ['day', 'time_from', 'time_to', 'hours', 'type', 'activity'];

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

        return $result;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{number: string, day: string, time_from: string, time_to: string, hours: float, type: string, activity: string}
     */
    public static function normalize(array $row): array
    {
        $day = substr((string) ($row['day'] ?? $row['date'] ?? ''), 0, 10);

        return [
            'number' => (string) ($row['number'] ?? $row['entry_number'] ?? ($row['employee_no'] ?? '').'-'.$day.'-'.($row['time_from'] ?? '')),
            'day' => $day,
            'time_from' => substr((string) ($row['time_from'] ?? ''), 0, 5),
            'time_to' => substr((string) ($row['time_to'] ?? ''), 0, 5),
            'hours' => round((float) ($row['hours'] ?? 0), 2),
            'type' => (string) ($row['entry_type_number'] ?? $row['entry_type'] ?? $row['entry_number'] ?? ''),
            'activity' => (string) ($row['activity_number'] ?? ''),
        ];
    }

    /**
     * Kumulative des Monats je Person: Art → [Name, Stunden].
     *
     * @param  list<string>  $employees
     * @return array<string, array<int|string, array{name: string, hours: float}>>
     */
    public function cumulatives(string $month, array $employees = []): array
    {
        [$from, $to] = self::range($month);
        $filters = ["valid_from::EQ::{$from}", "valid_to::EQ::{$to}"];
        if ($employees !== []) {
            $filters[] = 'employee_no::IN::'.implode(',', $employees);
        }
        $result = [];
        foreach (self::rows($this->expect($this->ctx->call('cumulatives', ['filters' => $filters]))) as $row) {
            $type = (string) ($row['entry_type'] ?? $row['entry_type_number'] ?? '');
            $result[(string) ($row['employee_no'] ?? '')][$type] = [
                'name' => self::text($row['name'] ?? $row['entry_type_name'] ?? ($type === '0' ? 'Sollzeit' : "Art {$type}")),
                'hours' => round((float) ($row['hours'] ?? $row['value'] ?? 0), 2),
            ];
        }

        return $result;
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
            'employees' => $entries,
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

        return self::hours((float) ($e['hours'] ?? 0)).' h'.$time.' (Art '.($e['type'] ?? '?').')';
    }

    public static function hours(float $h): string
    {
        return number_format($h, 2, '.', '\'');
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
                    $pdf->text(320, $y, (string) $e['type'], 9);
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
