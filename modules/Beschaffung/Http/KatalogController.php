<?php

namespace Modules\Beschaffung\Http;

use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Beschaffung\Artikel;
use Modules\Beschaffung\Data\Contracts\ArtikelRepository;
use Modules\Beschaffung\Kataloge;

/**
 * Kataloge unter «Bestellen» (#198): Übersicht, Tabelle je Katalog, Bearbeiten direkt in der Tabelle.
 */
class KatalogController extends Controller
{
    /** Höchstens so viele Zellen auf einmal (Einfügen aus Excel). */
    private const MAX_CELLS = 2000;

    public function __construct(
        private readonly ArtikelRepository $artikel,
        private readonly AuditLog $audit,
    ) {}

    public function index(): Response
    {
        $zahlen = $this->artikel->zaehlen();
        $kataloge = [];
        foreach (Kataloge::ALLE as $key => $k) {
            $kataloge[] = [
                'key' => $key,
                'title' => $k['title'],
                'bereich' => $k['bereich'],
                'icon' => $k['icon'],
                'description' => $k['description'],
                'anzahl' => $zahlen[$key]['anzahl'] ?? 0,
                'pruefen' => $zahlen[$key]['pruefen'] ?? 0,
            ];
        }

        return Inertia::render('Beschaffung::index', [
            'kataloge' => $kataloge,
            'darfBearbeiten' => Gate::allows('edit-beschaffung'),
        ]);
    }

    public function show(string $katalog, PluginManager $plugins): Response
    {
        abort_unless(Kataloge::existiert($katalog), 404);
        $def = Kataloge::ALLE[$katalog];
        $darf = Gate::allows('edit-beschaffung');

        $plugin = null;
        $id = $def['plugin'] ?? null;
        if ($id !== null && isset($plugins->all()[$id])) {
            $plugin = ['name' => $plugins->manifest($id)->name(), 'enabled' => $plugins->state($id)['enabled']];
        }

        return Inertia::render('Beschaffung::katalog', [
            'katalog' => ['key' => $katalog] + $def,
            'zeilen' => array_map(fn (array $a): array => self::darstellung($a), $this->artikel->katalog($katalog)),
            'felder' => Artikel::FELDER,
            'kosten' => Artikel::KOSTEN,
            'status' => Artikel::STATUS,
            'farben' => Artikel::FARBEN,
            'plugin' => $plugin,
            'darfBearbeiten' => $darf,
        ]);
    }

    /**
     * Zellen aus der Tabelle speichern – alles oder nichts.
     */
    public function zellen(Request $request, string $katalog): JsonResponse
    {
        abort_unless(Kataloge::existiert($katalog), 404);
        $changes = array_values(array_filter((array) $request->input('changes', []), 'is_array'));
        abort_if($changes === [] || count($changes) > self::MAX_CELLS, 422, 'Keine oder zu viele Änderungen.');

        $geplant = [];
        $fehler = [];
        foreach ($changes as $c) {
            $id = (string) ($c['id'] ?? '');
            $feld = (string) ($c['field'] ?? '');
            $vorher = isset($geplant[$id]) ? $geplant[$id][0] : $this->artikel->find($id);
            if ($vorher === null || ($vorher['katalog'] ?? '') !== $katalog) {
                $fehler[] = ['id' => $id, 'field' => $feld, 'message' => 'Eintrag nicht gefunden.'];

                continue;
            }
            [$wert, $meldung] = Artikel::wert($feld, $c['value'] ?? null);
            if ($meldung !== null) {
                $fehler[] = ['id' => $id, 'field' => $feld, 'message' => ($vorher['bezeichnung'] ?? '').': '.$meldung];

                continue;
            }
            $geplant[$id] = [$vorher, array_merge($geplant[$id][1] ?? [], [$feld => $wert])];
        }
        if ($fehler !== []) {
            return response()->json(['message' => count($fehler).' ungültige Werte – nichts gespeichert. '.$fehler[0]['message'], 'errors' => $fehler], 422);
        }

        $zeilen = [];
        foreach ($geplant as $id => [$vorher, $daten]) {
            $nachher = $this->artikel->update((string) $id, $daten + ['geaendert_von' => $request->user()?->getAuthIdentifier()]);
            $this->audit->record('bes_artikel', $vorher, $nachher, 'Beschaffung (Tabelle)', $request->user());
            $zeilen[] = self::darstellung($nachher);
        }

        return response()->json(['rows' => $zeilen]);
    }

    /**
     * «Neue Zeile»: leeren Eintrag am Ende des Katalogs anlegen.
     */
    public function zeile(Request $request, string $katalog): JsonResponse
    {
        abort_unless(Kataloge::existiert($katalog), 404);
        $positionen = array_map(fn (array $a): int => (int) ($a['position'] ?? 0), $this->artikel->katalog($katalog));
        $neu = $this->artikel->create(Artikel::leer($katalog, ($positionen === [] ? 0 : max($positionen)) + 1)
            + ['geaendert_von' => $request->user()?->getAuthIdentifier()]);
        $this->audit->record('bes_artikel', null, $neu, 'Beschaffung (Tabelle)', $request->user());

        return response()->json(['id' => $neu['id']]);
    }

    /**
     * Ausgewählte Einträge entfernen (nach Bestätigung im Dialog).
     */
    public function loeschen(Request $request, string $katalog): RedirectResponse
    {
        abort_unless(Kataloge::existiert($katalog), 404);
        $ids = (array) $request->validate(['ids' => ['required', 'array', 'max:1000'], 'ids.*' => ['string']])['ids'];
        $anzahl = 0;
        foreach ($ids as $id) {
            $eintrag = $this->artikel->find((string) $id);
            if ($eintrag === null || ($eintrag['katalog'] ?? '') !== $katalog) {
                continue;
            }
            BildController::dateiEntfernen($eintrag);
            $this->artikel->delete((string) $id);
            $this->audit->record('bes_artikel', $eintrag, null, 'Beschaffung (Tabelle)', $request->user());
            $anzahl++;
        }
        $this->toast($anzahl === 1 ? '1 Eintrag entfernt.' : "{$anzahl} Einträge entfernt.");

        return back();
    }

    /**
     * Darstellung eines Eintrags für die Tabelle.
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    public static function darstellung(array $a): array
    {
        $zeile = ['id' => (string) $a['id']];
        foreach (array_keys(Artikel::FELDER) as $feld) {
            $wert = $a[$feld] ?? '';
            $zeile[$feld] = match ($feld) {
                'pruefen' => ($wert === true),
                'preis' => is_numeric($wert) ? number_format((float) $wert, 2, '.', '') : '',
                'status' => is_string($wert) && $wert !== '' ? $wert : 'aktiv',
                default => is_scalar($wert) ? (string) $wert : '',
            };
        }
        $bild = (string) ($a['bild'] ?? '');
        $zeile['bild'] = $bild !== '' ? '/beschaffung/bild/'.$zeile['id'].'?v='.substr(md5($bild.($a['updated_at'] ?? '')), 0, 8) : '';
        $zeile['quelle'] = (string) ($a['quelle'] ?? '');

        return $zeile;
    }
}
