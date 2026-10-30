<?php

namespace App\Http\Controllers;

use App\Data\Store\JsonStore;
use App\Support\GridViews;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Tabellenansichten speichern und löschen (JSON). Geteilte Ansichten nur durch die Administration.
 */
class GridViewController extends Controller
{
    private const GRIDS = ['people'];

    private const MAX_STATE_BYTES = 20000;

    public function __construct(private readonly JsonStore $store) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grid' => ['required', 'string', 'in:'.implode(',', self::GRIDS)],
            'name' => ['required', 'string', 'max:60'],
            'shared' => ['boolean'],
            'state' => ['required', 'array'],
            'id' => ['nullable', 'string'],
        ]);
        abort_if(strlen((string) json_encode($data['state'])) > self::MAX_STATE_BYTES, 422, 'Ansicht ist zu gross.');

        $shared = ($data['shared'] ?? false) === true;
        abort_if($shared && ! Gate::allows('manage-users'), 403, 'Ansichten für alle speichert die Administration.');

        $own = (string) $request->user()?->getAuthIdentifier();
        $attributes = ['grid' => $data['grid'], 'name' => trim($data['name']), 'shared' => $shared, 'state' => $data['state']];

        if (isset($data['id'])) {
            $existing = $this->store->find(GridViews::COLLECTION, $data['id']);
            abort_if($existing === null, 404);
            $this->authorizeChange($existing, $own);
            $view = $this->store->update(GridViews::COLLECTION, $data['id'], $attributes);
        } else {
            $view = $this->store->insert(GridViews::COLLECTION, $attributes + ['owner_id' => $own]);
        }

        return response()->json(['view' => ($view ?? []) + ['mine' => true]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $existing = $this->store->find(GridViews::COLLECTION, $id);
        abort_if($existing === null, 404);
        $this->authorizeChange($existing, (string) $request->user()?->getAuthIdentifier());
        $this->store->delete(GridViews::COLLECTION, $id);

        return response()->json(['ok' => true]);
    }

    /**
     * @param  array<string, mixed>  $view
     */
    private function authorizeChange(array $view, string $own): void
    {
        $allowed = ($view['owner_id'] ?? null) === $own || (($view['shared'] ?? false) === true && Gate::allows('manage-users'));
        abort_unless($allowed, 403, 'Diese Ansicht gehört jemand anderem.');
    }
}
