import type { FilterOp, GridColumn, GridFilter } from './types';

export function text(value: unknown): string {
    if (typeof value === 'string') {
        return value;
    }
    if (typeof value === 'number' || typeof value === 'bigint') {
        return String(value);
    }
    if (typeof value === 'boolean') {
        return value ? 'ja' : 'nein';
    }

    return '';
}

/** Werte einer Mehrfachauswahl («A; B» → ['A', 'B']). */
export function splitMulti<T>(column: GridColumn<T>, value: unknown): string[] {
    return text(value)
        .split(column.separator ?? ';')
        .map((v) => v.trim())
        .filter(Boolean);
}

/** JJJJ-MM-TT → TT.MM.JJJJ */
export function formatDate(value: unknown): string {
    const raw = text(value);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);

    return match ? `${match[3]}.${match[2]}.${match[1]}` : raw;
}

/** Anzeigetext einer Zelle (Auswahl → Bezeichnung, Datum → TT.MM.JJJJ, Bool → Ja/Nein). */
export function display<T>(column: GridColumn<T>, value: unknown): string {
    switch (column.type) {
        case 'select':
            return column.options?.[text(value)] ?? text(value);
        case 'date':
            return formatDate(value);
        case 'bool':
            return value === true ? 'Ja' : value === false ? 'Nein' : '';
        default:
            return text(value);
    }
}

let measureContext: CanvasRenderingContext2D | null = null;

/** Textbreite in Pixeln (für automatische Spaltenbreite). */
export function measure(value: string, weight = 400): number {
    if (typeof document === 'undefined') {
        return value.length * 8;
    }
    measureContext ??= document.createElement('canvas').getContext('2d');
    if (!measureContext) {
        return value.length * 8;
    }
    const family = getComputedStyle(document.body).fontFamily || 'sans-serif';
    measureContext.font = `${weight} 14px ${family}`;

    return measureContext.measureText(value).width;
}

/** Tabulatorgetrennter Text (Excel, Google Sheets) → Zeilen und Zellen; beachtet Anführungszeichen. */
export function parseTsv(input: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let quoted = false;
    const textIn = input.replace(/\r\n?/g, '\n');

    for (let i = 0; i < textIn.length; i++) {
        const char = textIn[i];
        if (quoted) {
            if (char === '"' && textIn[i + 1] === '"') {
                cell += '"';
                i++;
            } else if (char === '"') {
                quoted = false;
            } else {
                cell += char;
            }
        } else if (char === '"' && cell === '') {
            quoted = true;
        } else if (char === '\t') {
            row.push(cell);
            cell = '';
        } else if (char === '\n') {
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
        } else {
            cell += char;
        }
    }
    if (cell !== '' || row.length > 0) {
        row.push(cell);
        rows.push(row);
    }

    return rows;
}

/** Zeilen und Zellen → tabulatorgetrennter Text (Anführungszeichen bei Tab, Zeilenumbruch, "). */
export function toTsv(rows: string[][]): string {
    return rows
        .map((row) =>
            row
                .map((cell) =>
                    /[\t\n"]/.test(cell)
                        ? `"${cell.replace(/"/g, '""')}"`
                        : cell,
                )
                .join('\t'),
        )
        .join('\n');
}

/**
 * Eingefügten Text in den Wert der Spalte umwandeln.
 * Liefert undefined, wenn der Text nicht passt (z. B. unbekannte Auswahl).
 */
export function coerce<T>(column: GridColumn<T>, input: string): unknown {
    const raw = input.trim();

    switch (column.type) {
        case 'bool': {
            const v = raw.toLowerCase();
            if (['ja', 'j', 'yes', 'true', 'wahr', '1', 'x', '✓'].includes(v)) {
                return true;
            }
            if (['nein', 'n', 'no', 'false', 'falsch', '0', ''].includes(v)) {
                return false;
            }

            return undefined;
        }
        case 'select': {
            if (raw === '') {
                return null;
            }
            const options = column.options ?? {};
            if (raw in options) {
                return raw;
            }
            const byLabel = Object.entries(options).find(
                ([, label]) => label.toLowerCase() === raw.toLowerCase(),
            );

            return byLabel ? byLabel[0] : undefined;
        }
        case 'multi': {
            // Nur erlaubte Werte (Schlüssel oder Bezeichnung), Reihenfolge der Auswahlliste
            const options = column.options ?? {};
            const wanted = splitMulti(column, raw).map((v) => v.toLowerCase());
            const keys = Object.entries(options)
                .filter(
                    ([key, label]) =>
                        wanted.includes(key.toLowerCase()) ||
                        wanted.includes(label.toLowerCase()),
                )
                .map(([key]) => key);
            if (keys.length < wanted.length) {
                return undefined;
            }

            return keys.length === 0
                ? null
                : keys.join(`${column.separator ?? ';'} `);
        }
        case 'date': {
            if (raw === '') {
                return null;
            }
            if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
                return raw;
            }
            const m = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(raw);

            return m
                ? `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`
                : undefined;
        }
        default:
            return raw === '' ? null : raw;
    }
}

export const OPS: Record<
    NonNullable<GridColumn<unknown>['type']>,
    FilterOp[]
> = {
    text: ['contains', 'starts', 'ends', 'equals', 'empty', 'notEmpty'],
    email: ['contains', 'starts', 'ends', 'equals', 'empty', 'notEmpty'],
    tel: ['contains', 'starts', 'ends', 'equals', 'empty', 'notEmpty'],
    select: ['in', 'notIn', 'empty', 'notEmpty'],
    multi: ['in', 'notIn', 'empty', 'notEmpty'],
    bool: ['yes', 'no', 'empty'],
    date: [
        'equals',
        'before',
        'after',
        'between',
        'today',
        'thisWeek',
        'thisMonth',
        'lastMonth',
        'thisYear',
        'empty',
        'notEmpty',
    ],
};

export const OP_LABELS: Record<FilterOp, string> = {
    contains: 'enthält',
    starts: 'beginnt mit',
    ends: 'endet mit',
    equals: 'ist genau',
    empty: 'ist leer',
    notEmpty: 'ist nicht leer',
    in: 'ist einer von',
    notIn: 'ist keiner von',
    yes: 'Ja',
    no: 'Nein',
    before: 'vor',
    after: 'nach',
    between: 'zwischen',
    today: 'heute',
    thisWeek: 'diese Woche',
    thisMonth: 'dieser Monat',
    lastMonth: 'letzter Monat',
    thisYear: 'dieses Jahr',
};

/** Bezeichnung einer Bedingung (bei Mehrfachauswahl «enthält eine von»). */
export function opLabel<T>(column: GridColumn<T>, op: FilterOp): string {
    if (column.type === 'multi' && (op === 'in' || op === 'notIn')) {
        return op === 'in' ? 'enthält eine von' : 'enthält keine von';
    }

    return OP_LABELS[op];
}

/** Ops ohne Eingabewert */
export const VALUELESS: FilterOp[] = [
    'empty',
    'notEmpty',
    'yes',
    'no',
    'today',
    'thisWeek',
    'thisMonth',
    'lastMonth',
    'thisYear',
];

function isoDate(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/** Zeitraum [von, bis] (inklusive) für relative Datumsfilter. */
function range(op: FilterOp, now = new Date()): [string, string] | null {
    const day = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    switch (op) {
        case 'today':
            return [isoDate(day), isoDate(day)];
        case 'thisWeek': {
            const start = new Date(day);
            start.setDate(day.getDate() - ((day.getDay() + 6) % 7));
            const end = new Date(start);
            end.setDate(start.getDate() + 6);

            return [isoDate(start), isoDate(end)];
        }
        case 'thisMonth':
            return [
                isoDate(new Date(day.getFullYear(), day.getMonth(), 1)),
                isoDate(new Date(day.getFullYear(), day.getMonth() + 1, 0)),
            ];
        case 'lastMonth':
            return [
                isoDate(new Date(day.getFullYear(), day.getMonth() - 1, 1)),
                isoDate(new Date(day.getFullYear(), day.getMonth(), 0)),
            ];
        case 'thisYear':
            return [`${day.getFullYear()}-01-01`, `${day.getFullYear()}-12-31`];
        default:
            return null;
    }
}

/** Ist der Filter vollständig (sonst wird er ignoriert)? */
export function isActive(filter: GridFilter | undefined): boolean {
    if (!filter) {
        return false;
    }
    if (VALUELESS.includes(filter.op)) {
        return true;
    }
    if (filter.op === 'in' || filter.op === 'notIn') {
        return (filter.values ?? []).length > 0;
    }
    if (filter.op === 'between') {
        return Boolean(filter.value) && Boolean(filter.value2);
    }

    return (filter.value ?? '') !== '';
}

export function matches<T>(
    column: GridColumn<T>,
    value: unknown,
    filter: GridFilter,
): boolean {
    const raw = text(value);
    const shown = display(column, value).toLowerCase();
    const wanted = (filter.value ?? '').toLowerCase();
    const isEmpty = value === null || value === undefined || raw === '';

    switch (filter.op) {
        case 'empty':
            return isEmpty;
        case 'notEmpty':
            return !isEmpty;
        case 'contains':
            return shown.includes(wanted);
        case 'starts':
            return shown.startsWith(wanted);
        case 'ends':
            return shown.endsWith(wanted);
        case 'equals':
            return column.type === 'date'
                ? raw === filter.value
                : shown === wanted;
        case 'in':
        case 'notIn': {
            // Mehrfachauswahl: enthält eine (bzw. keine) der gewählten Werte; «» = leer
            const hit =
                column.type === 'multi'
                    ? (filter.values ?? []).some((v) =>
                          v === ''
                              ? isEmpty
                              : splitMulti(column, value).includes(v),
                      )
                    : (filter.values ?? []).includes(raw);

            return filter.op === 'in' ? hit : !hit;
        }
        case 'yes':
            return value === true;
        case 'no':
            return value === false;
        case 'before':
            return !isEmpty && raw < (filter.value ?? '');
        case 'after':
            return !isEmpty && raw > (filter.value ?? '');
        case 'between':
            return (
                !isEmpty &&
                raw >= (filter.value ?? '') &&
                raw <= (filter.value2 ?? '')
            );
        default: {
            const span = range(filter.op);

            return (
                span !== null && !isEmpty && raw >= span[0] && raw <= span[1]
            );
        }
    }
}

/** Kurzbeschreibung eines Filters für die Filterzeile. */
export function describe<T>(column: GridColumn<T>, filter: GridFilter): string {
    const label = opLabel(column, filter.op);
    if (VALUELESS.includes(filter.op)) {
        return label;
    }
    if (filter.op === 'in' || filter.op === 'notIn') {
        const names = (filter.values ?? []).map(
            (v) => column.options?.[v] ?? (v === '' ? '(leer)' : v),
        );

        return `${label}: ${names.join(', ')}`;
    }
    if (filter.op === 'between') {
        return `${formatDate(filter.value)} – ${formatDate(filter.value2)}`;
    }

    return `${label} «${column.type === 'date' ? formatDate(filter.value) : (filter.value ?? '')}»`;
}
