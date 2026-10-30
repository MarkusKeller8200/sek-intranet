<?php

namespace App\Http\Controllers;

use App\Support\McpConcept;
use Inertia\Inertia;
use Inertia\Response;

/** Konzeptseite MCP-Schnittstelle (Prototyp → MCP-Schnittstelle). */
class McpConceptController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('mcp-concept', [
            'tools' => McpConcept::tools(),
            'endpoint' => rtrim((string) config('app.url'), '/').'/mcp',
        ]);
    }
}
