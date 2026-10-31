import { formatDate, text } from '@/components/data-grid';
import type { GridColumn } from '@/components/data-grid';
import type { Person, PersonField } from '@/types';

/** Feldtyp aus dem Schema → Spaltentyp der Tabelle. */
export function gridType(field: PersonField): GridColumn<Person>['type'] {
    switch (field.type) {
        case 'enum':
            return 'select';
        case 'bool':
            return 'bool';
        case 'email':
            return 'email';
        case 'date':
            return 'date';
        default:
            return field.input === 'tel' ? 'tel' : 'text';
    }
}

/** Anzeigewert eines Felds (Auswahl → Bezeichnung, Datum → TT.MM.JJJJ, Bool → Ja/Nein). */
export function displayValue(field: PersonField, value: unknown): string {
    if (field.type === 'enum') {
        return field.options?.[text(value)] ?? text(value);
    }
    if (field.type === 'date') {
        return formatDate(value);
    }
    if (field.type === 'bool') {
        return value === true ? 'Ja' : 'Nein';
    }

    return text(value);
}

/** In der Tabelle anfangs sichtbare Spalten; alle anderen über «Spalten» einblendbar. */
export const DEFAULT_VISIBLE = [
    'last_name',
    'first_name',
    'person_type',
    'function',
    'school',
    'email',
    'phone',
    'role',
    'active',
];

/** Anfangs sichtbare Spalten der Seite «Schüler:innen». */
export const STUDENT_VISIBLE = [
    'last_name',
    'first_name',
    'class_name',
    'school',
    'email',
    'device_inventory',
    'active',
];
