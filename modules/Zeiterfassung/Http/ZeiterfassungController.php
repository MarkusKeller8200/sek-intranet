<?php

namespace Modules\Zeiterfassung\Http;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\Support\Notifications;
use App\Support\PersonLinks;
use App\Support\Signatures;
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
use Modules\Zeiterfassung\Feiertage;
use Modules\Zeiterfassung\Kategorien;
use Modules\Zeiterfassung\Monatsabschluss;
use Modules\Zeiterfassung\MonatsblattPdf;
use Modules\Zeiterfassung\Pruefregeln;
use Modules\Zeiterfassung\Sollzeiten;
use Modules\Zeiterfassung\Unterschriften;
use Modules\Zeiterfassung\Vorgesetzte;
use Modules\Zeiterfassung\Zeitkonto;
use Plugins\Clicktime\Importer;
use Plugins\Clicktime\Plugin;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            'feiertage' => Feiertage::monat($monat),
            'soll' => app(Sollzeiten::class)->monat($person, $monat),
            'summen' => Zeitkonto::summen($liste),
            'kategorien' => Kategorien::all() + ['buchungsarten' => $this->buchungsarten->liste()],
            'person' => ['id' => $person, 'name' => $this->name($person)],
            'darfVisum' => Gate::allows('visum-zeiterfassung'),
            'unterschrift' => self::unterschrift($person, $monat, $this->monate->status($person, $monat)),
            'monatsblatt' => self::monatsblatt($person, $monat, $this->monate->status($person, $monat)),
            // Vollständigkeit (#33): Arbeitstage ohne Eintrag bis heute
            'fehlend' => Zeitkonto::fehlendeTage($monat, $liste, Feiertage::monat($monat), app(Sollzeiten::class)->monat($person, $monat)),
            // Prüfhinweise je Tag (#167)
            'pruefungen' => Zeitkonto::pruefungen($monat, $liste, app(Sollzeiten::class)->monat($person, $monat), null, app(Pruefregeln::class)->regeln()),
            'clicktime' => self::clicktime($request, $person),
            // ClickTime führt (#154): keine eigenen Einträge erfassen oder bearbeiten
            'clicktimeFuehrt' => self::clicktimeNr($person) !== null,
            // Monatsrapport an die vorgesetzte Person unterschreiben (#208)
            'vorgesetzte' => array_map(fn (string $id): array => ['id' => $id, 'name' => $this->name($id)], app(Vorgesetzte::class)->fuer($person)['vorgesetzte']),
            'signaturAktiv' => app(Signatures::class)->provider() !== null,
        ]);
    }

    /**
     * Monatsrapport unterschreiben (#208): die Person startet selbst, zuerst unterschreibt sie, danach die hinterlegte
     * vorgesetzte Person (aus ClickTime oder von Hand). Nur visierte oder in ClickTime abgeschlossene Monate.
     */
    public function rapportUnterschreiben(Request $request): RedirectResponse
    {
        $person = self::person($request);
        $data = $request->validate(['monat' => ['required', 'string'], 'vorgesetzte' => ['nullable', 'string', 'max:64']]);
        $monat = self::monatAus($data['monat']);
        $status = $this->monate->status($person, $monat);
        if (! in_array($status['status'] ?? null, ['visiert', 'abgeschlossen'], true)) {
            $this->toast('Unterschreiben ist erst möglich, wenn der Monat visiert bzw. in ClickTime abgeschlossen ist.', 'error');

            return back();
        }
        $signatures = app(Signatures::class);
        foreach ($signatures->forReference('zeiterfassung', Unterschriften::bezug($person, $monat)) as $auftrag) {
            if (in_array($auftrag['status'], ['entwurf', ...Signatures::OPEN, 'unterschrieben'], true)) {
                $this->toast('Für diesen Monat läuft bereits eine Unterschrift.', 'error');

                return back();
            }
        }
        $liste = app(Vorgesetzte::class)->fuer($person)['vorgesetzte'];
        $chefId = isset($data['vorgesetzte']) && in_array($data['vorgesetzte'], $liste, true) ? (string) $data['vorgesetzte'] : ($liste[0] ?? null);
        $chef = $chefId !== null ? $this->users->find($chefId) : null;
        $ich = $this->users->find($person);
        if ($chef === null || trim((string) ($chef['email'] ?? '')) === '' || $ich === null) {
            $this->toast('Keine vorgesetzte Person hinterlegt – bitte bei der Schulleitung melden.', 'error');

            return back();
        }
        $user = $request->user();
        $dokument = app(Monatsabschluss::class)->pdf($person, $monat, ['person' => (string) $ich['name'], 'schulleitung' => (string) $chef['name'], 'rolle' => 'Vorgesetzte:r']);
        try {
            $auftrag = $signatures->create([
                'module' => 'zeiterfassung',
                'reference' => Unterschriften::bezug($person, $monat),
                'title' => 'Monatsrapport '.MonatsblattPdf::monatName($monat).' – '.(string) $ich['name'],
                'file_name' => 'Monatsrapport-'.$monat.'-'.preg_replace('/[^A-Za-z0-9]+/', '-', (string) $ich['name']).'.pdf',
                'comment' => 'Bitte den Monatsrapport '.MonatsblattPdf::monatName($monat).' unterschreiben.',
                'signees' => [
                    ['person_id' => $person, 'name' => (string) $ich['name'], 'email' => (string) ($ich['email'] ?? ''), 'order' => 0, 'position' => $dokument['felder']['person']],
                    ['person_id' => (string) $chefId, 'name' => (string) $chef['name'], 'email' => (string) $chef['email'], 'order' => 1, 'position' => $dokument['felder']['schulleitung']],
                ],
                'observers' => [],
            ], $dokument['pdf'], $user instanceof IntranetUser ? $user : null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }
        $this->monate->setzen($person, $monat, ['unterschrift_status' => (string) $auftrag['status'], 'unterschrift_id' => (string) $auftrag['id'],
            'unterschrift_at' => null, 'unterschrift_pdf' => null, 'unterschrift_vorgesetzte' => $chefId, 'dossier_status' => null, 'dossier_at' => null]);
        $this->toast('Zur Unterschrift gesendet – du erhältst eine E-Mail von DeepSign, danach unterschreibt '.(string) $chef['name'].'.');

        return back();
    }

    /**
     * Knopf «Aus ClickTime übernehmen» (#101): nur mit eingeschaltetem Plugin, ClickTime-Nr. in der Datenherkunft und
     * Recht (Administration; Mitarbeitende, wenn im Plugin erlaubt).
     *
     * @return array{nr: string}|null
     */
    private static function clicktime(Request $request, string $person): ?array
    {
        $nr = self::clicktimeNr($person);
        if ($nr === null) {
            return null;
        }
        $user = $request->user();
        $allowed = ($user instanceof IntranetUser && $user->hasRole('admin'))
            || app(PluginManager::class)->context('clicktime', null)->setting('import_self', false) === true;

        return $allowed ? ['nr' => $nr] : null;
    }

    /**
     * ClickTime-Nr. der Person, wenn ClickTime für sie aktiv ist (Plugin eingeschaltet, Nr. in der Datenherkunft).
     * Dann führt ClickTime die Zeiten (#154).
     */
    public static function clicktimeNr(string $person): ?string
    {
        $plugins = app(PluginManager::class);
        if (! class_exists(Importer::class) || ! isset($plugins->all()['clicktime']) || ! $plugins->state('clicktime')['enabled']) {
            return null;
        }
        $nr = null;
        foreach (app(PersonLinks::class)->all() as $link) {
            if (($link['person_id'] ?? null) === $person && ($link['system'] ?? null) === 'clicktime' && ($link['direction'] ?? null) === 'source') {
                $nr = (string) ($link['external_id'] ?? '');
            }
        }

        return $nr !== null && $nr !== '' ? $nr : null;
    }

    /** Mit ClickTime keine eigenen Einträge (#154). */
    private static function ohneClicktime(string $person): void
    {
        if (self::clicktimeNr($person) !== null) {
            throw ValidationException::withMessages(['datum' => 'Ihre Zeiten werden in ClickTime erfasst – eigene Einträge sind hier nicht möglich.']);
        }
    }

    /**
     * Monat in ClickTime abgeschlossen (#164): letzter Abgleich, Monat sperren, Monatsblatt ablegen. Nur für Personen,
     * deren Zeiten ClickTime führt.
     */
    public function abschliessen(Request $request): RedirectResponse
    {
        $person = self::person($request);
        $monat = self::monatAus((string) $request->input('monat'));
        $nr = self::clicktimeNr($person);
        abort_if($nr === null, 403);
        $user = $request->user();
        try {
            $r = app(Monatsabschluss::class)->clicktime($person, $monat, $nr, $user instanceof IntranetUser ? $user : null);
        } catch (Throwable $e) {
            $this->toast('Abschluss nicht möglich: '.$e->getMessage(), 'error');

            return back();
        }
        $this->toast("Monat abgeschlossen: aus ClickTime übernommen ({$r['neu']} neu, {$r['geaendert']} geändert, {$r['geloescht']} gelöscht), Monatsblatt abgelegt.");

        return back();
    }

    /** Abgelegtes Monatsblatt (#34): nur für die Person selbst oder mit Recht visum-zeiterfassung. */
    public function monatsblattPdf(Request $request, string $person, string $monat): StreamedResponse
    {
        abort_unless($person === self::person($request) || Gate::allows('visum-zeiterfassung'), 403);
        $monat = self::monatAus($monat);
        $pfad = $this->monate->status($person, $monat)['monatsblatt_pdf'] ?? null;
        abort_unless(is_string($pfad) && $pfad === Monatsabschluss::datei($person, $monat) && Storage::disk('local')->exists($pfad), 404);

        return Storage::disk('local')->download($pfad, "Monatsblatt-{$monat}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Aktueller Monatsrapport als PDF (#168), im Aufbau von ClickTime: für die Person selbst oder mit Recht
     * visum-zeiterfassung. Wird nicht abgelegt.
     */
    public function rapportPdf(Request $request, string $person, string $monat): HttpResponse
    {
        abort_unless($person === self::person($request) || Gate::allows('visum-zeiterfassung'), 403);
        $monat = self::monatAus($monat);
        abort_if($this->users->find($person) === null, 404);
        $pdf = app(Monatsabschluss::class)->pdf($person, $monat)['pdf'];

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Monatsrapport-'.$monat.'.pdf"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    public static function monatsblatt(string $person, string $monat, array $status): ?string
    {
        return isset($status['monatsblatt_pdf']) ? "/zeiterfassung/monatsblatt/{$person}/{$monat}" : null;
    }

    /** Eigenen Monat aus ClickTime übernehmen (#101). */
    public function ausClicktime(Request $request): RedirectResponse
    {
        $person = self::person($request);
        $monat = self::monatAus((string) $request->input('monat'));
        $info = self::clicktime($request, $person);
        abort_if($info === null, 403);
        $user = $request->user();
        try {
            $text = app(PluginManager::class)->runWith('clicktime', $user instanceof IntranetUser ? $user : null, 'Zeiterfassung: aus ClickTime übernehmen',
                fn ($ctx): string => Plugin::importMessage($ctx, $this->name($person), $monat, (new Importer($ctx))->run($person, $info['nr'], $monat, true), true));
        } catch (Throwable $e) {
            $this->toast('Übernehmen nicht möglich: '.$e->getMessage(), 'error');

            return back();
        }
        $this->toast((string) $text);

        return back();
    }

    /**
     * Unterschriebener Monatsrapport (#43): nur für die Person selbst oder mit Recht visum-zeiterfassung.
     */
    public function unterschriftPdf(Request $request, string $person, string $monat): StreamedResponse
    {
        $monat = self::monatAus($monat);
        $status = $this->monate->status($person, $monat);
        // eigene Person, Visum oder die vorgesetzte Person, die mitunterschrieben hat (#208)
        abort_unless($person === self::person($request) || Gate::allows('visum-zeiterfassung')
            || (($status['unterschrift_vorgesetzte'] ?? null) !== null && $status['unterschrift_vorgesetzte'] === self::person($request)), 403);
        $pfad = $status['unterschrift_pdf'] ?? null;
        abort_unless(is_string($pfad) && $pfad === Unterschriften::datei($person, $monat) && Storage::disk('local')->exists($pfad), 404);

        return Storage::disk('local')->download($pfad, "Monatsrapport-{$monat}-unterschrieben.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{status: mixed, pdf: string|null, dossier: mixed}
     */
    private static function unterschrift(string $person, string $monat, array $status): array
    {
        return [
            'status' => $status['unterschrift_status'] ?? null,
            'pdf' => isset($status['unterschrift_pdf']) ? "/zeiterfassung/unterschrift/{$person}/{$monat}" : null,
            // Kopie ins Dossier (CMI, #208)
            'dossier' => $status['dossier_status'] ?? null,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $person = self::person($request);
        self::ohneClicktime($person);
        $eintrag = $this->daten($request, $person);
        $this->eintraege->create(['person_id' => $person] + $eintrag);
        $this->toast('Eintrag gespeichert ('.number_format((float) $eintrag['stunden'], 2, '.', "'").' h).');

        return redirect('/zeiterfassung?monat='.$eintrag['monat']);
    }

    /** Eintrag ändern (#102): nur von Hand im Intranet erfasste; aus Fremdsystemen (z. B. ClickTime) nur löschen. */
    public function update(Request $request, string $id): RedirectResponse
    {
        $person = self::person($request);
        self::ohneClicktime($person);
        $alt = $this->eintraege->find($id);
        abort_if($alt === null || ($alt['person_id'] ?? null) !== $person, 404);
        if ((string) ($alt['quelle'] ?? '') !== '') {
            throw ValidationException::withMessages(['datum' => 'Einträge aus einem Fremdsystem können nicht bearbeitet, nur gelöscht werden.']);
        }
        $this->entsperrt($person, (string) $alt['monat']);
        $eintrag = $this->daten($request, $person);
        $this->eintraege->update($id, $eintrag);
        $this->toast('Eintrag geändert ('.number_format((float) $eintrag['stunden'], 2, '.', "'").' h).');

        return redirect('/zeiterfassung?monat='.$eintrag['monat']);
    }

    /**
     * Geprüfte Felder eines Eintrags aus dem Formular (Erfassen und Ändern).
     *
     * @return array<string, mixed>
     */
    private function daten(Request $request, string $person): array
    {
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

        return [
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
        ];
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
        // Mit ClickTime: Abschluss und Genehmigung in ClickTime (#164)
        if (self::clicktimeNr($person) !== null) {
            $this->toast('Ihre Zeiten führt ClickTime: Monat dort abschliessen und hier «In ClickTime abgeschlossen» bestätigen.', 'error');

            return back();
        }
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
            throw ValidationException::withMessages(['datum' => "Der Monat {$monat} ist ".(['visiert' => 'visiert', 'abgeschlossen' => 'in ClickTime abgeschlossen'][$status] ?? 'eingereicht').' und kann nicht mehr geändert werden.']);
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
