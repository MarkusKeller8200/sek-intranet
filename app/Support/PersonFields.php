<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Felder der Personen aus dem Schema (config/intranet.php → schema.users):
 * Gruppen für Detailansicht und Formular, Sichtbarkeit, Validierung, Namensbildung.
 *
 * @phpstan-type Field array{key: string, label: string, type: string, group: string, required: bool, computed: bool, admin: bool, input: string|null, options: array<string, string>|null}
 */
final class PersonFields
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private static function schema(): array
    {
        /** @var array<string, array<string, mixed>> $fields */
        $fields = (array) config('intranet.schema.users.fields', []);

        return $fields;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function groupConfig(): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = (array) config('intranet.schema.users.groups', []);

        return $groups;
    }

    /**
     * Gruppen mit ihren Feldern für die Oberfläche; Admin-Gruppen nur für die Administration.
     *
     * @return list<array{key: string, label: string, description: string, admin: bool, fields: list<Field>}>
     */
    public static function groups(bool $admin): array
    {
        $result = [];

        foreach (self::groupConfig() as $key => $group) {
            $isAdmin = ($group['admin'] ?? false) === true;
            if ($isAdmin && ! $admin) {
                continue;
            }

            $fields = array_values(array_filter(self::fields($admin), fn (array $f): bool => $f['group'] === $key));
            if ($fields !== []) {
                $result[] = [
                    'key' => (string) $key,
                    'label' => (string) ($group['label'] ?? $key),
                    'description' => (string) ($group['description'] ?? ''),
                    'admin' => $isAdmin,
                    'fields' => $fields,
                ];
            }
        }

        return $result;
    }

    /**
     * @return list<Field>
     */
    public static function fields(bool $admin): array
    {
        $groups = self::groupConfig();
        $fields = [];

        foreach (self::schema() as $key => $field) {
            $group = (string) ($field['group'] ?? 'person');
            $isAdmin = ($groups[$group]['admin'] ?? false) === true;
            if ($isAdmin && ! $admin) {
                continue;
            }

            $values = $field['values'] ?? null;
            $fields[] = [
                'key' => (string) $key,
                'label' => (string) ($field['label'] ?? $key),
                'type' => (string) ($field['type'] ?? 'string'),
                'group' => $group,
                'required' => ($field['required'] ?? false) === true,
                'computed' => ($field['computed'] ?? false) === true,
                'admin' => $isAdmin,
                'input' => isset($field['input']) ? (string) $field['input'] : null,
                'options' => is_string($values) ? array_map('strval', (array) config("intranet.{$values}")) : null,
            ];
        }

        return $fields;
    }

    /**
     * Schlüssel der Felder, die eine Person ohne Administrationsrecht nicht sehen darf.
     *
     * @return list<string>
     */
    public static function adminOnlyKeys(): array
    {
        return array_values(array_map(
            fn (array $f): string => $f['key'],
            array_filter(self::fields(true), fn (array $f): bool => $f['admin']),
        ));
    }

    /**
     * Entfernt Admin-Felder aus einem Datensatz.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    public static function visible(array $person, bool $admin): array
    {
        return $admin ? $person : array_diff_key($person, array_flip(self::adminOnlyKeys()));
    }

    /**
     * Validierungsregeln aus dem Schema (Felder, die die Oberfläche bearbeiten kann).
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $rules = [];

        foreach (self::schema() as $key => $field) {
            $type = (string) ($field['type'] ?? 'string');
            $max = (int) ($field['max'] ?? ($type === 'text' ? 5000 : 200));
            $rule = [($field['required'] ?? false) === true ? 'required' : 'nullable'];

            $rule = array_merge($rule, match ($type) {
                'email' => ['email', 'max:200'],
                'bool' => ['boolean'],
                'date' => ['date_format:Y-m-d'],
                'enum' => [Rule::in(array_keys((array) config('intranet.'.((string) ($field['values'] ?? '')))))],
                default => ['string', "max:{$max}"],
            });

            // Bool: fehlend = false, daher nie «required»
            $rules[(string) $key] = $type === 'bool' ? ['boolean'] : $rule;
        }

        return $rules;
    }

    /**
     * Name, Vorname und Nachname aufeinander abstimmen.
     *
     * Sind Vor-/Nachname gesetzt, wird der Anzeigename daraus gebildet. Fehlen sie
     * (ältere Daten, Import aus Entra), werden sie aus dem Anzeigenamen abgeleitet:
     * letztes Wort = Nachname, Rest = Vorname.
     *
     * @param  array<string, mixed>  $person
     * @return array<string, mixed>
     */
    public static function withNames(array $person): array
    {
        $first = trim((string) ($person['first_name'] ?? ''));
        $last = trim((string) ($person['last_name'] ?? ''));

        if ($last === '' && $first === '') {
            $name = trim(preg_replace('/\s+/', ' ', (string) ($person['name'] ?? '')) ?? '');
            if ($name === '') {
                return $person;
            }
            $parts = explode(' ', $name);
            $last = (string) array_pop($parts);
            $first = implode(' ', $parts);
        }

        $person['first_name'] = $first !== '' ? $first : null;
        $person['last_name'] = $last !== '' ? $last : null;
        $person['name'] = trim($first.' '.$last);

        return $person;
    }
}
