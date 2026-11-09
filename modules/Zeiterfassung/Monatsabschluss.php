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
use RuntimeException;

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
        $status = $this->monate->status($person, $monat);
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);
        $summen = Zeitkonto::summen($liste);
        $vermerke = [];
        if (is_string($status['eingereicht_at'] ?? null)) {
            $vermerke[] = 'Eingereicht am '.self::zeit($status['eingereicht_at']);
        }
        if (($status['status'] ?? '') === 'abgeschlossen') {
            $by = (string) ($status['abgeschlossen_by'] ?? '');
            $vermerke[] = 'In ClickTime abgeschlossen; bestätigt von '.$this->name($by).' am '.self::zeit($status['abgeschlossen_at'] ?? null)
                .(($status['abgeschlossen_stellvertretend'] ?? false) === true ? ' (stellvertretend)' : '');
        }
        $soll = array_sum($this->sollzeiten->monat($person, $monat));
        if ($soll > 0) {
            $delta = $summen['total'] - $soll;
            $vermerke[] = sprintf('Soll %s h · Ist %s h · Differenz %s%s h', self::zahl($soll), self::zahl($summen['total']), $delta >= 0 ? '+' : '−', self::zahl(abs($delta)));
        }
        $visiertVon = isset($status['visiert_by']) ? $this->name((string) $status['visiert_by']) : null;
        $dokument = MonatsblattPdf::erstellen($this->name($person), $monat, $liste, $summen, $visiertVon,
            is_string($status['visiert_at'] ?? null) ? $status['visiert_at'] : null, ['person' => $this->name($person), 'schulleitung' => ''],
            array_map(fn (array $b): string => $b['name'], $this->buchungsarten->liste()), $vermerke, false);
        $pfad = self::datei($person, $monat);
        if (! Storage::disk('local')->put($pfad, $dokument['pdf'])) {
            throw new RuntimeException('Monatsblatt konnte nicht abgelegt werden.');
        }
        $this->monate->setzen($person, $monat, ['monatsblatt_pdf' => $pfad, 'monatsblatt_at' => now()->toIso8601String()]);

        return $pfad;
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

    private static function zahl(float $h): string
    {
        return number_format($h, 2, '.', "'");
    }
}
