<?php

namespace App\Http\Controllers;

use App\Data\Contracts\GlossaryRepository;
use App\Plugins\Support\SpreadsheetReader;
use App\Support\AuditLog;
use App\Support\Glossary;
use App\Support\GridViews;
use App\Support\SpreadsheetWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Glossar (docs/KONZEPT-GLOSSAR.md, #53–#59, #62): Tabelle, Einzeldatensatz, Listen, Excel, Doppelte.
 */
class GlossaryController extends Controller
{
    private const GRID = 'glossary';

    private const MAX_CELLS = 2000;

    public function __construct(
        private readonly GlossaryRepository $glossary,
        private readonly Glossary $service,
        private readonly AuditLog $audit,
    ) {}

    public function index(Request $request, GridViews $views): Response
    {
        return Inertia::render('glossary/index', [
            'rows' => $this->service->rows(),
            'kategorien' => $this->service->listOptions('kategorien'),
            'kategorienNamen' => $this->service->labelNames('kategorien'),
            'tagFarben' => ['kategorien' => $this->service->tagColors('kategorien'), 'herkunft' => $this->service->tagColors('herkunft')],
            'herkunft' => $this->service->listOptions('herkunft'),
            'bereiche' => $this->service->areaOptions(),
            'status' => Glossary::STATUS,
            'felder' => Glossary::FIELDS,
            'views' => $views->for(self::GRID, $request->user()),
            'rechte' => $this->rights(),
            'doppelte' => count($this->service->duplicates()),
        ]);
    }

    public function show(string $id): Response
    {
        $entry = $this->glossary->findOrFail($id);
        $short = mb_strtolower(Glossary::shortName($entry));
        $others = array_values(array_filter(
            $this->glossary->all(),
            fn (array $e): bool => $e['id'] !== $id && $short !== '' && mb_strtolower(Glossary::shortName($e)) === $short,
        ));

        return Inertia::render('glossary/show', [
            'eintrag' => $entry,
            'andere' => array_map(fn (array $e): array => ['id' => $e['id'], 'abk' => Glossary::shortName($e), 'bez' => Glossary::displayName($e)], $others),
            'kategorien' => $this->service->listOptions('kategorien'),
            'herkunft' => $this->glossary->listItems('herkunft'),
            'bereiche' => $this->service->areaOptions(),
            'status' => Glossary::STATUS,
            'rechte' => $this->rights(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(string $id): Response
    {
        return $this->form($this->glossary->findOrFail($id));
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $this->formInput($request);
        $check = $this->service->check($input, null, true);
        if (isset($check['errors'])) {
            throw ValidationException::withMessages($check['errors']);
        }
        $data = $check['data'] + Glossary::blank();
        if (Glossary::shortName($data) === '' && Glossary::displayName($data) === '') {
            throw ValidationException::withMessages(['abk_eigen' => 'Mindestens eine Abkürzung oder Bezeichnung ist nötig.']);
        }
        $entry = $this->glossary->create($data);
        $this->audit->record('glossar', null, $entry, 'Glossar', $request->user());
        $this->toast('Eintrag gespeichert.');

        return redirect('/glossar/'.$entry['id']);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $before = $this->glossary->findOrFail($id);
        $check = $this->service->check($this->formInput($request), $before, Gate::allows('edit-glossary-official'));
        if (isset($check['errors'])) {
            throw ValidationException::withMessages($check['errors']);
        }
        $after = $this->glossary->update($id, $check['data']);
        $this->audit->record('glossar', $before, $after, 'Glossar', $request->user());
        $this->toast('Eintrag gespeichert.');

        return redirect('/glossar/'.$id);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $before = $this->glossary->findOrFail($id);
        $this->glossary->delete($id);
        $this->audit->record('glossar', $before, null, 'Glossar', $request->user());
        $this->toast('Eintrag gelöscht.');

        return redirect('/glossar');
    }

    /** Mehrere Einträge auf einmal löschen (Auswahl in der Tabelle, #71). */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:1000'], 'ids.*' => ['string']]);
        $deleted = 0;
        foreach (array_unique($data['ids']) as $id) {
            $before = $this->glossary->find((string) $id);
            if ($before !== null) {
                $this->glossary->delete((string) $id);
                $this->audit->record('glossar', $before, null, 'Glossar (Auswahl gelöscht)', $request->user());
                $deleted++;
            }
        }
        $this->toast($deleted === 1 ? '1 Eintrag gelöscht.' : "{$deleted} Einträge gelöscht.");

        return redirect('/glossar');
    }

    /** Neue Zeile direkt in der Tabelle (JSON). */
    public function newRow(Request $request): JsonResponse
    {
        $entry = $this->glossary->create(['status' => 'zu_pruefen'] + Glossary::blank());
        $this->audit->record('glossar', null, $entry, 'Glossar (Tabelle)', $request->user());

        return response()->json(['id' => $entry['id']]);
    }

    /**
     * Zellen der Tabelle speichern (alles oder nichts).
     */
    public function cells(Request $request): JsonResponse
    {
        $changes = array_values(array_filter((array) $request->input('changes', []), 'is_array'));
        abort_if($changes === [] || count($changes) > self::MAX_CELLS, 422, 'Keine oder zu viele Änderungen.');
        $byEntry = [];
        foreach ($changes as $c) {
            $byEntry[(string) ($c['id'] ?? '')][(string) ($c['field'] ?? '')] = $c['value'] ?? null;
        }
        $official = Gate::allows('edit-glossary-official');
        $planned = [];
        $errors = [];
        foreach ($byEntry as $id => $fields) {
            $existing = $this->glossary->find((string) $id);
            if ($existing === null) {
                $errors[] = ['id' => (string) $id, 'field' => '', 'message' => 'Eintrag nicht gefunden.'];

                continue;
            }
            $check = $this->service->check($fields, $existing, $official);
            if (isset($check['errors'])) {
                foreach ($check['errors'] as $field => $message) {
                    $errors[] = ['id' => (string) $id, 'field' => $field, 'message' => $message];
                }

                continue;
            }
            $planned[(string) $id] = [$existing, $check['data']];
        }
        if ($errors !== []) {
            return response()->json(['message' => count($errors).' ungültige Werte – nichts gespeichert. '.$errors[0]['message'], 'errors' => $errors], 422);
        }
        foreach ($planned as $id => [$before, $data]) {
            $after = $this->glossary->update((string) $id, $data);
            $this->audit->record('glossar', $before, $after, 'Glossar (Tabelle)', $request->user());
        }
        $ids = array_keys($planned);

        return response()->json(['rows' => array_values(array_filter($this->service->rows(), fn (array $r): bool => in_array($r['id'], $ids, true)))]);
    }

    // ---------- Listen Kategorien und Herkunft ----------

    public function lists(Request $request): Response
    {
        $list = $request->string('liste')->toString() === 'herkunft' ? 'herkunft' : 'kategorien';
        $used = [];
        foreach ($this->glossary->all() as $e) {
            foreach ((array) ($e[$list] ?? []) as $id) {
                $used[(string) $id] = ($used[(string) $id] ?? 0) + 1;
            }
        }

        return Inertia::render('glossary/lists', [
            'liste' => $list,
            'eintraege' => array_map(fn (array $i): array => $i + ['verwendet' => $used[(string) $i['id']] ?? 0], $this->glossary->listItems($list)),
        ]);
    }

    public function storeListItem(Request $request, string $list): RedirectResponse
    {
        $this->glossary->saveListItem($this->listKey($list), null, $this->listInput($request, $list, null));
        $this->toast('Gespeichert.');

        return redirect('/glossar/listen?liste='.$list);
    }

    public function updateListItem(Request $request, string $list, string $id): RedirectResponse
    {
        $this->glossary->saveListItem($this->listKey($list), $id, $this->listInput($request, $list, $id));
        $this->toast('Gespeichert.');

        return redirect('/glossar/listen?liste='.$list);
    }

    public function destroyListItem(string $list, string $id): RedirectResponse
    {
        $this->listKey($list);
        $used = count(array_filter($this->glossary->all(), fn (array $e): bool => in_array($id, (array) ($e[$list] ?? []), true)));
        if ($used > 0) {
            $this->toast("Wird noch von {$used} Einträgen verwendet und kann nicht gelöscht werden.", 'error');
        } else {
            $this->glossary->deleteListItem($list, $id);
            $this->toast('Gelöscht.');
        }

        return redirect('/glossar/listen?liste='.$list);
    }

    // ---------- Doppelte ----------

    public function duplicates(): Response
    {
        $all = array_column($this->glossary->all(), null, 'id');

        return Inertia::render('glossary/duplicates', [
            'gruppen' => array_map(fn (array $g): array => $g + ['eintraege' => array_map(fn (string $id): array => $all[$id], $g['ids'])], $this->service->duplicates()),
            'kategorien' => $this->service->listOptions('kategorien'),
            'herkunft' => $this->service->listOptions('herkunft'),
            'felder' => Glossary::FIELDS,
        ]);
    }

    public function merge(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2'],
            'ids.*' => ['string'],
            'ziel' => ['required', 'string'],
            'auswahl' => ['nullable', 'array'],
        ]);
        $ids = array_values(array_map('strval', (array) $data['ids']));
        $before = array_map(fn (string $id): ?array => $this->glossary->find($id), $ids);
        try {
            $merged = $this->service->merge($ids, (string) $data['ziel'], array_map('strval', (array) ($data['auswahl'] ?? [])));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['ids' => $e->getMessage()]);
        }
        foreach ($before as $b) {
            if ($b !== null && $b['id'] !== $merged['id']) {
                $this->audit->record('glossar', $b, null, 'Glossar (zusammengeführt in '.Glossary::shortName($merged).')', $request->user());
            }
        }
        $this->audit->record('glossar', $before[array_search($merged['id'], $ids, true)] ?? null, $merged, 'Glossar (zusammengeführt)', $request->user());
        $this->toast(count($ids).' Einträge zusammengeführt.');

        return redirect('/glossar/doppelte');
    }

    public function keepApart(Request $request): RedirectResponse
    {
        $data = $request->validate(['key' => ['required', 'string', 'max:300'], 'ids' => ['required', 'array', 'min:2'], 'ids.*' => ['string']]);
        $this->service->keepApart((string) $data['key'], array_values(array_map('strval', (array) $data['ids'])));
        $this->toast('Als mehrdeutig belassen.');

        return redirect('/glossar/doppelte');
    }

    // ---------- Excel ----------

    public function export(Request $request): HttpResponse
    {
        $data = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['string'], 'vorlage' => ['nullable', 'boolean']]);
        $header = ['ID', ...array_values(Glossary::FIELDS)];
        $rows = [];
        if (! $request->boolean('vorlage')) {
            $ids = isset($data['ids']) ? array_flip(array_map('strval', (array) $data['ids'])) : null;
            foreach ($this->service->rows() as $row) {
                if ($ids !== null && ! isset($ids[$row['id']])) {
                    continue;
                }
                $rows[] = [(string) $row['id'], ...array_map(fn (string $f): string => (string) ($row[$f] ?? ''), array_keys(Glossary::FIELDS))];
            }
        }
        $name = $request->boolean('vorlage') ? 'glossar-vorlage' : 'glossar-'.date('Y-m-d');

        return response(SpreadsheetWriter::xlsx($header, $rows, 'Glossar'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xlsx"',
        ]);
    }

    /** Import hochladen → Vorschau (neu / geändert / Fehler), nichts wird gespeichert. */
    public function importPreview(Request $request): Response|RedirectResponse
    {
        $request->validate(['datei' => ['required', 'file', 'max:5120']]);
        $file = $request->file('datei');
        abort_if($file === null || is_array($file), 422);
        try {
            $table = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalName(), ['ID', ...array_values(Glossary::FIELDS)]);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['datei' => $e->getMessage()]);
        }
        $byLabel = array_flip(Glossary::FIELDS);
        $official = Gate::allows('edit-glossary-official');
        $plan = [];
        foreach ($table['rows'] as $n => $row) {
            $id = trim($row['ID'] ?? '');
            $fields = [];
            foreach ($row as $label => $value) {
                if (isset($byLabel[$label])) {
                    $fields[$byLabel[$label]] = $value;
                }
            }
            $existing = $id !== '' ? $this->glossary->find($id) : null;
            if ($id !== '' && $existing === null) {
                $plan[] = ['zeile' => $n + 2, 'art' => 'fehler', 'id' => $id, 'titel' => $row['Abkürzung offiziell'] ?? '', 'meldungen' => ['ID gibt es nicht (Zeile ohne ID = neuer Eintrag).'], 'aenderungen' => []];

                continue;
            }
            $check = $this->service->check($fields, $existing, $official || $existing === null);
            if (isset($check['errors'])) {
                $plan[] = ['zeile' => $n + 2, 'art' => 'fehler', 'id' => $id, 'titel' => ($fields['abk_offiziell'] ?? '') ?: ($fields['abk_eigen'] ?? ''), 'meldungen' => array_values($check['errors']), 'aenderungen' => []];

                continue;
            }
            $changes = $existing === null
                ? array_filter($check['data'], fn ($v): bool => $v !== '' && $v !== [] && $v !== null)
                : array_filter($check['data'], fn ($v, string $f): bool => ($existing[$f] ?? (in_array($f, Glossary::MULTI, true) ? [] : '')) != $v, ARRAY_FILTER_USE_BOTH);
            if ($existing === null && Glossary::shortName($changes) === '' && Glossary::displayName($changes) === '') {
                $plan[] = ['zeile' => $n + 2, 'art' => 'fehler', 'id' => '', 'titel' => '', 'meldungen' => ['Abkürzung oder Bezeichnung fehlt.'], 'aenderungen' => []];

                continue;
            }
            if ($existing !== null && $changes === []) {
                continue;
            }
            $plan[] = [
                'zeile' => $n + 2,
                'art' => $existing === null ? 'neu' : 'geaendert',
                'id' => $id,
                'titel' => trim(Glossary::shortName($changes + ($existing ?? [])).' – '.Glossary::displayName($changes + ($existing ?? [])), ' –'),
                'meldungen' => [],
                'aenderungen' => array_map(fn ($v): string => is_array($v) ? implode('; ', $v) : (string) $v, $changes),
                'daten' => $changes,
            ];
        }
        $token = (string) Str::uuid();
        Cache::put('glossar-import:'.$token, ['user' => $request->user()?->getAuthIdentifier(), 'plan' => $plan], now()->addMinutes(30));

        return Inertia::render('glossary/import', [
            'token' => $token,
            'datei' => $file->getClientOriginalName(),
            'plan' => array_map(fn (array $p): array => array_diff_key($p, ['daten' => true]), $plan),
            'felder' => Glossary::FIELDS,
        ]);
    }

    /** Vorschau übernehmen: neue anlegen, geänderte aktualisieren, Fehler überspringen. Nie löschen. */
    public function importApply(Request $request, string $token): RedirectResponse
    {
        $cached = Cache::pull('glossar-import:'.$token);
        abort_if(! is_array($cached) || ($cached['user'] ?? null) !== $request->user()?->getAuthIdentifier(), 404);
        $new = 0;
        $changed = 0;
        foreach ((array) $cached['plan'] as $p) {
            if (($p['art'] ?? '') === 'neu') {
                $entry = $this->glossary->create($p['daten'] + Glossary::blank());
                $this->audit->record('glossar', null, $entry, 'Glossar (Excel-Import)', $request->user());
                $new++;
            } elseif (($p['art'] ?? '') === 'geaendert' && ($before = $this->glossary->find((string) $p['id'])) !== null) {
                $after = $this->glossary->update((string) $p['id'], $p['daten']);
                $this->audit->record('glossar', $before, $after, 'Glossar (Excel-Import)', $request->user());
                $changed++;
            }
        }
        $this->toast("Import: {$new} neu, {$changed} geändert.");

        return redirect('/glossar');
    }

    // ---------- Hilfen ----------

    /**
     * @return array{edit: bool, official: bool}
     */
    private function rights(): array
    {
        return ['edit' => Gate::allows('edit-glossary'), 'official' => Gate::allows('edit-glossary-official')];
    }

    /**
     * @param  array<string, mixed>|null  $entry
     */
    private function form(?array $entry): Response
    {
        return Inertia::render('glossary/form', [
            'eintrag' => $entry,
            'kategorien' => $this->service->listOptions('kategorien'),
            'herkunft' => $this->service->listOptions('herkunft'),
            'bereiche' => $this->service->areaOptions(),
            'status' => Glossary::STATUS,
            'rechte' => $this->rights(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formInput(Request $request): array
    {
        return array_intersect_key($request->all(), Glossary::FIELDS);
    }

    private function listKey(string $list): string
    {
        abort_unless(in_array($list, ['kategorien', 'herkunft'], true), 404);

        return $list;
    }

    /**
     * @return array<string, string>
     */
    private function listInput(Request $request, string $list, ?string $id): array
    {
        $data = $request->validate([
            'kuerzel' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:30'],
            'farbe' => ['nullable', 'string', Rule::in(Glossary::COLORS)],
            'bezeichnung' => ['required', 'string', 'max:200'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'url:http,https', 'max:500'],
            'notiz' => ['nullable', 'string', 'max:5000'],
        ], ['url.url' => 'Die URL muss mit http:// oder https:// beginnen.']);
        foreach ($this->glossary->listItems($list) as $row) {
            if ($row['id'] !== $id && mb_strtolower((string) ($row['kuerzel'] ?? '')) === mb_strtolower(trim((string) $data['kuerzel']))) {
                throw ValidationException::withMessages(['kuerzel' => 'Diese Abkürzung gibt es schon.']);
            }
        }

        return array_map(fn ($v): string => trim((string) $v), $data + ['label' => '', 'farbe' => '', 'beschreibung' => '', 'url' => '', 'notiz' => '']);
    }
}
