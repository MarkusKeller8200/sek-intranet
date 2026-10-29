<?php

namespace App\Http\Controllers;

use App\Data\Contracts\EventRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly EventRepository $events) {}

    public function index(Request $request): Response
    {
        $showPast = $request->boolean('vergangene');

        return Inertia::render('events/index', [
            'events' => $showPast ? $this->events->chronological() : $this->events->upcoming(),
            'showPast' => $showPast,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('events/form', ['event' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = (string) $request->user()?->getAuthIdentifier();

        $event = $this->events->create($data);
        $this->pluginEvent('event.created', $event);
        $this->toast('Termin gespeichert.');

        return redirect()->route('events.index');
    }

    public function edit(string $id): Response
    {
        return Inertia::render('events/form', ['event' => $this->events->findOrFail($id)]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->events->update($id, $this->validated($request));
        $this->toast('Termin aktualisiert.');

        return redirect()->route('events.index');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->events->findOrFail($id);
        $this->events->delete($id);
        $this->toast('Termin gelöscht.');

        return redirect()->route('events.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in($this->keys('event_categories'))],
            'school' => ['required', Rule::in($this->keys('schools'))],
            'location' => ['nullable', 'string', 'max:200'],
            'all_day' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $allDay = $request->boolean('all_day');
        $starts = (string) $data['starts_at'];
        $ends = isset($data['ends_at']) ? (string) $data['ends_at'] : null;

        // Ganztägige Termine: nur das Datum zählt.
        if ($allDay) {
            $starts = substr($starts, 0, 10);
            $ends = $ends !== null && $ends !== '' ? substr($ends, 0, 10) : null;
        }

        $data['all_day'] = $allDay;
        $data['starts_at'] = $this->toAtom($starts);
        $data['ends_at'] = $this->toAtom($ends);
        $data['description'] = $data['description'] ?? null;
        $data['location'] = $data['location'] ?? null;

        return $data;
    }
}
