<?php

namespace App\Http\Controllers;

use App\Data\CodeCatalogs;
use App\Data\SchemaInspector;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Übersicht über Sammlungen, Felder und tatsächliche Daten.
 * Hilft zu entscheiden, wann die Strukturen reif für PostgreSQL sind.
 */
class DataStructureController extends Controller
{
    public function __invoke(SchemaInspector $inspector, CodeCatalogs $catalogs): Response
    {
        return Inertia::render('data-structures', [
            'store' => config('intranet.store'),
            'collections' => $inspector->inspect(),
            'unknownCollections' => $inspector->unknownCollections(),
            // Tabellen im Code statt in der Datenablage (Sprachkonzept, #79)
            'catalogs' => $catalogs->i18n(),
        ]);
    }
}
