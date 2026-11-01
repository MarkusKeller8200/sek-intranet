<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use App\Support\AuditLog;
use App\Support\GuiPrototypes;
use App\Support\Notifications;
use App\Support\Roles;
use App\Support\Themes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Menü «GUI-Prototypen»: Bausteine der Oberfläche mit Beispielen, Status und Versionen.
 * Eine freigegebene Version ist das verbindliche Template (siehe App\Support\GuiPrototypes).
 */
class GuiPrototypeController extends Controller
{
    public function __construct(
        private readonly GuiPrototypes $prototypes,
        private readonly UserRepository $users,
        private readonly AuditLog $audit,
        private readonly Notifications $notifications,
    ) {}

    /** Inhaltsverzeichnis: Bereiche mit Bausteinen, Erklärung, Bestandteilen und Status. */
    public function contents(): Response
    {
        $categories = [];
        foreach ((array) config('intranet.gui_categories') as $key => $category) {
            $categories[$key] = ['key' => $key, 'items' => []] + $category;
        }
        foreach (GuiPrototypes::components() as $key => $component) {
            $summary = GuiPrototypes::summary($this->prototypes->get($key));
            $categories[$component['category']]['items'][] = [
                'key' => $key,
                'title' => $component['title'],
                'icon' => $component['icon'],
                'description' => $component['description'],
                'parts' => $component['parts'],
                'released' => $summary['released']['version'] ?? null,
                'open' => $summary['open'] === null ? null : ['version' => $summary['open']['version'], 'status' => $summary['open']['status']],
            ];
        }

        return Inertia::render('gui-prototypes/contents', [
            'categories' => array_values($categories),
            'crosscutting' => (array) config('intranet.gui_crosscutting'),
            'later' => (array) config('intranet.gui_later'),
        ]);
    }

    /** Freigaben & Status aller Bausteine. */
    public function index(): Response
    {
        $items = [];
        $ids = [];
        foreach (GuiPrototypes::components() as $key => $component) {
            $record = $this->prototypes->get($key);
            $summary = GuiPrototypes::summary($record);
            $items[] = ['key' => $key, 'title' => $component['title'], 'icon' => $component['icon'], 'description' => $component['description'], 'category' => (string) (config('intranet.gui_categories.'.$component['category'].'.title') ?? '')] + $summary;
            $ids = [...$ids, ...self::userIds($record)];
        }

        return Inertia::render('gui-prototypes/index', ['items' => $items, 'names' => $this->names($ids)]);
    }

    public function show(Request $request, string $key): Response
    {
        abort_unless(GuiPrototypes::exists($key), 404);
        $component = GuiPrototypes::components()[$key];
        $record = $this->prototypes->get($key);

        return Inertia::render('gui-prototypes/show', [
            'component' => ['key' => $key] + $component,
            'record' => $record,
            'summary' => GuiPrototypes::summary($record),
            'usages' => GuiPrototypes::usages($key),
            'names' => $this->names(self::userIds($record)),
            // Vorschau der Beispiele hell/dunkel und mit einem Theme
            'themes' => app(Themes::class)->all(),
            'themeDefaults' => Themes::defaults(),
            'section' => in_array($request->query('reiter'), ['beispiele', 'versionen', 'ueberarbeitung', 'wuensche'], true) ? $request->query('reiter') : 'beispiele',
            'can' => [
                'develop' => Gate::allows('manage-users'),
                'review' => Gate::allows('review-gui'),
            ],
            // «Als GitHub-Issue vorbereiten» bei Wünschen (nur Administration)
            'github' => Gate::allows('manage-users') ? VersionController::githubRepo() : null,
        ]);
    }

    public function storeVersion(Request $request, string $key): RedirectResponse
    {
        abort_unless(GuiPrototypes::exists($key), 404);
        Gate::authorize('manage-users');
        $data = $request->validate([
            'bump' => ['required', Rule::in(['major', 'minor', 'patch'])],
            'changes' => ['required', 'string', 'max:2000'],
            'wishes' => ['array'],
            'wishes.*' => ['string'],
        ], ['changes.required' => 'Bitte beschreiben, was sich ändert.']);

        return $this->run($request, $key, fn (?string $user): array => $this->prototypes->createVersion(
            $key, $data['bump'], trim($data['changes']), array_values($data['wishes'] ?? []), $user,
        ), 'Neue Version angelegt (Entwicklung).', function (array $after, ?string $user) use ($key): void {
            $version = (string) end($after['versions'])['version'];
            $authors = array_column(array_filter((array) $after['wishes'], fn (array $w): bool => ($w['version'] ?? null) === $version), 'by');
            $this->notifications->toUsers($authors, 'gui.wish.planned', self::title($key)." v{$version}: dein Wunsch fliesst ein", 'Die neue Version ist in Entwicklung.', "/gui-prototypen/{$key}?reiter=wuensche", $user);
        });
    }

    public function transition(Request $request, string $key): RedirectResponse
    {
        abort_unless(GuiPrototypes::exists($key), 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['pruefen', 'zurueck', 'freigeben'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        Gate::authorize($data['action'] === 'pruefen' ? 'manage-users' : 'review-gui');

        $message = match ($data['action']) {
            'pruefen' => 'Zur Prüfung gegeben – die Prüfer:innen sind benachrichtigt.',
            'zurueck' => 'Zurück an die Entwicklung – die Entwicklung ist benachrichtigt.',
            default => 'Freigegeben – diese Version ist jetzt das verbindliche Template. Verwendende Seiten sind zur Überarbeitung vorgemerkt.',
        };

        return $this->run($request, $key, fn (?string $user): array => $this->prototypes->transition(
            $key, $data['action'], isset($data['comment']) ? trim($data['comment']) : null, $user,
        ), $message, function (array $after, ?string $user) use ($key, $data): void {
            $v = end($after['versions']);
            $title = self::title($key).' v'.$v['version'];
            $comment = isset($data['comment']) && trim((string) $data['comment']) !== '' ? 'Bemerkung: '.trim((string) $data['comment']) : '';
            $href = "/gui-prototypen/{$key}?reiter=versionen";
            match ($data['action']) {
                'pruefen' => $this->notifications->toAbility('review-gui', 'gui.review', "{$title} wartet auf Prüfung", $comment !== '' ? $comment : (string) $v['changes'], $href, $user),
                'zurueck' => $this->notifications->toUsers([$v['created_by'] ?? null, ...$this->developers()], 'gui.returned', "{$title} zurück an Entwicklung", $comment !== '' ? $comment : 'Ohne Bemerkung.', $href, $user),
                default => $this->notifications->toAbility('use-gui', 'gui.released', "{$title} freigegeben", count((array) $after['rework']).' Seiten sind zur Überarbeitung vorgemerkt.', "/gui-prototypen/{$key}?reiter=ueberarbeitung", $user),
            };
        });
    }

    public function rework(Request $request, string $key): RedirectResponse
    {
        abort_unless(GuiPrototypes::exists($key), 404);
        Gate::authorize('manage-users');
        $data = $request->validate(['file' => ['required', 'string', 'max:300'], 'done' => ['required', 'boolean']]);

        return $this->run($request, $key, fn (?string $user): array => $this->prototypes->markRework(
            $key, $data['file'], (bool) $data['done'], $user,
        ), $data['done'] ? 'Als überarbeitet markiert.' : 'Wieder offen.');
    }

    public function wish(Request $request, string $key): RedirectResponse
    {
        abort_unless(GuiPrototypes::exists($key), 404);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']], ['text.required' => 'Bitte den Wunsch beschreiben.']);

        return $this->run($request, $key, fn (?string $user): array => $this->prototypes->addWish($key, trim($data['text']), $user), 'Wunsch erfasst – die Entwicklung ist benachrichtigt.', function (array $after, ?string $user) use ($key, $data): void {
            $this->notifications->toUsers($this->developers(), 'gui.wish', 'Neuer Wunsch zu '.self::title($key), mb_substr(trim($data['text']), 0, 200), "/gui-prototypen/{$key}?reiter=wuensche", $user);
        });
    }

    /**
     * @param  callable(?string): array{before: array<string, mixed>|null, after: array<string, mixed>}  $change
     * @param  (callable(array<string, mixed>, ?string): void)|null  $then  z. B. Benachrichtigungen
     */
    private function run(Request $request, string $key, callable $change, string $message, ?callable $then = null): RedirectResponse
    {
        $user = $request->user()?->getAuthIdentifier() !== null ? (string) $request->user()->getAuthIdentifier() : null;
        try {
            $result = $change($user);
        } catch (InvalidArgumentException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }
        $this->audit->record(GuiPrototypes::COLLECTION, $result['before'], $result['after'], 'GUI-Prototypen', $request->user());
        if ($then !== null) {
            $then($result['after'], $user);
        }
        $this->toast($message);

        return back();
    }

    private static function title(string $key): string
    {
        return (string) (GuiPrototypes::components()[$key]['title'] ?? $key);
    }

    /**
     * Entwicklung der GUI-Prototypen (Recht manage-users).
     *
     * @return list<string>
     */
    private function developers(): array
    {
        return array_values(array_map(
            fn (array $u): string => (string) $u['id'],
            array_filter($this->users->active(), fn (array $u): bool => Roles::allows((string) ($u['role'] ?? ''), 'manage-users')),
        ));
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    private static function userIds(array $record): array
    {
        $ids = [];
        foreach ((array) $record['versions'] as $v) {
            foreach (['created_by', 'review_by', 'released_by'] as $field) {
                $ids[] = $v[$field] ?? null;
            }
            foreach ((array) ($v['log'] ?? []) as $entry) {
                $ids[] = $entry['by'] ?? null;
            }
        }
        foreach ((array) ($record['wishes'] ?? []) as $w) {
            $ids[] = $w['by'] ?? null;
        }
        foreach ((array) ($record['rework'] ?? []) as $r) {
            $ids[] = $r['done_by'] ?? null;
        }

        return array_values(array_unique(array_filter($ids, 'is_string')));
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    private function names(array $ids): array
    {
        $names = [];
        foreach (array_unique($ids) as $id) {
            $user = $this->users->find($id);
            $names[$id] = $user !== null ? (string) ($user['name'] ?? $id) : 'Unbekannt';
        }

        return $names;
    }
}
