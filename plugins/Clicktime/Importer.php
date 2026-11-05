<?php

namespace Plugins\Clicktime;

use App\Plugins\PluginContext;
use App\Plugins\PluginManager;
use Modules\Zeiterfassung\Buchungsarten;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Modules\Zeiterfassung\Kategorien;
use Modules\Zeiterfassung\Zeitkonto;
use RuntimeException;

/**
 * Buchungen eines Monats aus ClickTime in die eigene Zeiterfassung übernehmen (#101).
 *
 * - Herkunft je Eintrag: quelle = clicktime, quelle_id = ClickTime-Nr. der Buchung. Erneutes Übernehmen aktualisiert,
 *   in ClickTime gelöschte Buchungen werden entfernt; von Hand erfasste Einträge bleiben unberührt.
 * - Buchungsart über die Mapping-Tabelle; unbekannte ClickTime-Arten werden als «Name (clicktime)» angelegt und
 *   ergänzt. Kategorie bei Arbeit: «ct:<ClickTime-Name>» (Anzeige «Name (clicktime)»).
 * - Pause (eigene Buchung, negative Stunden) → Pause des längsten Arbeitsblocks am Tag.
 * - Nur Monate «offen» oder «zurück».
 */
final class Importer
{
    public const QUELLE = 'clicktime';

    /** Verglichene Felder eines Eintrags. */
    private const FIELDS = ['datum', 'art', 'buchungsart', 'kategorie', 'beginn', 'ende', 'pause_min', 'stunden', 'notiz'];

    public function __construct(private readonly PluginContext $ctx) {}

    /**
     * @return array{changes: list<array{change: string, day: string, before: string, after: string}>, counts: array<string, int>, created_types: list<string>}
     */
    public function run(string $personId, string $employeeNo, string $month, bool $apply): array
    {
        $months = app(MonatRepository::class);
        $status = (string) ($months->status($personId, $month)['status'] ?? 'offen');
        if (Zeitkonto::gesperrt($status)) {
            throw new RuntimeException("Der Monat {$month} ist ".($status === 'visiert' ? 'visiert' : 'eingereicht').' – Übernehmen nicht möglich.');
        }

        $buchungsarten = app(Buchungsarten::class);
        $own = $buchungsarten->liste();
        $mapping = Mapping::parse((string) $this->ctx->setting('mapping', Mapping::DEFAULT));
        $entries = (new Months($this->ctx))->entries($month, [$employeeNo])[$employeeNo] ?? [];

        // Unbekannte Buchungsarten: «Name (clicktime)» anlegen und in der Mapping-Tabelle ergänzen
        $created = [];
        foreach ($entries as $e) {
            $target = Mapping::target($mapping, (string) $e['type'], (string) $e['type_name']);
            if ($target !== null && ($target === Mapping::PAUSE || $target === Mapping::SKIP || isset($own[$target]))) {
                continue;
            }
            $name = (string) $e['type_name'];
            $key = 'ct_'.Buchungsarten::schluessel($name);
            $art = in_array((string) $e['category'], ['WORK', ''], true) ? 'arbeit' : 'abwesenheit';
            if ($apply) {
                $buchungsarten->hinzufuegen($key, $name.' (clicktime)', $art);
            }
            $own[$key] = ['name' => $name.' (clicktime)', 'art' => $art, 'aktiv' => true];
            $mapping[Mapping::key($name)] = ['name' => $name, 'target' => $key];
            $created[] = $name;
        }
        if ($created !== [] && $apply) {
            $manager = app(PluginManager::class);
            $id = $this->ctx->manifest->id();
            $manager->saveSettings($id, ['mapping' => Mapping::format($mapping)] + $manager->state($id)['settings']);
        }

        // Ziel-Einträge aus ClickTime
        /** @var array<string, array<string, mixed>> $wanted */
        $wanted = [];
        $pause = [];
        foreach ($entries as $e) {
            $target = (string) Mapping::target($mapping, (string) $e['type'], (string) $e['type_name']);
            if ($target === Mapping::SKIP) {
                continue;
            }
            if ($target === Mapping::PAUSE) {
                $pause[(string) $e['day']] = ($pause[(string) $e['day']] ?? 0) + (float) $e['hours'];

                continue;
            }
            $art = $own[$target]['art'];
            $wanted[(string) $e['number']] = [
                'datum' => (string) $e['day'],
                'monat' => $month,
                'art' => $art,
                'buchungsart' => $target,
                'kategorie' => $art === 'arbeit' ? Kategorien::CLICKTIME.$e['type_name'] : Buchungsarten::kategorieFuer($target),
                'beginn' => $e['time_from'] !== '' ? (string) $e['time_from'] : null,
                'ende' => $e['time_to'] !== '' ? (string) $e['time_to'] : null,
                'pause_min' => null,
                'stunden' => round((float) $e['hours'], 2),
                'notiz' => ($e['notes'] ?? '') !== '' ? mb_substr((string) $e['notes'], 0, 500) : null,
            ];
        }
        foreach ($pause as $day => $hours) {
            $longest = null;
            foreach ($wanted as $nr => $w) {
                if ($w['datum'] === $day && $w['art'] === 'arbeit' && ($longest === null || (float) $w['stunden'] > (float) $wanted[$longest]['stunden'])) {
                    $longest = $nr;
                }
            }
            if ($longest !== null) {
                $wanted[$longest]['pause_min'] = (int) round(-$hours * 60);
                $wanted[$longest]['stunden'] = round((float) $wanted[$longest]['stunden'] + $hours, 2);
            }
        }

        // Vergleich mit bereits übernommenen Einträgen
        $repo = app(EintragRepository::class);
        $existing = [];
        foreach ($repo->fuerPersonMonat($personId, $month) as $row) {
            if (($row['quelle'] ?? null) === self::QUELLE && (string) ($row['quelle_id'] ?? '') !== '') {
                $existing[(string) $row['quelle_id']] = $row;
            }
        }
        $changes = [];
        $counts = ['neu' => 0, 'geändert' => 0, 'unverändert' => 0, 'gelöscht' => 0];
        foreach ($wanted as $nr => $w) {
            $old = $existing[$nr] ?? null;
            if ($old === null) {
                $counts['neu']++;
                $changes[] = ['change' => 'neu', 'day' => (string) $w['datum'], 'before' => '–', 'after' => self::describe($w, $own)];
                if ($apply) {
                    $repo->create(['person_id' => $personId, 'projekt' => null, 'quelle' => self::QUELLE, 'quelle_id' => (string) $nr] + $w);
                }
            } elseif (self::differs($old, $w)) {
                $counts['geändert']++;
                $changes[] = ['change' => 'geändert', 'day' => (string) $w['datum'], 'before' => self::describe($old, $own), 'after' => self::describe($w, $own)];
                if ($apply) {
                    $repo->update((string) $old['id'], $w);
                }
            } else {
                $counts['unverändert']++;
            }
        }
        foreach ($existing as $nr => $old) {
            if (! isset($wanted[$nr])) {
                $counts['gelöscht']++;
                $changes[] = ['change' => 'gelöscht', 'day' => (string) $old['datum'], 'before' => self::describe($old, $own), 'after' => '–'];
                if ($apply) {
                    $repo->delete((string) $old['id']);
                }
            }
        }
        usort($changes, fn (array $a, array $b): int => [$a['day'], $a['change']] <=> [$b['day'], $b['change']]);
        $this->ctx->log(($apply ? 'Übernommen' : 'Vorschau').": {$counts['neu']} neu, {$counts['geändert']} geändert, {$counts['gelöscht']} gelöscht.");

        return ['changes' => $changes, 'counts' => $counts, 'created_types' => $created];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function differs(array $a, array $b): bool
    {
        foreach (self::FIELDS as $f) {
            $x = $a[$f] ?? null;
            $y = $b[$f] ?? null;
            if ($f === 'stunden' ? abs((float) $x - (float) $y) > 0.004 : (string) ($x ?? '') !== (string) ($y ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $e
     * @param  array<string, array{name: string, art: string, aktiv: bool}>  $own
     */
    private static function describe(array $e, array $own): string
    {
        $b = (string) ($e['buchungsart'] ?? '');
        $time = ($e['beginn'] ?? null) ? " {$e['beginn']}–{$e['ende']}" : '';
        $pause = ($e['pause_min'] ?? null) ? " (−{$e['pause_min']} min)" : '';

        return ($own[$b]['name'] ?? $b).' '.number_format((float) ($e['stunden'] ?? 0), 2, '.', "'").' h'.$time.$pause;
    }
}
