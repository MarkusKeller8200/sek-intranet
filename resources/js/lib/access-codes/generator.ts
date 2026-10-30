// i18n: nicht übersetzen (Inhalte von PDF, Kärtchen und Skript bleiben deutsch)
/**
 * Zugangsdaten-Generator (Geräteausgabe): Passwörter und PINs für Schüler-Logins.
 * Läuft nur im Browser – es werden keine Daten an den Server gesendet.
 * Logik aus zugangsdaten-generator.html übernommen.
 */

export type Student = {
    nachname: string;
    vorname: string;
    benutzer: string;
    inventar: string;
    lehrperson: string;
    klasse: string;
    pw: string;
    pin: string;
};

export type PasswordStyle = 'animalcolor' | 'word' | 'random';

const WORDS = [
    'Falke', 'Panda', 'Adler', 'Fuchs', 'Delfin', 'Puma', 'Koala', 'Biber', 'Igel', 'Otter',
    'Wolke', 'Rakete', 'Planet', 'Komet', 'Vulkan', 'Gletscher', 'Kaktus', 'Ahorn', 'Zeder', 'Klippe',
    'Drache', 'Ritter', 'Pirat', 'Kompass', 'Leuchtturm', 'Zauber', 'Kristall', 'Fossil', 'Meteor', 'Turbine',
];

type Gender = 'm' | 'f' | 'n';

const ANIMALS: { name: string; gender: Gender }[] = [
    { name: 'Falke', gender: 'm' }, { name: 'Panda', gender: 'm' }, { name: 'Adler', gender: 'm' },
    { name: 'Fuchs', gender: 'm' }, { name: 'Delfin', gender: 'm' }, { name: 'Puma', gender: 'm' },
    { name: 'Koala', gender: 'm' }, { name: 'Biber', gender: 'm' }, { name: 'Igel', gender: 'm' },
    { name: 'Otter', gender: 'm' }, { name: 'Hase', gender: 'm' }, { name: 'Luchs', gender: 'm' },
    { name: 'Tiger', gender: 'm' }, { name: 'Wolf', gender: 'm' }, { name: 'Pinguin', gender: 'm' },
    { name: 'Papagei', gender: 'm' }, { name: 'Elefant', gender: 'm' }, { name: 'Zebra', gender: 'n' },
    { name: 'Krake', gender: 'f' }, { name: 'Reh', gender: 'n' },
];

// stem = Adjektivstamm; unveränderliche Farben (Lehnwörter) ohne Endung
const COLORS: { stem: string; invariable: boolean }[] = [
    { stem: 'Rot', invariable: false }, { stem: 'Blau', invariable: false }, { stem: 'Weiss', invariable: false },
    { stem: 'Gelb', invariable: false }, { stem: 'Orange', invariable: true }, { stem: 'Lila', invariable: true },
    { stem: 'Rosa', invariable: true }, { stem: 'Golden', invariable: false }, { stem: 'Grau', invariable: false },
    { stem: 'Braun', invariable: false },
];

const GENDER_ENDING: Record<Gender, string> = { m: 'er', f: 'e', n: 'es' };

/** Sonderzeichen an gleicher Stelle auf DE/CH/US/UK-Tastaturen. */
export const SAFE_SYMBOLS = ['!', '-', '_', '+', '.'];

const AMBIG = 'Il1O0';

/** Zufallszahl 0 … max-1 aus dem Kryptografie-Zufall des Browsers (ohne Verzerrung). */
export function randInt(max: number): number {
    const limit = Math.floor(0x100000000 / max) * max;
    const buffer = new Uint32Array(1);
    let value = 0;
    do {
        crypto.getRandomValues(buffer);
        value = buffer[0];
    } while (value >= limit);

    return value % max;
}

function pick<T>(list: T[]): T {
    return list[randInt(list.length)];
}

export function generatePassword(
    len: number,
    style: PasswordStyle,
    avoidAmbig: boolean,
    selectedSymbols: string[],
): string {
    const symbols = selectedSymbols.length > 0 ? selectedSymbols : SAFE_SYMBOLS;
    if (style === 'animalcolor') {
        const animal = pick(ANIMALS);
        const color = pick(COLORS);
        const colorWord = color.invariable
            ? color.stem
            : color.stem + GENDER_ENDING[animal.gender];
        const num = String(randInt(100)).padStart(2, '0');

        return colorWord + animal.name + pick(symbols) + num;
    }
    if (style === 'word') {
        const num = String(randInt(100)).padStart(2, '0');

        return pick(WORDS) + num + pick(symbols);
    }
    let charset =
        'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789' +
        symbols.join('');
    if (!avoidAmbig) {
        charset += AMBIG;
    }
    let pw = '';
    for (let i = 0; i < len; i++) {
        pw += charset[randInt(charset.length)];
    }

    return pw;
}

/** 6 Ziffern, erste Ziffer 1–9 (nie führende 0). */
export function generatePin(): string {
    let pin = String(1 + randInt(9));
    for (let i = 0; i < 5; i++) {
        pin += String(randInt(10));
    }

    return pin;
}

function detectDelimiter(line: string): string | null {
    if (line.includes('\t')) {
        return '\t';
    }
    if (line.includes(';')) {
        return ';';
    }
    if (line.includes(',')) {
        return ',';
    }

    return null;
}

/** Eingefügte Liste: Nachname / Vorname / Benutzername / iPad-Nr. / Lehrperson / Klasse. */
export function parseInput(text: string): Omit<Student, 'pw' | 'pin'>[] {
    return text
        .split('\n')
        .map((l) => l.trim())
        .filter((l) => l.length > 0)
        .map((line) => {
            const delim = detectDelimiter(line);
            const parts = delim
                ? line.split(delim).map((p) => p.trim())
                : [line.trim()];

            return {
                nachname: parts[0] || '',
                vorname: parts[1] || '',
                benutzer: (parts[2] || '').toLowerCase(),
                inventar: parts[3] || '',
                lehrperson: parts[4] || '',
                klasse: parts[5] || '',
            };
        });
}

/** Problem mit der E-Mail-Adresse (Schulkonvention vorname.nachname@…), null wenn gültig. */
export function getEmailIssue(email: string): string | null {
    const trimmed = (email || '').trim();
    if (!trimmed) {
        return 'fehlt';
    }
    if (/[äöüÄÖÜß]/.test(trimmed)) {
        return 'enthält Umlaute (ä/ö/ü/ß) — im Benutzernamen nicht zulässig';
    }
    if (/[áéíóúñÁÉÍÓÚÑ¿¡]/.test(trimmed)) {
        return 'enthält spanische Sonderzeichen (z. B. á/é/í/ó/ú/ñ) — im Benutzernamen nicht zulässig';
    }
    if (/[çğışÇĞİŞ]/.test(trimmed)) {
        return 'enthält türkische Sonderzeichen (z. B. ç/ğ/ı/ş) — im Benutzernamen nicht zulässig';
    }
    const atIndex = trimmed.indexOf('@');
    if (atIndex <= 0 || trimmed.indexOf('@', atIndex + 1) !== -1) {
        return 'enthält ungültige Zeichen oder falsches Format';
    }
    const local = trimmed.slice(0, atIndex);
    const domain = trimmed.slice(atIndex + 1);
    if (!/^[a-z]+(\.[a-z]+)*$/.test(local)) {
        return 'Benutzername (vor dem @) darf nur Kleinbuchstaben a-z und Punkte enthalten';
    }
    if (!/^[a-z0-9.-]+\.[a-z]{2,}$/.test(domain)) {
        return 'enthält ungültige Zeichen oder falsches Format';
    }

    return null;
}

const CSV_HEADER =
    'Nachname;Vorname;Benutzername;Inventarnummer;Lehrperson;Klasse;Passwort;PIN';

export function toCsv(data: Student[]): string {
    const rows = data.map((d) =>
        [d.nachname, d.vorname, d.benutzer, d.inventar, d.lehrperson, d.klasse, d.pw, d.pin].join(';'),
    );

    return '﻿' + [CSV_HEADER, ...rows].join('\r\n');
}

/** Frühere zugangsdaten.csv wieder einlesen (ohne neu zu würfeln). */
export function parseCsv(
    raw: string,
): { data: Student[] } | { error: string } {
    let text = raw;
    if (text.charCodeAt(0) === 0xfeff) {
        text = text.slice(1);
    }
    const lines = text
        .split(/\r\n|\n/)
        .map((l) => l.trim())
        .filter((l) => l.length > 0);
    if (lines.length < 2) {
        return { error: 'Die CSV-Datei enthält keine Datenzeilen.' };
    }
    const split = (line: string) => line.split(';').map((s) => s.trim());
    const header = split(lines[0]).map((h) => h.toLowerCase());
    const col = (name: string) => header.indexOf(name);
    const idx: Record<keyof Student, number> = {
        nachname: col('nachname'),
        vorname: col('vorname'),
        benutzer: col('benutzername'),
        inventar: col('inventarnummer'),
        lehrperson: col('lehrperson'),
        klasse: col('klasse'),
        pw: col('passwort'),
        pin: col('pin'),
    };
    if (idx.nachname === -1 && idx.vorname === -1 && idx.benutzer === -1) {
        return {
            error: 'Diese CSV scheint nicht vom Zugangsdaten-Generator zu stammen (erwartete Spalten nicht gefunden).',
        };
    }

    return {
        data: lines.slice(1).map((line) => {
            const cols = split(line);
            const get = (key: keyof Student) =>
                idx[key] >= 0 && cols[idx[key]] !== undefined ? cols[idx[key]] : '';

            return {
                nachname: get('nachname'),
                vorname: get('vorname'),
                benutzer: get('benutzer'),
                inventar: get('inventar'),
                lehrperson: get('lehrperson'),
                klasse: get('klasse'),
                pw: get('pw'),
                pin: get('pin'),
            };
        }),
    };
}

export function escapeHtml(str: string): string {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Nach Klasse gruppiert (Reihenfolge des ersten Auftretens), ohne Klasse am Schluss. */
export function groupByClass(data: Student[]): Map<string, Student[]> {
    const groups = new Map<string, Student[]>();
    data.forEach((d) => {
        const key = d.klasse && d.klasse.trim() ? d.klasse.trim() : '— ohne Klasse';
        if (!groups.has(key)) {
            groups.set(key, []);
        }
        groups.get(key)?.push(d);
    });

    return groups;
}

/** Zugangskärtchen als eigenständige HTML-Datei (je Klasse eine Druckseite). */
export function slipsHtml(data: Student[]): string {
    const groups = groupByClass(data);
    const cardHtml = (d: Student) => `
    <div class="card">
      <div class="cname">${escapeHtml(d.vorname)} ${escapeHtml(d.nachname)}</div>
      <div class="cline"><span class="clabel">Benutzer</span>${escapeHtml(d.benutzer)}</div>
      <div class="cline"><span class="clabel">Passwort</span>${escapeHtml(d.pw)}</div>
      <div class="cline"><span class="clabel">Entsperrcode</span>${escapeHtml(d.pin)}</div>
    </div>
  `;
    const keys = Array.from(groups.keys());
    const pagesHtml = keys
        .map((klasse, i) => {
            const students = groups.get(klasse) ?? [];
            const lehrpersonen = Array.from(
                new Set(students.map((s) => s.lehrperson).filter((v) => v && v.length)),
            );
            const subtitle = lehrpersonen.length
                ? ` · ${escapeHtml(lehrpersonen.join(', '))}`
                : '';
            const isLast = i === keys.length - 1;

            return `
    <div class="class-page${isLast ? ' last' : ''}">
      <h2 class="class-heading">Klasse ${escapeHtml(klasse)}${subtitle}</h2>
      <div class="card-grid">${students.map(cardHtml).join('')}</div>
    </div>`;
        })
        .join('');

    return `<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Zugangskärtchen</title>
<style>
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; margin:24px; color:#1F1D1F;}
  .toolbar{margin-bottom:20px;}
  .toolbar button{
    font-family:inherit; font-weight:600; font-size:0.9rem;
    background:#059FEB; color:#fff; border:none; border-radius:7px;
    padding:10px 18px; cursor:pointer;
  }
  .toolbar p{font-size:0.82rem; color:#5A6570; margin:8px 0 0;}
  .class-page{ page-break-after: always; }
  .class-page.last{ page-break-after: auto; }
  .class-heading{
    font-size:1.1rem; font-weight:700; margin:0 0 12px 0;
    border-bottom:2px solid #059FEB; padding-bottom:6px;
  }
  .card-grid{display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;}
  .card{border:1px dashed #999; border-radius:8px; padding:10px 12px; break-inside:avoid;}
  .card .cname{font-weight:700; font-size:0.92rem; margin-bottom:5px;}
  .card .cline{font-family:'SFMono-Regular',Consolas,monospace; font-size:0.78rem; margin:2px 0; word-break:break-all;}
  .card .clabel{color:#666; font-size:0.62rem; text-transform:uppercase; margin-right:4px;}
  @media print{ .toolbar{display:none;} }
</style>
</head>
<body>
  <div class="toolbar">
    <button onclick="window.print()">Jetzt drucken</button>
    <p>Diese Datei liegt jetzt in Ihren Downloads. Zum Drucken diesen Button oder Strg+P / Cmd+P verwenden. Jede Klasse beginnt auf einer neuen Seite.</p>
  </div>
  ${pagesHtml}
</body>
</html>`;
}

/** Datei im Browser speichern (ohne Server). */
export function downloadBlob(content: BlobPart, type: string, filename: string) {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

export const DEMO_INPUT = `Müller;Anna;anna.mueller@edu.sekbuelach.ch;104582;Urs Beispiel;M_A1a
Meier;Tom;tom.meier@edu.sekbuelach.ch;104583;Urs Beispiel;M_A1a
Keller;Lea;lea.keller@edu.sekbuelach.ch;104584;Karin Muster;M_B1b
Steiner;Noah;noah.steiner@edu.sekbuelach.ch;104585;Karin Muster;M_B1b`;
