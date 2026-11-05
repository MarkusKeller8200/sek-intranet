<?php

namespace Modules\Zeiterfassung\Http;

use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use App\Support\Notifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Zeiterfassung\Buchungsarten;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Modules\Zeiterfassung\Kategorien;
use Modules\Zeiterfassung\Unterschriften;
use Modules\Zeiterfassung\Zeitkonto;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Eigene Zeiterfassung: Einträge eines Monats erfassen, löschen und den Monat zum Visum einreichen.
 */
class ZeiterfassungController extends Controller
{
    public function __construct(
        private readonly EintragRepository $eintraege,
        private readonly MonatRepository $monate,
        private readonly Notifications $notifications,
        private readonly UserRepository $users,
        private readonly Buchungsarten $buchungsarten,
    ) {}

    public function index(Request $request): Response
    {
        $person = self::person($request);
        $monat = self::monatAus($request->query('monat'));
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);

        return Inertia::render('Zeiterfassung::start', [
            'monat' => $monat,
            'eintraege' => self::mitBuchungsart($liste),
            'status' => $this->monate->status($person, $monat),
            'summen' => Zeitkonto::summen($liste),
            'kategorien' => Kategorien::all() + ['buchungsarten' => $this->buchungsarten->liste()],
            'person' => ['name' => $this->name($person)],
            'darfVisum' => Gate::allows('visum-zeiterfassung'),
            'unterschrift' => self::unterschrift($person, $monat, $this->monate->status($person, $monat)),
        ]);
    }

    /**
     * Unterschriebener Monatsrapport (#43): nur für die Person selbst oder mit Recht visum-zeiterfassung.
     */
    public function unterschriftPdf(Request $request, string $person, string $monat): StreamedResponse
    {
        abort_unless($person === self::person($request) || Gate::allows('visum-zeiterfassung'), 403);
        $monat = self::monatAus($monat);
        $pfad = $this->monate->status($person, $monat)['unterschrift_pdf'] ?? null;
        abort_unless(is_string($pfad) && $pfad === Unterschriften::datei($person, $monat) && Storage::disk('local')->exists($pfad), 404);

        return Storage::disk('local')->download($pfad, "Monatsrapport-{$monat}-unterschrieben.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{status: mixed, pdf: string|null}
     */
    private static function unterschrift(string $person, string $monat, array $status): array
    {
        return [
            'status' => $status['unterschrift_status'] ?? null,
            'pdf' => isset($status['unterschrift_pdf']) ? "/zeiterfassung/unterschrift/{$person}/{$monat}" : null,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $person = self::person($request);
        $data = $request->validate([
            'datum' => ['required', 'date_format:Y-m-d'],
            // Buchungsart (#99); ohne Angabe wie bisher aus Art und Kategorie
            'buchungsart' => ['nullable', 'string', Rule::in(array_keys($this->buchungsarten->liste(true)))],
            'art' => ['nullable', Rule::in(['arbeit', 'abwesenheit'])],
            'kategorie' => ['nullable', 'string'],
            'beginn' => ['nullable', 'date_format:H:i'],
            'ende' => ['nullable', 'date_format:H:i', 'required_with:beginn'],
            'pause_min' => ['nullable', 'integer', 'min:0', 'max:480'],
            'stunden' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'required_without:beginn'],
            'projekt' => ['nullable', 'string', 'max:120'],
            'notiz' => ['nullable', 'string', 'max:500'],
        ], [
            'datum.required' => 'Bitte ein Datum wählen.',
            'buchungsart.in' => 'Bitte eine Buchungsart aus der Liste wählen.',
            'stunden.required_without' => 'Beginn und Ende oder die Anzahl Stunden angeben.',
            'ende.required_with' => 'Bitte das Ende angeben.',
        ]);

        // Art aus der Buchungsart; Abwesenheiten ohne eigene Kategorie (für Summen und Export wie bisher)
        $buchungsart = $data['buchungsart'] ?? Buchungsarten::von(['art' => $data['art'] ?? 'arbeit', 'kategorie' => $data['kategorie'] ?? '']);
        $art = $this->buchungsarten->liste()[$buchungsart]['art'] ?? (($data['art'] ?? 'arbeit') === 'abwesenheit' ? 'abwesenheit' : 'arbeit');
        if (($data['buchungsart'] ?? null) === null && $art === 'abwesenheit' && ! array_key_exists((string) ($data['kategorie'] ?? ''), Kategorien::ABWESENHEIT)) {
            throw ValidationException::withMessages(['buchungsart' => 'Bitte eine Buchungsart wählen.']);
        }
        $kategorie = $art === 'abwesenheit' ? (($data['buchungsart'] ?? null) === null ? (string) $data['kategorie'] : Buchungsarten::kategorieFuer($buchungsart)) : (string) ($data['kategorie'] ?? '');
        if ($art === 'arbeit' && ! array_key_exists($kategorie, Kategorien::ARBEIT)) {
            throw ValidationException::withMessages(['kategorie' => 'Bitte eine Kategorie (Tätigkeit) wählen.']);
        }
        $monat = substr($data['datum'], 0, 7);
        $this->entsperrt($person, $monat);

        $stunden = (float) ($data['stunden'] ?? 0);
        if (! empty($data['beginn'])) {
            try {
                $stunden = Zeitkonto::stunden($data['beginn'], (string) $data['ende'], (int) ($data['pause_min'] ?? 0));
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['ende' => $e->getMessage()]);
            }
        }

        $this->eintraege->create([
            'person_id' => $person,
            'datum' => $data['datum'],
            'monat' => $monat,
            'art' => $art,
            'buchungsart' => $buchungsart,
            'kategorie' => $kategorie,
            'beginn' => $data['beginn'] ?? null,
            'ende' => $data['ende'] ?? null,
            'pause_min' => isset($data['pause_min']) ? (int) $data['pause_min'] : null,
            'stunden' => $stunden,
            'projekt' => isset($data['projekt']) ? trim($data['projekt']) : null,
            'notiz' => isset($data['notiz']) ? trim($data['notiz']) : null,
        ]);
        $this->toast('Eintrag gespeichert ('.number_format($stunden, 2, '.', "'").' h).');

        return redirect('/zeiterfassung?monat='.$monat);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $person = self::person($request);
        $eintrag = $this->eintraege->find($id);
        abort_if($eintrag === null || ($eintrag['person_id'] ?? null) !== $person, 404);
        $this->entsperrt($person, (string) $eintrag['monat']);
        $this->eintraege->delete($id);
        $this->toast('Eintrag gelöscht.');

        return back();
    }

    public function einreichen(Request $request): RedirectResponse
    {
        $person = self::person($request);
        $monat = self::monatAus((string) $request->input('monat'));
        $this->entsperrt($person, $monat);
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);
        if ($liste === []) {
            $this->toast('Für diesen Monat gibt es noch keine Einträge.', 'error');

            return back();
        }
        $summen = Zeitkonto::summen($liste);
        $this->monate->setzen($person, $monat, [
            'status' => 'eingereicht',
            'stunden' => $summen['total'],
            'eingereicht_at' => now()->toIso8601String(),
            'bemerkung' => null,
        ]);
        $name = $this->name($person);
        $this->notifications->toAbility('visum-zeiterfassung', 'zeit.monat_eingereicht', "Zeiterfassung {$monat}: {$name} hat eingereicht",
            number_format($summen['total'], 2, '.', "'").' h – bitte visieren.', "/zeiterfassung/visum/{$person}?monat={$monat}", $person);
        $this->toast('Monat eingereicht – die Schulleitung ist benachrichtigt.');

        return back();
    }

    /**
     * Einträge mit Buchungsart (ältere aus Art und Kategorie).
     *
     * @param  list<array<string, mixed>>  $liste
     * @return list<array<string, mixed>>
     */
    public static function mitBuchungsart(array $liste): array
    {
        return array_map(fn (array $e): array => ['buchungsart' => Buchungsarten::von($e)] + $e, $liste);
    }

    private function entsperrt(string $person, string $monat): void
    {
        $status = (string) ($this->monate->status($person, $monat)['status'] ?? 'offen');
        if (Zeitkonto::gesperrt($status)) {
            throw ValidationException::withMessages(['datum' => "Der Monat {$monat} ist ".($status === 'visiert' ? 'visiert' : 'eingereicht').' und kann nicht mehr geändert werden.']);
        }
    }

    private function name(string $id): string
    {
        return (string) ($this->users->find($id)['name'] ?? '');
    }

    private static function person(Request $request): string
    {
        $id = $request->user()?->getAuthIdentifier();
        abort_unless(is_string($id), 403);

        return $id;
    }

    private static function monatAus(mixed $monat): string
    {
        try {
            return Zeitkonto::monat(is_string($monat) ? $monat : null);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
