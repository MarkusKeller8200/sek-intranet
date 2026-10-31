<?php

namespace App\Http\Controllers;

use App\Plugins\ManifestOpenApi;
use App\Plugins\PluginManager;
use App\Support\ApiDocs;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * API-Ansicht mit Scalar (Open Source): eigene REST-API, Endpunkte eines Plugins, aufgezeichnete APIs.
 */
class ApiViewController extends Controller
{
    public function own(): Response
    {
        return Inertia::render('api-view', [
            'title' => 'REST-API – Referenz',
            'description' => 'Die REST-API des Intranets (/api/v1) als interaktive Referenz. Ausprobieren mit einem API-Token.',
            'back' => ['href' => '/api-doku', 'label' => 'API'],
            'url' => '/api/openapi.json',
        ]);
    }

    public function plugin(string $id, PluginManager $plugins): Response
    {
        try {
            $manifest = $plugins->manifest($id);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return Inertia::render('api-view', [
            'title' => $manifest->name().' – Endpunkte',
            'description' => 'Endpunkte aus plugin.json mit Status (vollständig, teilweise geprüft, geplant). Ohne Zugangsdaten.',
            'back' => ['href' => "/plugins/{$id}", 'label' => $manifest->name()],
            'spec' => ManifestOpenApi::build($manifest),
        ]);
    }

    public function recording(string $id, ApiDocs $docs): Response
    {
        $doc = $docs->find($id);
        abort_if($doc === null, 404);
        $title = (string) ($doc['title'] ?? 'API');

        return Inertia::render('api-view', [
            'title' => $title.' – Ansicht',
            'description' => 'Aufgezeichnete API als OpenAPI-Referenz (Schemas aus Beispielen abgeleitet).',
            'back' => ['href' => "/api-recorder/{$id}", 'label' => $title],
            'doc' => $doc,
        ]);
    }
}
