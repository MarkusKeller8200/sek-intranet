<?php

namespace App\Support;

use App\Data\Contracts\UserRepository;
use Illuminate\Validation\Validator;

/**
 * Prüft Personendaten nach Schema (Formular, Tabelle, API): Namen bilden,
 * E-Mails normalisieren, eindeutige E-Mail. Liefert einen vollständigen Datensatz
 * (fehlende Felder = null) oder die Fehler je Feld.
 */
final class PersonValidator
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{data: array<string, mixed>}|array{errors: array<string, string>}
     */
    public function check(array $input, ?string $ignoreId = null): array
    {
        $input = PersonFields::withNames($input);
        // Ohne Angabe: Art aus der Rolle ableiten (ältere Formulare, API, Importe)
        if (($input['person_type'] ?? null) === null || $input['person_type'] === '') {
            $input['person_type'] = PersonTypes::fromRole(is_string($input['role'] ?? null) ? $input['role'] : null);
        }
        if (($input['email'] ?? null) === '') {
            $input['email'] = null;
        }
        foreach (['email', 'private_email'] as $key) {
            if (is_string($input[$key] ?? null)) {
                $input[$key] = mb_strtolower(trim($input[$key]));
            }
        }

        $rules = PersonFields::rules();
        $validator = validator($input, $rules, [], array_map(
            fn (array $f): string => $f['label'],
            array_column(PersonFields::fields(true), null, 'key'),
        ));

        // Eindeutige E-Mail (in der Datenbank später ein UNIQUE-Index).
        $validator->after(function (Validator $validator) use ($input, $ignoreId): void {
            if (($input['email'] ?? null) === null) {
                return;
            }
            $existing = $this->users->findByEmail((string) $input['email']);

            if ($existing !== null && ($existing['id'] ?? null) !== $ignoreId) {
                $validator->errors()->add('email', 'Diese E-Mail-Adresse ist bereits erfasst.');
            }
        });

        if ($validator->fails()) {
            return ['errors' => array_map(fn (array $m): string => (string) $m[0], $validator->errors()->toArray())];
        }

        $data = $validator->validated();
        $complete = [];
        foreach (array_keys($rules) as $key) {
            $complete[$key] = $data[$key] ?? null;
        }
        $complete['active'] = filter_var($input['active'] ?? false, FILTER_VALIDATE_BOOL);

        return ['data' => $complete];
    }
}
