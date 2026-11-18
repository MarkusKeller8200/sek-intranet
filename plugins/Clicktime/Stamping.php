<?php

namespace Plugins\Clicktime;

use App\Plugins\PluginContext;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Ein- und Ausstempeln sowie Korrektur einer Buchung in ClickTime (#214) für die Zeiterfassung.
 *
 * - Status: Buchungen des Tages (GET /entries); eingestempelt = Buchung mit Beginn ohne Ende.
 * - Einstempeln: POST /entries mit Beginn = jetzt, ohne Ende.
 * - Ausstempeln: offene Buchung mit Ende = jetzt ergänzen (PUT /entries, Vorgabe) oder – Einstellung
 *   «stamp_out_post» – POST /entries nur mit Ende (je nachdem, wie ClickTime das Stempeln erwartet; zu prüfen).
 * - Korrektur: PUT /entries mit der Nummer der Buchung; übrige Felder aus der gelesenen Buchung.
 *
 * Schreiben nur mit «Schreibende Aktionen erlauben»; ClickTime schaltet ENTRIES_WRITE laut Hersteller erst nach
 * Rücksprache frei. Feldnamen (entry_type_number) laut Manifest noch zu prüfen.
 */
final class Stamping
{
    public const TIME_ZONE = 'Europe/Zurich';

    public function __construct(private readonly PluginContext $ctx) {}

    /**
     * @return array{eingestempelt: bool, seit: string|null, number: string|null}
     */
    public function status(string $employeeNo, ?string $day = null): array
    {
        $open = $this->open($employeeNo, $day ?? self::today());

        return [
            'eingestempelt' => $open !== null,
            'seit' => $open !== null ? substr((string) $open['time_from'], 0, 5) : null,
            'number' => $open !== null && isset($open['number']) ? (string) $open['number'] : null,
        ];
    }

    /** Einstempeln; gibt die Zeit «HH:MM» zurück. */
    public function kommen(string $employeeNo): string
    {
        $day = self::today();
        if ($this->open($employeeNo, $day) !== null) {
            throw new RuntimeException('Bereits eingestempelt.');
        }
        $time = self::now();
        $entry = ['day' => $day, 'time_from' => $time.':00', 'time_to' => null, 'time_zone' => self::TIME_ZONE,
            'employee_no' => self::number($employeeNo), 'notes' => 'Eingestempelt im Intranet'];
        $type = trim((string) $this->ctx->setting('stamp_type', ''));
        if ($type !== '') {
            $entry['entry_type_number'] = self::number($type);
        }
        self::expect($this->ctx->call('entries-create', [], [$entry]), 'Einstempeln');

        return $time;
    }

    /** Ausstempeln; gibt die Zeit «HH:MM» zurück. */
    public function gehen(string $employeeNo): string
    {
        $day = self::today();
        $open = $this->open($employeeNo, $day) ?? throw new RuntimeException('Nicht eingestempelt.');
        $time = self::now();
        if ($this->ctx->setting('stamp_out_post', false) === true) {
            $entry = ['day' => $day, 'time_from' => null, 'time_to' => $time.':00', 'time_zone' => self::TIME_ZONE, 'employee_no' => self::number($employeeNo)];
            self::expect($this->ctx->call('entries-create', [], [$entry]), 'Ausstempeln');

            return $time;
        }
        $from = substr((string) $open['time_from'], 0, 5);
        $this->put($open, ['time_to' => $time.':00', 'hours' => self::hours($from, $time)], 'Ausstempeln');

        return $time;
    }

    /**
     * Buchung korrigieren (PUT /entries).
     *
     * @param  array{day: string, time_from: string|null, time_to: string|null, hours: float, notes?: string|null}  $changes
     */
    public function korrigieren(string $employeeNo, string $number, string $originalDay, array $changes): void
    {
        $row = null;
        foreach ($this->rows($employeeNo, $originalDay) as $r) {
            if ((string) ($r['number'] ?? '') === $number) {
                $row = $r;
            }
        }
        if ($row === null) {
            throw new RuntimeException("Buchung {$number} in ClickTime nicht gefunden.");
        }
        $this->put($row, [
            'day' => $changes['day'],
            'time_from' => $changes['time_from'] !== null ? $changes['time_from'].':00' : null,
            'time_to' => $changes['time_to'] !== null ? $changes['time_to'].':00' : null,
            'hours' => $changes['hours'],
        ] + (array_key_exists('notes', $changes) ? ['notes' => $changes['notes']] : []), 'Korrektur');
    }

    /**
     * Offene Buchung des Tages (Beginn ohne Ende), die letzte zuerst.
     *
     * @return array<string, mixed>|null
     */
    private function open(string $employeeNo, string $day): ?array
    {
        $open = null;
        foreach ($this->rows($employeeNo, $day) as $r) {
            if (($r['deleted'] ?? false) === true) {
                continue;
            }
            if (trim((string) ($r['time_from'] ?? '')) !== '' && trim((string) ($r['time_to'] ?? '')) === ''
                && ($open === null || (string) $r['time_from'] > (string) $open['time_from'])) {
                $open = $r;
            }
        }

        return $open;
    }

    /**
     * Rohdaten der Buchungen einer Person an einem Tag.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(string $employeeNo, string $day): array
    {
        $response = self::expect($this->ctx->call('entries', ['filters' => "day::GE::{$day}|day::LE::{$day}|employee_no::IN::{$employeeNo}"]), 'Buchungen lesen');
        $json = $response->json();
        if (is_array($json) && ! array_is_list($json)) {
            $json = $json['data'] ?? $json['items'] ?? $json['content'] ?? [];
        }
        $rows = [];
        foreach (is_array($json) ? $json : [] as $r) {
            if (is_array($r) && substr((string) ($r['day'] ?? ''), 0, 10) === $day && (string) ($r['employee_no'] ?? '') === $employeeNo) {
                $rows[] = $r;
            }
        }

        return $rows;
    }

    /**
     * Buchung mit Änderungen senden; nur die Felder des Manifest-Beispiels (keine Lese-Felder wie entry_type_name).
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $changes
     */
    private function put(array $row, array $changes, string $what): void
    {
        $example = $this->ctx->manifest->endpoint('entries-update')['body'][0] ?? [];
        $allowed = is_array($example) ? array_keys($example) : ['number', 'day', 'time_from', 'time_to', 'time_zone', 'hours', 'employee_no', 'notes'];
        $entry = array_intersect_key($changes + $row, array_flip($allowed));
        $entry['number'] = $row['number'] ?? throw new RuntimeException('Buchung ohne Nummer – Ändern nicht möglich.');
        $entry['time_zone'] ??= self::TIME_ZONE;
        self::expect($this->ctx->call('entries-update', [], [$entry]), $what);
    }

    private static function expect(Response $response, string $what): Response
    {
        if (! $response->successful()) {
            // Meldung von ClickTime mitgeben (#218); bei 403 auf die Schreibberechtigung hinweisen
            $detail = $response->json('message') ?? $response->json('error_description') ?? $response->json('error') ?? $response->json('title') ?? null;
            $detail = is_scalar($detail) ? mb_substr(trim((string) $detail), 0, 200) : '';
            $hint = $response->status() === 403 && $what !== 'Buchungen lesen'
                ? ' Vermutlich fehlt dem ClickTime-Client die Berechtigung ENTRIES_WRITE oder ClickTime hat das Schreiben von Buchungen noch nicht freigeschaltet.'
                : '';
            throw new RuntimeException("{$what}: ClickTime antwortet mit HTTP {$response->status()}".($detail !== '' ? " ({$detail})" : '').'.'.$hint.' Details im Protokoll des Plugins (Protokollstufe «Details»).');
        }

        return $response;
    }

    private static function hours(string $from, string $to): float
    {
        $min = fn (string $t): int => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

        return round(max(0, $min($to) - $min($from)) / 60, 2);
    }

    private static function number(string $value): int|string
    {
        return ctype_digit($value) ? (int) $value : $value;
    }

    private static function today(): string
    {
        return now(self::TIME_ZONE)->format('Y-m-d');
    }

    private static function now(): string
    {
        return now(self::TIME_ZONE)->format('H:i');
    }
}
