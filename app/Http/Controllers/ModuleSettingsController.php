<?php

namespace App\Http\Controllers;

use App\Support\AuditLog;
use App\Support\FrameCheck;
use App\Support\ModuleNavigation;
use App\Support\ModuleSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menü «Einstellungen»: Einstellungen der einzelnen Menüpunkte. Heute: Fachanwendungen und weitere
 * Einbindungen (Adresse, Art des Öffnens: Rahmen, Rahmen mit Tab, eigenes Fenster, Browser-Erweiterung).
 */
class ModuleSettingsController extends Controller
{
    /** Bereiche der Einstellungsseite → Gruppen der Navigation (null = alle übrigen) */
    private const SECTIONS = [
        'fachanwendungen' => ['title' => 'Fachanwendungen', 'groups' => ['fachanwendungen']],
        'einbindungen' => ['title' => 'Weitere Einbindungen', 'groups' => null],
    ];

    public function __construct(
        private readonly ModuleNavigation $navigation,
        private readonly ModuleSettings $settings,
        private readonly AuditLog $audit,
    ) {}

    public function index(string $section): Response
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);
        $groups = self::SECTIONS[$section]['groups'];
        $others = array_merge(...array_values(array_filter(array_map(fn (array $s): ?array => $s['groups'], self::SECTIONS))));

        $items = array_values(array_filter(
            $this->navigation->embedSettings(),
            fn (array $i): bool => $groups !== null ? in_array($i['group'], $groups, true) : ! in_array($i['group'], $others, true),
        ));

        return Inertia::render('module-settings', [
            'section' => $section,
            'title' => self::SECTIONS[$section]['title'],
            'items' => $items,
            'modes' => ModuleSettings::OPEN_MODES,
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $this->known($key);
        $data = $request->validate([
            'url' => ['nullable', 'string', 'max:2000', 'regex:#^https://[^\s<>"]+$#'],
            'open' => ['required', Rule::in(ModuleSettings::OPEN_MODES)],
        ], ['url.regex' => 'Nur https-Adressen sind erlaubt.']);

        $result = $this->settings->save($key, $data['url'] ?? null, $data['open'], (string) $request->user()?->getAuthIdentifier());
        $this->audit->record(ModuleSettings::COLLECTION, $result['before'], $result['after'], 'Einstellungen', $request->user());

        $this->toast('Einstellung gespeichert.');

        return back();
    }

    public function destroy(Request $request, string $key): RedirectResponse
    {
        $this->known($key);
        $before = $this->settings->reset($key);
        if ($before !== null) {
            $this->audit->record(ModuleSettings::COLLECTION, $before, null, 'Einstellungen', $request->user());
        }

        $this->toast('Vorgabe wiederhergestellt.');

        return back();
    }

    /** «Einbetten prüfen»: Kopfzeilen der Seite lesen und erklären, ob und warum sie sich nicht einbetten lässt. */
    public function check(string $key): JsonResponse
    {
        $this->known($key);
        $item = collect($this->navigation->embedSettings())->firstWhere('key', $key);
        $url = is_array($item) ? $item['url'] : null;
        if (! is_string($url)) {
            return response()->json(['embeddable' => null, 'reason' => 'Noch keine Adresse eingetragen.']);
        }

        return response()->json(FrameCheck::fresh($url) + ['url' => $url]);
    }

    private function known(string $key): void
    {
        abort_unless(in_array($key, array_column($this->navigation->embedSettings(), 'key'), true), 404);
    }
}
