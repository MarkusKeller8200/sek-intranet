<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Fachstelle ICT → Geräteausgabe: Zugangsdaten-Generator (läuft nur im Browser, speichert nichts)
 * und die geplanten Funktionen der Jamf-Anbindung.
 */
class DeviceHandoutController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('ict/device-handout', [
            'planned' => [
                'features' => array_values(array_map('strval', (array) config('intranet.modules.ict.items.geraeteausgabe.features', []))),
            ],
        ]);
    }
}
