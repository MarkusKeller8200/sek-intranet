<?php

namespace Modules\Zeiterfassung\Http;

use App\Data\Contracts\UserRepository;
use App\Http\Controllers\Controller;
use App\Support\Notifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Modules\Zeiterfassung\Data\Contracts\EintragRepository;
use Modules\Zeiterfassung\Data\Contracts\MonatRepository;
use Modules\Zeiterfassung\Kategorien;
use Modules\Zeiterfassung\Zeitkonto;
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
            'eintraege' => $liste,
            'status' => $status,
            'summen' => Zeitkonto::summen($liste),
            'kategorien' => Kategorien::all(),
            'visiertVon' => isset($status['visiert_by']) ? (string) ($this->users->find((string) $status['visiert_by'])['name'] ?? '') : null,
        ]);
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

        $rows = [];
        if ($details) {
            $rows[] = ['Person', 'E-Mail', 'Monat', 'Status', 'Datum', 'Art', 'Kategorie', 'Beginn', 'Ende', 'Pause (min)', 'Stunden', 'Projekt'];
            foreach ($personen as $p) {
                foreach ($this->eintraege->fuerPersonMonat($p['id'], $monat) as $e) {
                    $rows[] = [$p['name'], $p['email'], $monat, Kategorien::STATUS[$p['status']] ?? $p['status'], (string) $e['datum'], $e['art'] === 'abwesenheit' ? 'Abwesenheit' : 'Arbeit',
                        Kategorien::label((string) $e['kategorie']), (string) ($e['beginn'] ?? ''), (string) ($e['ende'] ?? ''), (string) ($e['pause_min'] ?? ''), self::zahl((float) $e['stunden']), (string) ($e['projekt'] ?? '')];
                }
            }
        } else {
            $rows[] = ['Person', 'E-Mail', 'Monat', 'Status', 'Arbeit (h)', 'Abwesenheit (h)', 'Total (h)', ...array_values($kategorien)];
            foreach ($personen as $p) {
                $s = $p['summen'];
                $rows[] = [$p['name'], $p['email'], $monat, Kategorien::STATUS[$p['status']] ?? $p['status'], self::zahl($s['arbeit']), self::zahl($s['abwesenheit']), self::zahl($s['total']),
                    ...array_map(fn (string $k): string => self::zahl($s['kategorien'][$k] ?? 0), array_keys($kategorien))];
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
     * @return list<array{id: string, name: string, email: string, status: string, eintraege: int, summen: array{arbeit: float, abwesenheit: float, total: float, kategorien: array<string, float>, projekte: array<string, float>}}>
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
