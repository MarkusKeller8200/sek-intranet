<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use App\Support\Glossary;
use App\Support\Themes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Farbkonzept mit Theme-Auswahl: Themes anlegen (auch aus Vorlage), bearbeiten, für alle aktivieren, löschen.
 */
class ThemeController extends Controller
{
    public function __construct(private readonly Themes $themes, private readonly AuditLog $audit) {}

    public function index(): Response
    {
        $glossary = app(Glossary::class);
        $tags = fn (string $list): array => array_map(fn (array $i): array => ['label' => (string) ($i['label'] ?: $i['kuerzel']), 'bezeichnung' => (string) $i['bezeichnung'], 'farbe' => (string) $i['farbe']], $glossary->items($list));

        return Inertia::render('color-concept', $this->shared() + [
            'activeId' => $this->themes->activeId(),
            // Farbige Labels des Glossars (#74), Farbe je Eintrag unter Glossar → Kategorien & Herkunft
            'glossarTags' => ['kategorien' => $tags('kategorien'), 'herkunft' => $tags('herkunft')],
        ]);
    }

    public function create(Request $request): Response
    {
        $presets = (array) config('intranet.theme_presets');
        $from = (string) $request->query('vorlage', '');
        $copy = $from !== '' ? $this->themes->find($from) : null;
        $source = $presets[$from] ?? $copy;

        return Inertia::render('theme-editor', $this->shared() + ['theme' => [
            'id' => null,
            'name' => $source === null ? '' : (string) $source['name'].($copy !== null ? ' (Kopie)' : ''),
            'description' => (string) ($source['description'] ?? ''),
            'light' => (array) ($source['light'] ?? []),
            'dark' => (array) ($source['dark'] ?? []),
        ]]);
    }

    public function edit(string $id): Response
    {
        $theme = $this->themes->find($id);
        abort_if($theme === null || $theme['builtin'] === true, 404);

        return Inertia::render('theme-editor', $this->shared() + ['theme' => $theme]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        return $this->save($request, $id);
    }

    public function activate(Request $request, string $id): RedirectResponse
    {
        try {
            foreach ($this->themes->activate($id) as $change) {
                $this->audit->record(Themes::COLLECTION, $change['before'], $change['after'], 'Farbkonzept', $request->user());
            }
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }
        $this->toast('Theme ist jetzt für alle aktiv.');

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        try {
            $before = $this->themes->delete($id);
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }
        if ($before !== null) {
            $this->audit->record(Themes::COLLECTION, $before, null, 'Farbkonzept', $request->user());
        }
        $this->toast(($before['active'] ?? false) === true ? 'Theme gelöscht – es gilt wieder der Standard.' : 'Theme gelöscht.');

        return redirect('/farbkonzept');
    }

    private function save(Request $request, ?string $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'light' => ['array'],
            'dark' => ['array'],
        ], ['name.required' => 'Bitte einen Namen angeben.']);

        try {
            $result = $this->themes->save($id, $data['name'], (string) ($data['description'] ?? ''), (array) ($data['light'] ?? []), (array) ($data['dark'] ?? []), $request->user()?->getAuthIdentifier() !== null ? (string) $request->user()->getAuthIdentifier() : null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['light' => $e->getMessage()]);
        }
        $this->audit->record(Themes::COLLECTION, $result['before'], $result['after'], 'Farbkonzept', $request->user());
        $this->toast('Theme gespeichert.');

        return redirect('/farbkonzept/themes/'.$result['after']['id'].'/bearbeiten');
    }

    /**
     * @return array<string, mixed>
     */
    private function shared(): array
    {
        return [
            'themes' => $this->themes->all(),
            'defaults' => Themes::defaults(),
            'groups' => Themes::GROUPS,
            'presets' => array_map(fn (array $p): array => ['name' => $p['name'], 'description' => $p['description']], (array) config('intranet.theme_presets')),
        ];
    }
}
