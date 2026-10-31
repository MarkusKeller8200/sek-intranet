<?php

namespace App\Http\Controllers;

use App\Data\Contracts\UserRepository;
use App\Support\PersonTypes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fachstelle ICT → Geräteausgabe: Zugangsdaten-Generator (Passwörter entstehen nur im Browser
 * und werden nicht gespeichert) und die geplanten Funktionen der Jamf-Anbindung.
 * Schüler:innen lassen sich aus einer Datei oder direkt aus dem Intranet laden (gewählte Klassen).
 */
class DeviceHandoutController extends Controller
{
    public function __construct(private readonly UserRepository $users) {}

    public function __invoke(): Response
    {
        return Inertia::render('ict/device-handout', [
            'mailDomain' => (string) config('intranet.student_mail_domain', 'edu.sekbuelach.ch'),
            'planned' => [
                'features' => array_values(array_map('strval', (array) config('intranet.modules.ict.items.geraeteausgabe.features', []))),
            ],
            'classes' => $this->classes(),
        ]);
    }

    /**
     * Schüler:innen der gewählten Klassen (nur aktive), mit Klassenlehrperson.
     */
    public function students(Request $request): JsonResponse
    {
        $wanted = array_values(array_filter(array_map('strval', (array) $request->query('klassen', []))));
        if ($wanted === []) {
            return response()->json(['message' => 'Bitte mindestens eine Klasse wählen.'], 422);
        }
        $wanted = array_flip(array_slice($wanted, 0, 100));
        $teachers = $this->classTeachers();

        $students = [];
        foreach ($this->pupils() as $p) {
            $class = (string) ($p['class_name'] ?? '');
            if (! isset($wanted[$class])) {
                continue;
            }
            $students[] = [
                'nachname' => (string) ($p['last_name'] ?? ''),
                'vorname' => (string) ($p['first_name'] ?? ''),
                'benutzer' => mb_strtolower((string) ($p['email'] ?? '')),
                'inventar' => (string) ($p['device_inventory'] ?? ''),
                'lehrperson' => $teachers[$class] ?? '',
                'klasse' => $class,
            ];
        }
        usort($students, fn (array $a, array $b): int => [$a['klasse'], $a['nachname'], $a['vorname']] <=> [$b['klasse'], $b['nachname'], $b['vorname']]);

        return response()->json(['data' => $students])->header('Cache-Control', 'no-store');
    }

    /**
     * @return list<array{name: string, school: string, year: int|null, count: int, teacher: string}>
     */
    private function classes(): array
    {
        $teachers = $this->classTeachers();
        $classes = [];
        foreach ($this->pupils() as $p) {
            $name = trim((string) ($p['class_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $classes[$name] ??= [
                'name' => $name,
                'school' => (string) ($p['school'] ?? ''),
                'year' => preg_match('/[1-3]/', $name, $m) === 1 ? (int) $m[0] : null,
                'count' => 0,
                'teacher' => $teachers[$name] ?? '',
            ];
            $classes[$name]['count']++;
        }
        ksort($classes, SORT_NATURAL);

        return array_values($classes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pupils(): array
    {
        return array_values(array_filter($this->users->active(), fn (array $p): bool => PersonTypes::of($p) === PersonTypes::STUDENT));
    }

    /**
     * Klasse → Name der Klassenlehrperson (Lehrperson mit dieser Klasse im Feld «Klasse»).
     *
     * @return array<string, string>
     */
    private function classTeachers(): array
    {
        $teachers = [];
        foreach ($this->users->active() as $p) {
            $class = trim((string) ($p['class_name'] ?? ''));
            if ($class !== '' && in_array(PersonTypes::of($p), ['lehrperson', 'schulleitung'], true)) {
                $teachers[$class] = isset($teachers[$class]) ? $teachers[$class].', '.$p['name'] : (string) ($p['name'] ?? '');
            }
        }

        return $teachers;
    }
}
