<?php

namespace Modules\Zeiterfassung\Http;

use App\Auth\IntranetUser;
use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use App\Support\Notifications;
use App\Support\Signatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
use Modules\Zeiterfassung\Sollzeiten;
use Modules\Zeiterfassung\Unterschriften;
use Modules\Zeiterfassung\Zeitkonto;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Monatsvisum (Recht visum-zeiterfassung, Schulleitung): Übersicht je Monat, Monatsblatt je Person,
 * visieren bzw. zurückgeben, Export für die Lohnbuchhaltung.
 */
class VisumController extends Controller
{
    public function __construct(
        private readonly EintragRepository $eintraege,
        private readonly MonatRepository $monate,
        private readonly UserRepository $users,
        private readonly Notifications $notifications,
        private readonly Signatures $signatures,
        private readonly Buchungsarten $buchungsarten,
    ) {}

    public function index(Request $request): Response
    {
        $monat = self::monatAus($request->query('monat'));

        return Inertia::render('Zeiterfassung::visum', [
            'monat' => $monat,
            'personen' => $this->uebersicht($monat),
            'status' => Kategorien::STATUS,
        ]);
    }

    public function show(Request $request, string $person): Response
    {
        $monat = self::monatAus($request->query('monat'));
        $user = $this->users->find($person);
        abort_if($user === null, 404);
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);
        $status = $this->monate->status($person, $monat);

        return Inertia::render('Zeiterfassung::person', [
            'monat' => $monat,
            'person' => ['id' => $person, 'name' => (string) ($user['name'] ?? ''), 'email' => (string) ($user['email'] ?? '')],
            'eintraege' => ZeiterfassungController::mitBuchungsart($liste),
            'status' => $status,
            'feiertage' => Feiertage::monat($monat),
            'soll' => app(Sollzeiten::class)->monat($person, $monat),
            'summen' => Zeitkonto::summen($liste),
            'kategorien' => Kategorien::all() + ['buchungsarten' => $this->buchungsarten->liste()],
            'visiertVon' => isset($status['visiert_by']) ? (string) ($this->users->find((string) $status['visiert_by'])['name'] ?? '') : null,
            'unterschrift' => $this->unterschriftInfo($person, $monat, $status),
            // Monatsblatt (#34), ClickTime-Abschluss (#164)
            'monatsblatt' => ZeiterfassungController::monatsblatt($person, $monat, $status),
            'clicktime' => ZeiterfassungController::clicktimeNr($person) !== null,
            'abgeschlossenVon' => isset($status['abgeschlossen_by']) ? (string) ($this->users->find((string) $status['abgeschlossen_by'])['name'] ?? '') : null,
            'fehlend' => Zeitkonto::fehlendeTage($monat, $liste, Feiertage::monat($monat), app(Sollzeiten::class)->monat($person, $monat)),
            'pruefungen' => Zeitkonto::pruefungen($monat, $liste, app(Sollzeiten::class)->monat($person, $monat)),
        ]);
    }

    /**
     * Visierten Monatsrapport zur elektronischen Unterschrift senden (#43): PDF auf dem Server, Mitarbeiter:in und
     * Schulleitung (die angemeldete Person) in der gewählten Reihenfolge, optional Beobachtende (Personalabteilung).
     */
    public function unterschrift(Request $request, string $person): RedirectResponse
    {
        $data = $request->validate([
            'monat' => ['required', 'string'],
            'reihenfolge' => ['required', Rule::in(['person_zuerst', 'schulleitung_zuerst', 'gleichzeitig'])],
            'beobachtende' => ['nullable', 'email', 'max:200'],
        ]);
        $monat = self::monatAus($data['monat']);
        $status = $this->monate->status($person, $monat);
        $user = $this->users->find($person);
        $sl = $request->user();
        abort_if($user === null || ! $sl instanceof IntranetUser, 404);
        if (! in_array($status['status'] ?? null, ['visiert', 'abgeschlossen'], true)) {
            $this->toast('Nur visierte oder in ClickTime abgeschlossene Monate können zur Unterschrift gesendet werden.', 'error');

            return back();
        }
        foreach ($this->signatures->forReference('zeiterfassung', Unterschriften::bezug($person, $monat)) as $auftrag) {
            if (in_array($auftrag['status'], ['entwurf', ...Signatures::OPEN, 'unterschrieben'], true)) {
                $this->toast('Für diesen Monat läuft bereits eine Unterschrift.', 'error');

                return back();
            }
        }

        $slName = (string) ($this->users->find((string) $sl->getAuthIdentifier())['name'] ?? $sl->name());
        $slEmail = (string) ($this->users->find((string) $sl->getAuthIdentifier())['email'] ?? '');
        $liste = $this->eintraege->fuerPersonMonat($person, $monat);
        $dokument = MonatsblattPdf::erstellen((string) $user['name'], $monat, $liste, Zeitkonto::summen($liste), $slName,
            is_string($status['visiert_at'] ?? null) ? $status['visiert_at'] : null, ['person' => (string) $user['name'], 'schulleitung' => $slName],
            array_map(fn (array $b): string => $b['name'], $this->buchungsarten->liste()));
        [$ordnungPerson, $ordnungSl] = match ($data['reihenfolge']) {
            'schulleitung_zuerst' => [1, 0],
            'gleichzeitig' => [0, 0],
            default => [0, 1],
        };
        $titel = 'Monatsrapport '.MonatsblattPdf::monatName($monat).' – '.(string) $user['name'];

        try {
            $auftrag = $this->signatures->create([
                'module' => 'zeiterfassung',
                'reference' => Unterschriften::bezug($person, $monat),
                'title' => $titel,
                'file_name' => 'Monatsrapport-'.$monat.'-'.preg_replace('/[^A-Za-z0-9]+/', '-', (string) $user['name']).'.pdf',
                'comment' => 'Bitte den Monatsrapport '.MonatsblattPdf::monatName($monat).' unterschreiben.',
                'signees' => [
                    ['person_id' => $person, 'name' => (string) $user['name'], 'email' => (string) ($user['email'] ?? ''), 'order' => $ordnungPerson, 'position' => $dokument['felder']['person']],
                    ['person_id' => (string) $sl->getAuthIdentifier(), 'name' => $slName, 'email' => $slEmail, 'order' => $ordnungSl, 'position' => $dokument['felder']['schulleitung']],
                ],
                'observers' => isset($data['beobachtende']) && $data['beobachtende'] !== '' ? [(string) $data['beobachtende']] : [],
            ], $dokument['pdf'], $sl);
        } catch (InvalidArgumentException|RuntimeException $e) {
            $this->toast($e->getMessage(), 'error');

            return back();
        }

        $this->monate->setzen($person, $monat, ['unterschrift_status' => (string) $auftrag['status'], 'unterschrift_id' => (string) $auftrag['id'], 'unterschrift_at' => null, 'unterschrift_pdf' => null]);
        $this->notifications->toUsers([$person], 'zeit.unterschrift', "Monatsrapport {$monat} zur Unterschrift", 'Du erhältst eine E-Mail von DeepSign mit dem Link zum Unterschreiben.', "/zeiterfassung?monat={$monat}", (string) $sl->getAuthIdentifier());
        $this->toast('Zur Unterschrift gesendet.');

        return back();
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array<string, mixed>
     */
    private function unterschriftInfo(string $person, string $monat, array $status): array
    {
        $auftraege = $this->signatures->forReference('zeiterfassung', Unterschriften::bezug($person, $monat));

        return [
            'status' => $status['unterschrift_status'] ?? null,
            'pdf' => isset($status['unterschrift_pdf']) ? "/zeiterfassung/unterschrift/{$person}/{$monat}" : null,
            'anbieter' => $this->signatures->provider(),
            'auftrag' => $auftraege[0] ?? null,
        ];
    }

    public function visieren(Request $request, string $person): RedirectResponse
    {
        $monat = self::monatAus((string) $request->input('monat'));
        $status = (string) ($this->monate->status($person, $monat)['status'] ?? 'offen');
        if ($status !== 'eingereicht') {
            $this->toast('Nur eingereichte Monate können visiert werden.', 'error');

            return back();
        }
        $by = (string) $request->user()?->getAuthIdentifier();
        $this->monate->setzen($person, $monat, ['status' => 'visiert', 'visiert_by' => $by, 'visiert_at' => now()->toIso8601String(), 'bemerkung' => null]);
        // Monatsblatt ablegen (#34); ein Fehler dabei hebt das Visum nicht auf
        try {
            app(Monatsabschluss::class)->ablegen($person, $monat);
        } catch (RuntimeException $e) {
            $this->toast('Monat visiert, Monatsblatt nicht abgelegt: '.$e->getMessage(), 'error');

            return back();
        }
        $this->notifications->toUsers([$person], 'zeit.monat_visiert', "Zeiterfassung {$monat} visiert", 'Die Schulleitung hat deinen Monat visiert. Das Monatsblatt liegt in deiner Zeiterfassung.', "/zeiterfassung?monat={$monat}", $by);
        $this->toast('Monat visiert, Monatsblatt abgelegt.');

        return back();
    }

    public function zurueck(Request $request, string $person): RedirectResponse
    {
        $data = $request->validate(['monat' => ['required', 'string'], 'bemerkung' => ['required', 'string', 'max:1000']], ['bemerkung.required' => 'Bitte begründen, was korrigiert werden soll.']);
        $monat = self::monatAus($data['monat']);
        $status = (string) ($this->monate->status($person, $monat)['status'] ?? 'offen');
        if (! in_array($status, ['eingereicht', 'visiert', 'abgeschlossen'], true)) {
            $this->toast('Der Monat ist nicht eingereicht.', 'error');

            return back();
        }
        $by = (string) $request->user()?->getAuthIdentifier();
        $this->monate->setzen($person, $monat, ['status' => 'zurueck', 'bemerkung' => trim($data['bemerkung']), 'visiert_by' => null, 'visiert_at' => null,
            'abgeschlossen_at' => null, 'abgeschlossen_by' => null, 'abgeschlossen_stellvertretend' => null, 'abweichung' => null]);
        $this->notifications->toUsers([$person], 'zeit.monat_zurueck', "Zeiterfassung {$monat}: bitte korrigieren", mb_substr(trim($data['bemerkung']), 0, 200), "/zeiterfassung?monat={$monat}", $by);
        $this->toast('Zurück an die Person – sie ist benachrichtigt.');

        return back();
    }

    /** Im Notfall stellvertretend «in ClickTime abgeschlossen» bestätigen (#164), vermerkt und der Person gemeldet. */
    public function abschliessen(Request $request, string $person): RedirectResponse
    {
        $monat = self::monatAus((string) $request->input('monat'));
        abort_if($this->users->find($person) === null, 404);
        $nr = ZeiterfassungController::clicktimeNr($person);
        if ($nr === null) {
            $this->toast('Für diese Person führt ClickTime keine Zeiten.', 'error');

            return back();
        }
        $user = $request->user();
        try {
            $r = app(Monatsabschluss::class)->clicktime($person, $monat, $nr, $user instanceof IntranetUser ? $user : null);
        } catch (Throwable $e) {
            $this->toast('Abschluss nicht möglich: '.$e->getMessage(), 'error');

            return back();
        }
        $this->toast("Stellvertretend abgeschlossen ({$r['neu']} neu, {$r['geaendert']} geändert, {$r['geloescht']} gelöscht), Monatsblatt abgelegt.");

        return back();
    }

    /** Abgeschlossenen Monat mit ClickTime vergleichen (Nachbuchungen, #164). */
    public function vergleichen(Request $request, string $person): RedirectResponse
    {
        $monat = self::monatAus((string) $request->input('monat'));
        $nr = ZeiterfassungController::clicktimeNr($person);
        if ($nr === null || ($this->monate->status($person, $monat)['status'] ?? '') !== 'abgeschlossen') {
            $this->toast('Nur in ClickTime abgeschlossene Monate können verglichen werden.', 'error');

            return back();
        }
        $user = $request->user();
        try {
            $r = app(Monatsabschluss::class)->vergleichen($person, $monat, $nr, $user instanceof IntranetUser ? $user : null);
        } catch (Throwable $e) {
            $this->toast('Vergleich nicht möglich: '.$e->getMessage(), 'error');

            return back();
        }
        $anzahl = $r['neu'] + $r['geaendert'] + $r['geloescht'];
        $this->toast($anzahl === 0 ? 'Keine Nachbuchungen seit dem Abschluss.' : "Nachbuchungen in ClickTime: {$r['neu']} neu, {$r['geaendert']} geändert, {$r['geloescht']} gelöscht. Zum Übernehmen den Monat zurückgeben.", $anzahl === 0 ? 'success' : 'error');

        return back();
    }

    /** Erinnerung an Personen, die den Monat noch nicht eingereicht bzw. abgeschlossen haben (#33). */
    public function erinnerung(Request $request): RedirectResponse
    {
        $data = $request->validate(['monat' => ['required', 'string'], 'personen' => ['nullable', 'array'], 'personen.*' => ['string']]);
        $monat = self::monatAus($data['monat']);
        $auswahl = isset($data['personen']) ? array_map('strval', (array) $data['personen']) : null;
        $ids = [];
        foreach ($this->uebersicht($monat) as $p) {
            if (in_array($p['status'], ['offen', 'zurueck'], true) && ($auswahl === null || in_array($p['id'], $auswahl, true))) {
                $ids[] = $p['id'];
            }
        }
        $by = (string) $request->user()?->getAuthIdentifier();
        $name = MonatsblattPdf::monatName($monat);
        $anzahl = $this->notifications->toUsers($ids, 'zeit.erinnerung', "Zeiterfassung {$name}: bitte abschliessen",
            'Bitte prüfen, ob alles eingetragen ist, und den Monat einreichen bzw. in ClickTime abschliessen.', "/zeiterfassung?monat={$monat}", $by);
        foreach ($ids as $id) {
            $this->monate->setzen($id, $monat, ['erinnert_at' => now()->toIso8601String()]);
        }
        $this->toast("{$anzahl} Personen erinnert.");

        return back();
    }

    /** Buchungsarten pflegen (#99). */
    public function buchungsarten(): Response
    {
        return Inertia::render('Zeiterfassung::buchungsarten', [
            'buchungsarten' => array_map(fn (string $key, array $b): array => ['key' => $key] + $b, array_keys($this->buchungsarten->liste()), $this->buchungsarten->liste()),
            'arten' => Buchungsarten::ARTEN,
        ]);
    }

    public function buchungsartenSpeichern(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zeilen' => ['required', 'array', 'max:60'],
            'zeilen.*.key' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_]*$/'],
            'zeilen.*.name' => ['nullable', 'string', 'max:60'],
            'zeilen.*.art' => ['required', Rule::in(array_keys(Buchungsarten::ARTEN))],
            'zeilen.*.aktiv' => ['boolean'],
        ]);
        try {
            $this->buchungsarten->speichern(array_map(fn (array $z): array => [
                'key' => $z['key'] ?? null, 'name' => (string) ($z['name'] ?? ''), 'art' => (string) $z['art'], 'aktiv' => (bool) ($z['aktiv'] ?? true),
            ], array_values((array) $data['zeilen'])));
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['zeilen' => $e->getMessage()]);
        }
        $this->toast('Buchungsarten gespeichert.');

        return back();
    }

    /**
     * CSV für die Lohnbuchhaltung (Excel Schweiz: Semikolon, UTF-8 mit BOM).
     * art=summen (Standard): eine Zeile je Person mit Stunden je Kategorie; art=details: alle Einträge.
     */
    public function export(Request $request): StreamedResponse
    {
        $monat = self::monatAus($request->query('monat'));
        $details = $request->query('art') === 'details';
        $nurVisiert = $request->query('nur_visiert', '1') === '1';
        $personen = array_filter($this->uebersicht($monat), fn (array $p): bool => ! $nurVisiert || in_array($p['status'], ['visiert', 'abgeschlossen'], true));
        $kategorien = Kategorien::ARBEIT + Kategorien::ABWESENHEIT;
        $buchungsarten = array_map(fn (array $b): string => $b['name'], $this->buchungsarten->liste());

        $rows = [];
        if ($details) {
            $rows[] = ['Person', 'E-Mail', 'Monat', 'Status', 'Datum', 'Art', 'Buchungsart', 'Kategorie', 'Beginn', 'Ende', 'Pause (min)', 'Stunden', 'Projekt'];
            foreach ($personen as $p) {
                foreach ($this->eintraege->fuerPersonMonat($p['id'], $monat) as $e) {
                    $rows[] = [$p['name'], $p['email'], $monat, Kategorien::STATUS[$p['status']] ?? $p['status'], (string) $e['datum'], $e['art'] === 'abwesenheit' ? 'Abwesenheit' : 'Arbeit',
                        $buchungsarten[Buchungsarten::von($e)] ?? Buchungsarten::von($e), Kategorien::label((string) $e['kategorie']), (string) ($e['beginn'] ?? ''), (string) ($e['ende'] ?? ''), (string) ($e['pause_min'] ?? ''), self::zahl((float) $e['stunden']), (string) ($e['projekt'] ?? '')];
                }
            }
        } else {
            $rows[] = ['Person', 'E-Mail', 'Monat', 'Status', 'Arbeit (h)', 'Abwesenheit (h)', 'Total (h)', ...array_values($kategorien),
                ...array_map(fn (string $n): string => "Buchungsart: {$n}", array_values($buchungsarten))];
            foreach ($personen as $p) {
                $s = $p['summen'];
                $rows[] = [$p['name'], $p['email'], $monat, Kategorien::STATUS[$p['status']] ?? $p['status'], self::zahl($s['arbeit']), self::zahl($s['abwesenheit']), self::zahl($s['total']),
                    ...array_map(fn (string $k): string => self::zahl($s['kategorien'][$k] ?? 0), array_keys($kategorien)),
                    ...array_map(fn (string $k): string => self::zahl($s['buchungsarten'][$k] ?? 0), array_keys($buchungsarten))];
            }
        }

        $name = "zeiterfassung-{$monat}-".($details ? 'details' : 'summen').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            echo "\xEF\xBB\xBF";
            foreach ($rows as $row) {
                echo implode(';', array_map(fn ($v): string => '"'.str_replace('"', '""', (string) $v).'"', $row))."\r\n";
            }
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Personen mit Einträgen oder Status im Monat.
     *
     * @return list<array{id: string, name: string, email: string, status: string, eintraege: int, summen: array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>, buchungsarten: array<string, float>}, fehlend: int, pruefen: array{fehler: int, hinweise: int}, erinnert_at: string|null}>
     */
    private function uebersicht(string $monat): array
    {
        $proPerson = [];
        foreach ($this->eintraege->fuerMonat($monat) as $e) {
            $proPerson[(string) $e['person_id']][] = $e;
        }
        $status = [];
        $erinnert = [];
        foreach ($this->monate->fuerMonat($monat) as $m) {
            $status[(string) $m['person_id']] = (string) ($m['status'] ?? 'offen');
            $erinnert[(string) $m['person_id']] = is_string($m['erinnert_at'] ?? null) ? $m['erinnert_at'] : null;
            $proPerson[(string) $m['person_id']] ??= [];
        }
        $feiertage = Feiertage::monat($monat);
        $soll = app(Sollzeiten::class);
        $result = [];
        foreach ($proPerson as $id => $liste) {
            $user = $this->users->find((string) $id);
            $result[] = [
                'id' => (string) $id,
                'name' => (string) ($user['name'] ?? 'Unbekannt'),
                'email' => (string) ($user['email'] ?? ''),
                'status' => $status[$id] ?? 'offen',
                'eintraege' => count($liste),
                'summen' => Zeitkonto::summen($liste),
                // Vollständigkeit (#33): Arbeitstage ohne Eintrag bis heute
                'fehlend' => count(Zeitkonto::fehlendeTage($monat, $liste, $feiertage, $soll->monat((string) $id, $monat))),
                // Tage mit Fehlern bzw. Hinweisen (#167)
                'pruefen' => self::pruefenZaehlen(Zeitkonto::pruefungen($monat, $liste, $soll->monat((string) $id, $monat))),
                'erinnert_at' => $erinnert[$id] ?? null,
            ];
        }
        usort($result, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * @param  array<string, list<array{art: string, text: string, eintraege: list<string>}>>  $pruefungen
     * @return array{fehler: int, hinweise: int} Tage mit Fehlern bzw. nur Hinweisen/Warnungen
     */
    private static function pruefenZaehlen(array $pruefungen): array
    {
        $fehler = count(array_filter($pruefungen, fn (array $h): bool => in_array('fehler', array_column($h, 'art'), true)));

        return ['fehler' => $fehler, 'hinweise' => count($pruefungen) - $fehler];
    }

    private static function zahl(float $h): string
    {
        return number_format($h, 2, '.', '');
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
