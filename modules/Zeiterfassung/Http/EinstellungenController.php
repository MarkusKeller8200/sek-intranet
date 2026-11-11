<?php

namespace Modules\Zeiterfassung\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Zeiterfassung\Pruefregeln;

/**
 * Einstellungen der Zeiterfassung (#204, Recht config-zeiterfassung): welche Prüfhinweise je Tag erscheinen.
 */
class EinstellungenController extends Controller
{
    public function __construct(private readonly Pruefregeln $pruefregeln) {}

    public function index(): Response
    {
        return Inertia::render('Zeiterfassung::einstellungen', [
            'regeln' => $this->pruefregeln->regeln(),
            'vorgabe' => Pruefregeln::VORGABE,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kommen_fehlt' => ['required', 'boolean'],
            'gehen_fehlt' => ['required', 'boolean'],
            'keine_buchung' => ['required', 'boolean'],
            'tagesist' => ['required', 'boolean'],
            'tagesist_max' => ['required', 'string', 'max:5'],
            'abweichung' => ['required', 'boolean'],
            'abweichung_prozent' => ['required', 'integer', 'min:1', 'max:100'],
            'mittag' => ['required', 'boolean'],
            'mittag_von' => ['required', 'string', 'max:5'],
            'mittag_bis' => ['required', 'string', 'max:5'],
            'mittag_min' => ['required', 'integer', 'min:5', 'max:180'],
        ]);
        try {
            $this->pruefregeln->speichern($data);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['regeln' => $e->getMessage()]);
        }
        $this->toast('Einstellungen gespeichert.');

        return back();
    }
}
