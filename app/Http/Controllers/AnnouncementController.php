<?php

namespace App\Http\Controllers;

use App\Data\Contracts\AnnouncementRepository;
use App\Data\Contracts\UserRepository;
use App\Support\ReleaseAnnouncements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementRepository $announcements,
        private readonly UserRepository $users,
    ) {}

    public function index(Request $request): Response
    {
        // Redaktion sieht auch geplante (noch nicht veröffentlichte) Mitteilungen.
        $items = Gate::allows('manage-content')
            ? $this->sortedForEditors()
            : $this->announcements->published();

        return Inertia::render('announcements/index', [
            'announcements' => $this->withAuthors(ReleaseAnnouncements::filterForViewer($items)),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->string('category')->toString(),
            ],
        ]);
    }

    public function show(string $id): Response
    {
        $item = $this->announcements->findOrFail($id);

        abort_if(
            strtotime((string) ($item['published_at'] ?? '')) > time() && Gate::denies('manage-content'),
            404,
        );
        // Neuerungen einer Version: nur die Punkte, für die die Person das Recht hat
        $item = ReleaseAnnouncements::forViewer($item);
        abort_if($item === null, 404);

        return Inertia::render('announcements/show', [
            'announcement' => $this->withAuthors([$item])[0],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('announcements/form', ['announcement' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = (string) $request->user()?->getAuthIdentifier();

        $item = $this->announcements->create($data);
        $this->pluginEvent('announcement.created', $item);
        $this->toast('Mitteilung gespeichert.');

        return redirect()->route('announcements.show', $item['id']);
    }

    public function edit(string $id): Response
    {
        return Inertia::render('announcements/form', [
            'announcement' => $this->announcements->findOrFail($id),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        // Bearbeitet: der Text gilt für alle (keine Auswahl der Punkte nach Recht mehr)
        $this->announcements->update($id, $this->validated($request) + ['release_items' => null]);
        $this->toast('Mitteilung aktualisiert.');

        return redirect()->route('announcements.show', $id);
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->announcements->findOrFail($id);
        $this->announcements->delete($id);
        $this->toast('Mitteilung gelöscht.');

        return redirect()->route('announcements.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['required', Rule::in($this->keys('announcement_categories'))],
            'audience' => ['required', Rule::in($this->keys('audiences'))],
            'pinned' => ['boolean'],
            'published_at' => ['required', 'date'],
        ]);

        $data['pinned'] = $request->boolean('pinned');
        $data['published_at'] = $this->toAtom($data['published_at']);

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sortedForEditors(): array
    {
        $items = $this->announcements->all();

        usort($items, fn (array $a, array $b): int => [($b['pinned'] ?? false) === true, strtotime((string) ($b['published_at'] ?? ''))]
            <=> [($a['pinned'] ?? false) === true, strtotime((string) ($a['published_at'] ?? ''))]);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withAuthors(array $items): array
    {
        $names = $this->users->names();
        $now = time();

        return array_map(fn (array $item): array => $item + [
            'author_name' => $names[$item['author_id'] ?? ''] ?? (is_string($item['author_label'] ?? null) ? $item['author_label'] : null),
            'scheduled' => strtotime((string) ($item['published_at'] ?? '')) > $now,
        ], $items);
    }
}
