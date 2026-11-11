<?php

namespace Modules\Zeiterfassung\Http;

use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use App\Support\PersonTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Zeiterfassung\Pruefregeln;
use Modules\Zeiterfassung\Vorgesetzte;

/**
 * Einstellungen der Zeiterfassung (Recht config-zeiterfassung): welche Prüfhinweise je Tag erscheinen (#204) und wer
 * den Monatsrapport als vorgesetzte Person mitunterschreibt (#208).
 */
class EinstellungenController extends Controller
{
    /** Personenarten mit Zeiterfassung (Personal). */
    private const PERSONAL = ['lehrperson', 'schulleitung', 'mitarbeitende'];

    public function __construct(
        private readonly Pruefregeln $pruefregeln,
        private readonly Vorgesetzte $vorgesetzte,
        private readonly UserRepository $users,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Zeiterfassung::einstellungen', [
            'regeln' => $this->pruefregeln->regeln(),
            'vorgabe' => Pruefregeln::VORGABE,
            'personen' => $this->personal(),
            'vorgesetzte' => $this->vorgesetzte->alle(),
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

    /** Vorgesetzte einer Person von Hand setzen (#208); leer = Zuordnung entfernen, danach gilt wieder ClickTime. */
    public function vorgesetzte(Request $request, string $person): RedirectResponse
    {
        $personal = array_column($this->personal(), 'name', 'id');
        abort_unless(isset($personal[$person]), 404);
        $data = $request->validate(['vorgesetzte' => ['nullable', 'string', 'max:64']]);
        $id = (string) ($data['vorgesetzte'] ?? '');
        if ($id !== '' && ! isset($personal[$id])) {
            throw ValidationException::withMessages(['vorgesetzte' => 'Unbekannte Person.']);
        }
        $this->vorgesetzte->vonHand($person, $id === '' ? [] : [$id]);
        $this->toast('Vorgesetzte:r gespeichert.');

        return back();
    }

    /**
     * Aktive Personen mit Zeiterfassung (Personal), nach Name.
     *
     * @return list<array{id: string, name: string}>
     */
    private function personal(): array
    {
        $liste = [];
        foreach ($this->users->active() as $u) {
            if (in_array(PersonTypes::of($u), self::PERSONAL, true)) {
                $liste[] = ['id' => (string) $u['id'], 'name' => (string) ($u['name'] ?? '')];
            }
        }
        usort($liste, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $liste;
    }
}
