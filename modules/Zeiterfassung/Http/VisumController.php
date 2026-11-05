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
use Modules\Zeiterfassung\MonatsblattPdf;
use Modules\Zeiterfassung\Sollzeiten;
use Modules\Zeiterfassung\Unterschriften;
use Modules\Zeiterfassung\Zeitkonto;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        if (($status['status'] ?? null) !== 'visiert') {
            $this->toast('Nur visierte Monate können zur Unterschrift gesendet werden.', 'error');

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
        $this->notifications->toUsers([$person], 'zeit.monat_visiert', "Zeiterfassung {$monat} visiert", 'Die Schulleitung hat deinen Monat visiert.', "/zeiterfassung?monat={$monat}", $by);
        $this->toast('Monat visiert.');

        return back();
    }

    public function zurueck(Request $request, string $person): RedirectResponse
    {
        $data = $request->validate(['monat' => ['required', 'string'], 'bemerkung' => ['required', 'string', 'max:1000']], ['bemerkung.required' => 'Bitte begründen, was korrigiert werden soll.']);
        $monat = self::monatAus($data['monat']);
        $status = (string) ($this->monate->status($person, $monat)['status'] ?? 'offen');
        if (! in_array($status, ['eingereicht', 'visiert'], true)) {
            $this->toast('Der Monat ist nicht eingereicht.', 'error');

            return back();
        }
        $by = (string) $request->user()?->getAuthIdentifier();
        $this->monate->setzen($person, $monat, ['status' => 'zurueck', 'bemerkung' => trim($data['bemerkung']), 'visiert_by' => null, 'visiert_at' => null]);
        $this->notifications->toUsers([$person], 'zeit.monat_zurueck', "Zeiterfassung {$monat}: bitte korrigieren", mb_substr(trim($data['bemerkung']), 0, 200), "/zeiterfassung?monat={$monat}", $by);
        $this->toast('Zurück an die Person – sie ist benachrichtigt.');

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
        $personen = array_filter($this->uebersicht($monat), fn (array $p): bool => ! $nurVisiert || $p['status'] === 'visiert');
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
     * @return list<array{id: string, name: string, email: string, status: string, eintraege: int, summen: array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>, buchungsarten: array<string, float>}}>
     */
    private function uebersicht(string $monat): array
    {
        $proPerson = [];
        foreach ($this->eintraege->fuerMonat($monat) as $e) {
            $proPerson[(string) $e['person_id']][] = $e;
        }
        $status = [];
        foreach ($this->monate->fuerMonat($monat) as $m) {
            $status[(string) $m['person_id']] = (string) $m['status'];
            $proPerson[(string) $m['person_id']] ??= [];
        }
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
            ];
        }
        usort($result, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $result;
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
