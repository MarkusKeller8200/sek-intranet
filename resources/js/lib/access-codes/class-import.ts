/**
 * Klassenliste einlesen (Excel .xlsx oder CSV) – nur im Browser, die Datei verlässt das Gerät nie.
 * Spalten werden über die Überschriften erkannt und lassen sich im Dialog anpassen.
 */

export type ImportField =
    | 'nachname'
    | 'vorname'
    | 'benutzer'
    | 'inventar'
    | 'lehrperson'
    | 'klasse';

export const IMPORT_FIELDS: { key: ImportField; label: string }[] = [
    { key: 'nachname', label: 'Nachname' },
    { key: 'vorname', label: 'Vorname' },
    { key: 'klasse', label: 'Klasse' },
    { key: 'benutzer', label: 'Benutzername (E-Mail)' },
    { key: 'inventar', label: 'iPad-Inventarnummer' },
    { key: 'lehrperson', label: 'Lehrperson' },
];

/** Übliche Überschriften (Escola-Export, Excel-Listen der Schulhäuser). */
const SYNONYMS: Record<ImportField, string[]> = {
    nachname: ['nachname', 'name', 'familienname', 'last name', 'lastname', 'surname'],
    vorname: ['vorname', 'rufname', 'first name', 'firstname', 'given name'],
    klasse: ['klasse', 'klassenbezeichnung', 'klasse kurz', 'class', 'gruppe'],
    benutzer: ['benutzername', 'e-mail', 'email', 'mail', 'e-mail schule', 'mail schule', 'upn', 'login', 'benutzer'],
    inventar: ['inventarnummer', 'inventar', 'ipad', 'ipad-nr', 'ipad nr', 'geräte-nr', 'gerätenummer', 'asset tag'],
    lehrperson: ['lehrperson', 'klassenlehrperson', 'klassenlehrer', 'klassenlehrerin', 'klp', 'lp'],
};

export type Table = { headers: string[]; rows: string[][] };
export type Mapping = Record<ImportField, number>;

const norm = (s: string) =>
    s
        .toLowerCase()
        .replace(/[_.:]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

/** Spalten anhand der Überschriften zuordnen (-1 = nicht vorhanden). */
export function guessMapping(headers: string[]): Mapping {
    const h = headers.map(norm);
    const used = new Set<number>();
    const mapping = {} as Mapping;
    for (const { key } of IMPORT_FIELDS) {
        let index = h.findIndex((x, i) => !used.has(i) && SYNONYMS[key].includes(x));
        if (index < 0) {
            index = h.findIndex(
                (x, i) => !used.has(i) && SYNONYMS[key].some((s) => s.length > 3 && x.includes(s)),
            );
        }
        if (index >= 0) {
            used.add(index);
        }
        mapping[key] = index;
    }

    return mapping;
}

/** Zeile mit den Überschriften: die erste der ersten 10 Zeilen mit mindestens zwei erkannten Spalten. */
function splitHeader(all: string[][]): Table {
    let best = 0;
    for (let i = 0; i < Math.min(10, all.length); i++) {
        const m = guessMapping(all[i]);
        if (Object.values(m).filter((v) => v >= 0).length >= 2) {
            best = i;
            break;
        }
    }
    const headers = (all[best] ?? []).map((x) => x.trim());
    const rows = all
        .slice(best + 1)
        .filter((r) => r.some((c) => c.trim() !== ''))
        .map((r) => headers.map((_, i) => (r[i] ?? '').trim()));

    return { headers, rows };
}

// ---------- CSV ----------

function decodeText(bytes: Uint8Array): string {
    const utf8 = new TextDecoder('utf-8').decode(bytes);
    // Excel speichert CSV oft in Windows-1252: dann tauchen Ersatzzeichen auf
    const text = utf8.includes('�') ? new TextDecoder('windows-1252').decode(bytes) : utf8;

    return text.charCodeAt(0) === 0xfeff ? text.slice(1) : text;
}

export function parseCsvTable(text: string): string[][] {
    const first = text.split(/\r?\n/)[0] ?? '';
    const delim = first.includes('\t') ? '\t' : first.includes(';') ? ';' : ',';
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = '';
    let quoted = false;
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (quoted) {
            if (ch === '"' && text[i + 1] === '"') {
                cell += '"';
                i++;
            } else if (ch === '"') {
                quoted = false;
            } else {
                cell += ch;
            }
        } else if (ch === '"') {
            quoted = true;
        } else if (ch === delim) {
            row.push(cell);
            cell = '';
        } else if (ch === '\n' || ch === '\r') {
            if (ch === '\r' && text[i + 1] === '\n') {
                i++;
            }
            row.push(cell);
            rows.push(row);
            row = [];
            cell = '';
        } else {
            cell += ch;
        }
    }
    if (cell !== '' || row.length > 0) {
        row.push(cell);
        rows.push(row);
    }

    return rows;
}

// ---------- XLSX (ZIP + XML, ohne Bibliothek) ----------

async function unzip(bytes: Uint8Array): Promise<Map<string, Uint8Array>> {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let eocd = -1;
    for (let i = bytes.length - 22; i >= Math.max(0, bytes.length - 65557); i--) {
        if (view.getUint32(i, true) === 0x06054b50) {
            eocd = i;
            break;
        }
    }
    if (eocd < 0) {
        throw new Error('Keine gültige Excel-Datei (.xlsx).');
    }
    const count = view.getUint16(eocd + 10, true);
    let offset = view.getUint32(eocd + 16, true);
    const files = new Map<string, Uint8Array>();
    const decoder = new TextDecoder();

    for (let n = 0; n < count; n++) {
        if (view.getUint32(offset, true) !== 0x02014b50) {
            break;
        }
        const method = view.getUint16(offset + 10, true);
        const size = view.getUint32(offset + 20, true);
        const nameLength = view.getUint16(offset + 28, true);
        const extraLength = view.getUint16(offset + 30, true);
        const commentLength = view.getUint16(offset + 32, true);
        const local = view.getUint32(offset + 42, true);
        const name = decoder.decode(bytes.subarray(offset + 46, offset + 46 + nameLength));
        offset += 46 + nameLength + extraLength + commentLength;

        if (!/^xl\/(sharedStrings\.xml|workbook\.xml|worksheets\/sheet\d+\.xml)$/.test(name)) {
            continue;
        }
        const start =
            local + 30 + view.getUint16(local + 26, true) + view.getUint16(local + 28, true);
        const data = bytes.subarray(start, start + size);
        if (method === 0) {
            files.set(name, data);
        } else if (method === 8) {
            const stream = new Blob([data as BlobPart]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
            files.set(name, new Uint8Array(await new Response(stream).arrayBuffer()));
        }
    }

    return files;
}

function columnIndex(ref: string): number {
    const letters = /^[A-Z]+/.exec(ref)?.[0] ?? 'A';
    let n = 0;
    for (const c of letters) {
        n = n * 26 + (c.charCodeAt(0) - 64);
    }

    return n - 1;
}

async function parseXlsx(bytes: Uint8Array): Promise<string[][]> {
    const files = await unzip(bytes);
    const decoder = new TextDecoder();
    const parse = (name: string) => {
        const data = files.get(name);

        return data ? new DOMParser().parseFromString(decoder.decode(data), 'application/xml') : null;
    };
    const strings: string[] = [];
    const shared = parse('xl/sharedStrings.xml');
    shared?.querySelectorAll('si').forEach((si) => {
        strings.push(Array.from(si.querySelectorAll('t')).map((t) => t.textContent ?? '').join(''));
    });
    // erstes Tabellenblatt
    const sheetName = [...files.keys()].filter((k) => k.startsWith('xl/worksheets/')).sort()[0];
    const sheet = sheetName ? parse(sheetName) : null;
    if (!sheet) {
        throw new Error('Kein Tabellenblatt gefunden.');
    }
    const rows: string[][] = [];
    sheet.querySelectorAll('sheetData > row').forEach((r) => {
        const row: string[] = [];
        r.querySelectorAll('c').forEach((c) => {
            const type = c.getAttribute('t');
            const v = c.querySelector('v')?.textContent ?? '';
            const value =
                type === 's'
                    ? (strings[Number(v)] ?? '')
                    : type === 'inlineStr'
                      ? Array.from(c.querySelectorAll('is t')).map((t) => t.textContent ?? '').join('')
                      : v;
            row[columnIndex(c.getAttribute('r') ?? '')] = value;
        });
        rows.push(Array.from(row, (x) => x ?? ''));
    });

    return rows;
}

/** Datei lesen: .xlsx oder CSV/Text. */
export async function readTable(file: File): Promise<Table> {
    const bytes = new Uint8Array(await file.arrayBuffer());
    const isZip = bytes[0] === 0x50 && bytes[1] === 0x4b;
    if (isZip) {
        return splitHeader(await parseXlsx(bytes));
    }
    if (/\.xls$/i.test(file.name)) {
        throw new Error('Altes Excel-Format (.xls): bitte in Excel als .xlsx oder CSV speichern.');
    }

    return splitHeader(parseCsvTable(decodeText(bytes)));
}

/** Jahrgang aus der Klassenbezeichnung (erste Ziffer, z. B. «M_A1a» → 1). */
export function yearOf(klasse: string): number | null {
    const digit = /[1-3]/.exec(klasse);

    return digit ? Number(digit[0]) : null;
}

/** Benutzername nach Schulkonvention vorname.nachname@domain (ohne Umlaute, nur a-z). */
export function usernameFor(vorname: string, nachname: string, domain: string): string {
    const clean = (s: string) =>
        s
            .toLowerCase()
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/ı/g, 'i')
            .replace(/[^a-z]/g, '');
    const local = [clean(vorname), clean(nachname)].filter(Boolean).join('.');

    return local ? `${local}@${domain}` : '';
}
