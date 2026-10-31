<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use App\Support\AuditLog;
use App\Support\PersonLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Datenherkunft einer Person pflegen (nur Administration): Quelle/Ziel, System, ID/PK, letzter Abgleich.
 */
class PersonLinkController extends Controller
{
    public function __construct(
        private readonly PersonLinks $links,
        private readonly UserRepository $users,
        private readonly AuditLog $audit,
    ) {}

    public function store(Request $request, string $id): RedirectResponse
    {
        $this->users->findOrFail($id);
        $result = $this->links->save(['person_id' => $id] + $this->input($request));
        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }
        $this->audit->record(PersonLinks::COLLECTION, null, $result['link'], 'Formular', $request->user());
        $this->toast('Datenherkunft gespeichert.');

        return back();
    }

    public function update(Request $request, string $id, string $link): RedirectResponse
    {
        $before = $this->ownLink($id, $link);
        $result = $this->links->save(['person_id' => $id] + $this->input($request), $link);
        if (isset($result['errors'])) {
            return back()->withErrors($result['errors']);
        }
        $this->audit->record(PersonLinks::COLLECTION, $before, $result['link'], 'Formular', $request->user());
        $this->toast('Datenherkunft gespeichert.');

        return back();
    }

    /** Ziel als abgeglichen markieren (z. B. nach einem manuellen Export). */
    public function synced(Request $request, string $id, string $link): RedirectResponse
    {
        $before = $this->ownLink($id, $link);
        $this->links->recordSync($id, (string) $before['system'], (string) $before['direction'], null);
        $this->audit->record(PersonLinks::COLLECTION, $before, $this->links->find($link), 'Abgleich', $request->user());
        $this->toast('Als abgeglichen markiert.');

        return back();
    }

    public function destroy(Request $request, string $id, string $link): RedirectResponse
    {
        $before = $this->ownLink($id, $link);
        $this->links->delete($link);
        $this->audit->record(PersonLinks::COLLECTION, $before, null, 'Formular', $request->user());
        $this->toast('Eintrag gelöscht.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function ownLink(string $personId, string $linkId): array
    {
        $link = $this->links->find($linkId);
        abort_if($link === null || ($link['person_id'] ?? null) !== $personId, 404);

        return $link;
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return $request->only(['direction', 'system', 'external_id', 'id_label', 'last_sync_at', 'note']);
    }
}
