// Sammelt deutsche Oberflächentexte aus den React-Seiten und config/intranet.php
// und meldet, welche in den Sprachkatalogen (resources/js/i18n/*.json) fehlen.
// Aufruf: node tools/i18n-extract.mjs [--json] [--all] [--check]
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const jsDir = path.join(root, 'resources/js');
const catalogDir = path.join(jsDir, 'i18n');
const SKIP_DIRS = new Set(['i18n', 'actions', 'routes', 'wayfinder', 'ui']);

const texts = new Map(); // text -> first source
const add = (raw, source) => {
    const text = raw.replace(/\s+/g, ' ').trim();
    if (text.length < 2 || !/[A-Za-zÄÖÜäöü]{2}/.test(text)) return;
    if (/^[a-z0-9_.\-/:@{}()[\]#?=&%]+$/.test(text)) return; // Schlüssel, Pfade, Klassen
    if (/^(https?:|\/|@|\{)/.test(text) || /[{}<>]|=>|&&|\|\||;|\?\?|===|!==|\?\.|\bconst\b|\breturn\b|\)\s*:/.test(text)) return;
    if (/^[A-Z][A-Za-z]+$/.test(text) && !/[äöü]/.test(text) && text.length > 18) return; // Bezeichner
    const key = text.replace(/\d+(?:['’.,]\d+)*/g, '{n}');
    if (!texts.has(key)) texts.set(key, source);
};

const walk = (dir) => {
    for (const name of fs.readdirSync(dir)) {
        const file = path.join(dir, name);
        if (fs.statSync(file).isDirectory()) {
            if (!SKIP_DIRS.has(name)) walk(file);
        } else if (/\.tsx?$/.test(name)) {
            const code = fs.readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
            const rel = path.relative(root, file);
            // JSX-Text zwischen Tags
            for (const m of code.matchAll(/>([^<>{}]*[A-Za-zÄÖÜäöü][^<>{}]*)</g)) add(m[1], rel);
            // Attribute und Eigenschaften mit Texten
            for (const m of code.matchAll(/\b(?:title|placeholder|aria-label|alt|label|description|hint|summary|message|emptyText|searchPlaceholder|tooltip)\s*[=:]\s*(?:\{\s*)?(['"`])((?:(?!\1)[^\\]|\\.)*?)\1/g)) add(m[2], rel);
            // Meldungen
            for (const m of code.matchAll(/toast\.(?:success|error|info|warning)\(\s*(['"`])((?:(?!\1)[^\\]|\\.)*?)\1/g)) add(m[2].replace(/\$\{[^}]*\}/g, '{x}'), rel);
            // Texte in Anführungszeichen mit Umlaut oder Leerzeichen und Grossbuchstaben am Anfang
            for (const m of code.matchAll(/(['"])([A-ZÄÖÜ][^'"\n]*\s[^'"\n]*)\1/g)) if (!/[;={}]/.test(m[2])) add(m[2], rel);
        }
    }
};
walk(jsDir);

// config/intranet.php: Titel, Beschreibungen, Funktionen, Bezeichnungen, Auswahlwerte
const config = fs.readFileSync(path.join(root, 'config/intranet.php'), 'utf8').replace(/^\s*(\/\/|\*|\|).*$/gm, '');
for (const m of config.matchAll(/'(?:title|label|description)'\s*=>\s*'((?:[^'\\]|\\.)*)'/g)) add(m[1].replace(/\\'/g, "'"), 'config/intranet.php');
for (const m of config.matchAll(/'features'\s*=>\s*\[([^\]]*)\]/g)) for (const f of m[1].matchAll(/'((?:[^'\\]|\\.)*)'/g)) add(f[1], 'config/intranet.php');
for (const block of ['roles', 'schools', 'audiences', 'announcement_categories', 'event_categories', 'document_categories']) {
    const m = new RegExp(`'${block}' => \\[([^\\]]*)\\]`).exec(config);
    if (m) for (const v of m[1].matchAll(/=>\s*'([^']*)'/g)) add(v[1], 'config/intranet.php');
}

const catalog = {};
for (const name of fs.existsSync(catalogDir) ? fs.readdirSync(catalogDir) : []) {
    if (name.endsWith('.json') && name !== 'ignore.json') Object.assign(catalog, JSON.parse(fs.readFileSync(path.join(catalogDir, name), 'utf8')));
}
const ignore = fs.existsSync(path.join(catalogDir, 'ignore.json')) ? JSON.parse(fs.readFileSync(path.join(catalogDir, 'ignore.json'), 'utf8')) : [];
const missing = [...texts.entries()].filter(([t]) => !(t in catalog) && !ignore.includes(t));

const all = process.argv.includes('--all');
const list = all ? [...texts.entries()] : missing;
if (process.argv.includes('--json')) {
    console.log(JSON.stringify(Object.fromEntries(list), null, 1));
} else {
    console.log(`${texts.size} Texte gefunden, ${texts.size - missing.length} übersetzt, ${missing.length} fehlen.`);
    for (const [t, s] of missing.slice(0, 400)) console.log(`- ${t}    (${s})`);
}
// In CI (--check): fehlende Übersetzungen lassen den Lauf scheitern
if (process.argv.includes('--check') && missing.length > 0) process.exit(1);
