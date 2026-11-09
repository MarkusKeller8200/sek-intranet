<?php

namespace App\Http\Controllers;

use App\Auth\IntranetUser;
use App\Data\Contracts\GlossaryRepository;
use App\Plugins\PluginManager;
use App\Support\Glossary;
use App\Support\GlossaryProposals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Glossar ↔ EduKSX-Glossary im Glossar selbst (Recht «Glossar pflegen»): Stand, Probelauf und Sync (#161),
 * Vorschläge neuer Begriffe und Korrekturen (#162). Die Abrufe macht das Plugin «eduksx-glossary».
 */
class GlossarySyncController extends Controller
{
    public const PLUGIN = 'eduksx-glossary';

    public function __construct(
        private readonly PluginManager $plugins,
        private readonly GlossaryRepository $glossary,
    ) {}

    public function show(): Response
    {
        return Inertia::render('glossary/sync', ['sync' => $this->status(), 'ergebnis' => null]);
    }

    /** Probelauf: zeigt, was sich ändern würde, speichert nichts. */
    public function dryRun(Request $request): Response
    {
        $data = $request->validate(['neu' => ['nullable', 'boolean']]);

        return Inertia::render('glossary/sync', [
            'sync' => $this->status(),
            'ergebnis' => $this->run($request, ['probelauf' => true, 'neu' => (bool) ($data['neu'] ?? false)]),
        ]);
    }

    public function sync(Request $request): RedirectResponse
    {
        $data = $request->validate(['neu' => ['nullable', 'boolean']]);
        $result = $this->run($request, ['neu' => (bool) ($data['neu'] ?? false)]);
        $this->toast((string) $result['message'], ($result['status'] ?? '') === 'success' ? 'success' : 'error');

        return redirect('/glossar/sync');
    }

    public function proposeForm(string $id): Response
    {
        $entry = $this->glossary->findOrFail($id);
        $proposals = new GlossaryProposals($this->glossary);
        $names = $this->glossaryCategories($proposals);

        return Inertia::render('glossary/propose', [
            'eintrag' => ['id' => $entry['id'], 'titel' => trim(Glossary::shortName($entry).' – '.Glossary::displayName($entry), ' –'), 'sync' => is_array($entry['glossary'] ?? null)],
            'werte' => $proposals->defaults($entry),
            'kategorien' => $names,
            'felder' => GlossaryProposals::LABELS,
            'sync' => $this->status(),
        ]);
    }

    public function propose(Request $request, string $id): RedirectResponse
    {
        $this->glossary->findOrFail($id);
        $data = $request->validate([
            'werte' => ['required', 'array'],
            'begruendung' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = $this->run($request, ['eintrag' => $id, 'begruendung' => (string) ($data['begruendung'] ?? ''), '_changes' => (array) $data['werte']], 'propose');
        if (($result['status'] ?? '') !== 'success') {
            $this->toast((string) $result['message'], 'error');

            return redirect('/glossar/'.$id.'/vorschlag');
        }
        $this->toast((string) $result['message']);

        return redirect('/glossar/'.$id);
    }

    /**
     * Stand für die Seiten: eingeschaltet, automatisch, Adresse, Token vorhanden (nur ja/nein), letzter Sync.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        if (! isset($this->plugins->all()[self::PLUGIN])) {
            return ['vorhanden' => false];
        }
        $state = $this->plugins->state(self::PLUGIN);
        $default = '';
        foreach ($this->plugins->manifest(self::PLUGIN)->settings() as $setting) {
            if (($setting['key'] ?? '') === 'base_url') {
                $default = (string) ($setting['default'] ?? '');
            }
        }
        $sync = $this->glossary->syncState();

        return [
            'vorhanden' => true,
            'eingeschaltet' => $state['enabled'],
            'automatisch' => ($state['settings']['auto'] ?? false) === true,
            'schreiben' => $state['allow_write'],
            'adresse' => is_string($state['settings']['base_url'] ?? null) && $state['settings']['base_url'] !== '' ? $state['settings']['base_url'] : $default,
            'token' => ($state['secrets']['token'] ?? '') !== '',
            'stand' => [
                'cursor' => isset($sync['cursor']) ? (int) $sync['cursor'] : null,
                'release' => isset($sync['release']) ? (int) $sync['release'] : null,
                'letzter_erfolg' => is_string($sync['letzter_erfolg'] ?? null) ? $sync['letzter_erfolg'] : null,
                'letzter_abruf' => is_string($sync['letzter_abruf'] ?? null) ? $sync['letzter_abruf'] : null,
                'fehler' => is_string($sync['fehler'] ?? null) && $sync['fehler'] !== '' ? $sync['fehler'] : null,
            ],
            'admin' => Gate::allows('manage-users'),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function run(Request $request, array $options, string $action = 'sync'): array
    {
        $user = $request->user();
        try {
            return $this->plugins->runAction(self::PLUGIN, $action, $options, $user instanceof IntranetUser ? $user : null);
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => 'EduKSX-Glossary: '.$e->getMessage(), 'columns' => [], 'rows' => []];
        }
    }

    /**
     * Kategorien mit Schlüssel im Glossary: Schlüssel → «Label – Bezeichnung».
     *
     * @return array<string, string>
     */
    private function glossaryCategories(GlossaryProposals $proposals): array
    {
        $items = array_column($this->glossary->listItems('kategorien'), null, 'id');
        $names = [];
        foreach ($proposals->categoryKeys() as $id => $key) {
            $item = $items[$id] ?? null;
            $names[$key] = $item !== null ? trim((string) ($item['label'] ?? '').' – '.(string) ($item['bezeichnung'] ?? ''), ' –') : $key;
        }

        return $names;
    }
}
