<?php

namespace Modules\Zeiterfassung;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Plugins\PluginContext;
use App\Plugins\PluginManager;
use App\Support\Notifications;
use Illuminate\Support\Facades\Storage;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Plugins\Clicktime\Importer;
use Plugins\Clicktime\Months;
use RuntimeException;
use Throwable;

/**
 * Monatsabschluss (#164, #34).
 *
 * - **Mit ClickTime** führt ClickTime Abschluss und Genehmigung (Antwort ClickTime 09.11.2026: über die API nicht
 *   lesbar). Die Person bestätigt im Intranet «Monat in ClickTime abgeschlossen» – im Notfall die Schulleitung bzw.
 *   Administration stellvertretend (vermerkt). Dann übernimmt das Intranet die Buchungen ein letztes Mal, sperrt den
 *   Monat (Status «abgeschlossen») und legt das Monatsblatt ab. Nachbuchungen in ClickTime danach werden beim Vergleich
 *   erkannt und gemeldet, nie automatisch übernommen.
 * - **Ohne ClickTime** bleibt der Ablauf einreichen → visieren (Schulleitung); beim Visum wird das Monatsblatt abgelegt.
 * - Das Monatsblatt (PDF auf dem Server) liegt in der Ablage des Moduls und ist nur für die Person selbst und Personen
 *   mit Recht «visum-zeiterfassung» abrufbar.
 */
final class Monatsabschluss
{
    public function __construct(
        private readonly EintragRepository $eintraege,
        private readonly MonatRepository $monate,
        private readonly UserRepository $users,
        private readonly Buchungsarten $buchungsarten,
        private readonly Notifications $notifications,
        private readonly Sollzeiten $sollzeiten,
    ) {}

    /** Pfad des Monatsblatts in der Ablage des Moduls (Disk «local»). */
    public static function datei(string $person, string $monat): string
    {
        return "zeiterfassung/monatsblatt/{$person}/{$monat}.pdf";
    }

    /**
     * In ClickTime abgeschlossen bestätigen: letzter Abgleich, Monat sperren, Monatsblatt ablegen, benachrichtigen.
     *
     * @return array{neu: int, geaendert: int, geloescht: int, stunden: float}
     *
     * @throws RuntimeException wenn der Monat gesperrt ist oder ClickTime nicht antwortet (dann bleibt alles unverändert)
     */
    public function clicktime(string $person, string $monat, string $nr, ?IntranetUser $durch): array
    {
        $status = (string) ($this->monate->status($person, $monat)['status'] ?? 'offen');
        if (Zeitkonto::gesperrt($status)) {
            throw new RuntimeException('Der Monat ist bereits '.(Kategorien::STATUS[$status] ?? $status).'.');
        }
        $ergebnis = app(PluginManager::class)->runWith('clicktime', $durch, 'Zeiterfassung: Monat in ClickTime abgeschlossen',
            fn (PluginContext $ctx): array => (new Importer($ctx))->run($person, $nr, $monat, true));
        $counts = is_array($ergebnis['counts'] ?? null) ? $ergebnis['counts'] : [];
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);
        $durchId = $durch !== null ? (string) $durch->getAuthIdentifier() : $person;
        $stellvertretend = $durchId !== $person;
        $this->monate->setzen($person, $monat, [
            'status' => 'abgeschlossen',
            'stunden' => Zeitkonto::summen($liste)['total'],
            'abgeschlossen_at' => now()->toIso8601String(),
            'abgeschlossen_by' => $durchId,
            'abgeschlossen_stellvertretend' => $stellvertretend,
            'abweichung' => null,
            'bemerkung' => null,
        ]);
        $this->ablegen($person, $monat);

        $name = $this->name($person);
        if ($stellvertretend) {
            $this->notifications->toUsers([$person], 'zeit.monat_abgeschlossen', "Zeiterfassung {$monat}: Abschluss bestätigt",
                'Die Schulleitung hat den Abschluss in ClickTime stellvertretend bestätigt. Das Monatsblatt liegt in deiner Zeiterfassung.', "/zeiterfassung?monat={$monat}", $durchId);
        }
        $this->notifications->toAbility('visum-zeiterfassung', 'zeit.monat_abgeschlossen', "Zeiterfassung {$monat}: {$name} in ClickTime abgeschlossen",
            'Monat übernommen und gesperrt; das Monatsblatt ist abgelegt.', "/zeiterfassung/visum/{$person}?monat={$monat}", $durchId);

        return [
            'neu' => (int) ($counts['neu'] ?? 0),
            'geaendert' => (int) ($counts['geändert'] ?? 0),
            'geloescht' => (int) ($counts['gelöscht'] ?? 0),
            'stunden' => Zeitkonto::summen($liste)['total'],
        ];
    }

    /**
     * Abgeschlossenen Monat mit ClickTime vergleichen (Nachbuchungen, #164). Übernimmt nichts; Abweichungen werden am
     * Monat vermerkt und der Schulleitung gemeldet.
     *
     * @return array{neu: int, geaendert: int, geloescht: int}
     */
    public function vergleichen(string $person, string $monat, string $nr, ?IntranetUser $durch): array
    {
        $ergebnis = app(PluginManager::class)->runWith('clicktime', $durch, 'Zeiterfassung: Nachbuchungen prüfen',
            fn (PluginContext $ctx): array => (new Importer($ctx))->run($person, $nr, $monat, false, true));
        $counts = is_array($ergebnis['counts'] ?? null) ? $ergebnis['counts'] : [];
        $result = ['neu' => (int) ($counts['neu'] ?? 0), 'geaendert' => (int) ($counts['geändert'] ?? 0), 'geloescht' => (int) ($counts['gelöscht'] ?? 0)];
        $anzahl = array_sum($result);
        $this->monate->setzen($person, $monat, ['abweichung' => ['anzahl' => $anzahl, 'geprueft_at' => now()->toIso8601String()] + $result]);
        if ($anzahl > 0) {
            $this->notifications->toAbility('visum-zeiterfassung', 'zeit.monat_nachbuchung', "Zeiterfassung {$monat}: Nachbuchungen in ClickTime",
                "{$this->name($person)}: {$anzahl} Buchungen seit dem Abschluss geändert. Zum Übernehmen den Monat zurückgeben.", "/zeiterfassung/visum/{$person}?monat={$monat}", null);
        }

        return $result;
    }

    /**
     * Monatsblatt als PDF erstellen und ablegen (#34): Einträge, Summen, Soll/Ist und die Bestätigungen. Ersetzt ein
     * früheres Blatt desselben Monats.
     */
    public function ablegen(string $person, string $monat): string
    {
        $dokument = $this->pdf($person, $monat);
        $pfad = self::datei($person, $monat);
        if (! Storage::disk('local')->put($pfad, $dokument['pdf'])) {
            throw new RuntimeException('Monatsblatt konnte nicht abgelegt werden.');
        }
        $this->monate->setzen($person, $monat, ['monatsblatt_pdf' => $pfad, 'monatsblatt_at' => now()->toIso8601String()]);

        return $pfad;
    }

    /**
     * Monatsrapport als PDF im Aufbau von ClickTime (#168): Buchungen mit Soll/Differenz je Tag, Total, Buchungsarten,
     * Saldi (mit ClickTime: Vormonat und Ende Monat), Bestätigungen. Mit $unterzeichnende zusätzlich die
     * Unterschriftsfelder für DeepSign (#43).
     *
     * @param  array{person: string, schulleitung: string}|null  $unterzeichnende
     * @return array{pdf: string, felder: array<string, array{pageNumber: int, x: float, y: float, width: float, height: float}>}
     */
    public function pdf(string $person, string $monat, ?array $unterzeichnende = null): array
    {
        $status = $this->monate->status($person, $monat);
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);
        $vermerke = [];
        if (is_string($status['eingereicht_at'] ?? null)) {
            $vermerke[] = 'Eingereicht am '.self::zeit($status['eingereicht_at']);
        }
        if (($status['status'] ?? '') === 'abgeschlossen') {
            $by = (string) ($status['abgeschlossen_by'] ?? '');
            $vermerke[] = 'In ClickTime abgeschlossen; bestätigt von '.$this->name($by).' am '.self::zeit($status['abgeschlossen_at'] ?? null)
                .(($status['abgeschlossen_stellvertretend'] ?? false) === true ? ' (stellvertretend)' : '');
        }
        $nr = Http\ZeiterfassungController::clicktimeNr($person);
        $name = $this->name($person);

        return MonatsblattPdf::erstellen($name, $monat, $liste, Zeitkonto::summen($liste),
            isset($status['visiert_by']) ? $this->name((string) $status['visiert_by']) : null,
            is_string($status['visiert_at'] ?? null) ? $status['visiert_at'] : null,
            $unterzeichnende ?? ['person' => $name, 'schulleitung' => ''],
            array_map(fn (array $b): string => $b['name'], $this->buchungsarten->liste()),
            [
                'vermerke' => $vermerke,
                'unterschriftsfelder' => $unterzeichnende !== null,
                'soll' => $this->sollzeiten->monat($person, $monat),
                'untertitel' => trim(($nr ?? '').' '.$name),
                'salden' => $nr !== null ? $this->salden($nr, $monat) : [],
            ]);
    }

    /**
     * Saldi aus ClickTime: Stand Ende Vormonat und Ende Monat (z. B. Gleitzeit, Ferien). Ohne Verbindung leer.
     *
     * @return list<array{name: string, vormonat: float|null, ende: float|null, einheit: string}>
     */
    private function salden(string $nr, string $monat): array
    {
        $ende = date('Y-m-t', (int) strtotime($monat.'-01'));
        $vormonat = date('Y-m-d', (int) strtotime($monat.'-01 -1 day'));
        try {
            /** @var array{0: list<array{name: string, value: float, unit: string}>, 1: list<array{name: string, value: float, unit: string}>} $werte */
            $werte = app(PluginManager::class)->runWith('clicktime', null, 'Zeiterfassung: Saldi für das Monatsblatt', function (PluginContext $ctx) use ($nr, $ende, $vormonat): array {
                $months = new Months($ctx);

                return [$months->balances($vormonat, [$nr])[$nr] ?? [], $months->balances($ende, [$nr])[$nr] ?? []];
            });
        } catch (Throwable) {
            return [];
        }
        $salden = [];
        foreach ([0 => 'vormonat', 1 => 'ende'] as $i => $feld) {
            foreach ($werte[$i] as $b) {
                $salden[$b['name']] ??= ['name' => $b['name'], 'vormonat' => null, 'ende' => null, 'einheit' => $b['unit'] === 'd' ? 'Tage' : ($b['unit'] === 'h' ? 'h' : $b['unit'])];
                $salden[$b['name']][$feld] = $b['value'];
            }
        }

        return array_values($salden);
    }

    private function name(string $id): string
    {
        return (string) ($this->users->find($id)['name'] ?? '');
    }

    private static function zeit(mixed $iso): string
    {
        $t = is_string($iso) ? strtotime($iso) : false;

        return $t === false ? '–' : date('d.m.Y H:i', $t);
    }
}
