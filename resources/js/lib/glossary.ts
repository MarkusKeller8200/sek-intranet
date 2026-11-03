// i18n: Export-Texte (Druck, Markdown) bleiben Deutsch
export type GlossaryRow = {
    id: string;
    kategorien: string;
    abk_offiziell: string;
    aliase_offiziell: string;
    abk_eigen: string;
    alias_eigen: string;
    bez_offiziell: string;
    bez_eigen: string;
    kurzbeschreibung: string;
    beschreibung: string;
    url: string;
    notiz: string;
    herkunft: string;
    genutzt_in: string;
    status: string;
    suchbegriffe: string;
    gueltig_ab: string;
    gueltig_bis: string;
    mehrdeutig: boolean;
    /** Anzahl verschiedener Bedeutungen der Abkürzung (gleiche Bezeichnung zählt einmal) */
    bedeutungen: number;
    anzeige: string;
};

export type GlossaryEntry = {
    id: string;
    kategorien: string[];
    abk_offiziell: string;
    aliase_offiziell: string[];
    abk_eigen: string;
    alias_eigen: string;
    bez_offiziell: string;
    bez_eigen: string;
    kurzbeschreibung: string;
    beschreibung: string;
    url: string;
    notiz: string;
    herkunft: string[];
    genutzt_in: string[];
    status: string;
    suchbegriffe: string;
    gueltig_ab: string | null;
    gueltig_bis: string | null;
    updated_at?: string;
};

export const abbreviation = (r: { abk_eigen: string; abk_offiziell: string }) =>
    r.abk_eigen.trim() || r.abk_offiziell.trim();
export const designation = (r: { bez_eigen: string; bez_offiziell: string }) =>
    r.bez_eigen.trim() || r.bez_offiziell.trim();

const esc = (s: string) =>
    s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

/** Hochgestellte Zahl bei Mehrfachbedeutung (#67). */
const sup = (r: GlossaryRow) =>
    r.bedeutungen > 1 ? `<sup>${r.bedeutungen}</sup>` : '';
const SUPERSCRIPT = '⁰¹²³⁴⁵⁶⁷⁸⁹';
const supText = (r: GlossaryRow) =>
    r.bedeutungen > 1
        ? String(r.bedeutungen).replace(/\d/g, (d) => SUPERSCRIPT[Number(d)])
        : '';

/** Nach Kategorie gliedern (Einträge mit mehreren Kategorien erscheinen mehrfach). */
function byCategory(rows: GlossaryRow[]): [string, GlossaryRow[]][] {
    const groups = new Map<string, GlossaryRow[]>();
    for (const r of rows) {
        const cats = r.kategorien
            .split(';')
            .map((c) => c.trim())
            .filter(Boolean);
        for (const c of cats.length ? cats : ['(ohne Kategorie)']) {
            groups.set(c, [...(groups.get(c) ?? []), r]);
        }
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'de-CH'))
        .map(([c, list]) => [
            c,
            [...list].sort((a, b) =>
                abbreviation(a).localeCompare(abbreviation(b), 'de-CH'),
            ),
        ]);
}

const tags = (s: string) =>
    s
        .split(';')
        .map((t) => t.trim())
        .filter(Boolean)
        .map((t) => `<span class="tag">${esc(t)}</span>`)
        .join(' ');

const official = (own: string, off: string) =>
    own && off && own !== off
        ? `<br><small>offiziell: ${esc(off)}</small>`
        : '';

export type PdfScope = 'kompakt' | 'erweitert' | 'vollstaendig';
export type PdfGroup = 'keine' | 'kategorie' | 'herkunft' | 'status';
export type PdfOptions = {
    format: 'hoch' | 'quer';
    umfang: PdfScope;
    gruppe: PdfGroup;
    umbruch: 'keiner' | 'gruppe';
};

export const PDF_DEFAULT: PdfOptions = {
    format: 'hoch',
    umfang: 'kompakt',
    gruppe: 'kategorie',
    umbruch: 'keiner',
};

/** Schlüssel → Bezeichnung */
type Labels = Record<string, string>;

const byAbbreviation = (list: GlossaryRow[]) =>
    [...list].sort((a, b) =>
        abbreviation(a).localeCompare(abbreviation(b), 'de-CH'),
    );

/** Gliedern nach Kategorie, Herkunft oder Status (Mehrfachwerte: in jeder ihrer Gruppen). */
function groupRows(
    rows: GlossaryRow[],
    by: PdfGroup,
    names: Labels,
    status: Labels,
): [string, GlossaryRow[]][] {
    if (by === 'keine') {
        return [['', byAbbreviation(rows)]];
    }
    if (by === 'kategorie') {
        return byCategory(rows).map(([c, list]) => [names[c] || c, list]);
    }
    const groups = new Map<string, GlossaryRow[]>();
    for (const r of rows) {
        const keys =
            by === 'status'
                ? [status[r.status] ?? r.status]
                : r.herkunft
                      .split(';')
                      .map((h) => h.trim())
                      .filter(Boolean);
        for (const k of keys.length ? keys : ['(ohne Angabe)']) {
            groups.set(k, [...(groups.get(k) ?? []), r]);
        }
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'de-CH'))
        .map(([k, list]) => [k, byAbbreviation(list)]);
}

type Column = {
    title: string;
    width: number;
    cell: (r: GlossaryRow) => string;
    cls?: string;
};

const aliases = (r: GlossaryRow) =>
    [r.aliase_offiziell, r.alias_eigen].filter(Boolean).join('; ');

/** Spalten je Umfang; die Spalte der Gruppierung entfällt. */
function columns(o: PdfOptions, status: Labels): Column[] {
    const abk = (full: boolean): Column => ({
        title: 'Abkürzung',
        width: full ? 9 : 12,
        cell: (r) =>
            `<b>${esc(abbreviation(r))}</b>${sup(r)}${full ? official(r.abk_eigen, r.abk_offiziell) + (aliases(r) ? `<br><small>Alias: ${esc(aliases(r))}</small>` : '') : ''}`,
    });
    const bez = (full: boolean, url = false): Column => ({
        title: 'Bezeichnung',
        width: full ? 20 : 40,
        cell: (r) =>
            `${esc(designation(r))}${full ? official(r.bez_eigen, r.bez_offiziell) : ''}${url && r.url ? `<br><small class="url">${esc(r.url)}</small>` : ''}`,
    });
    const kurz: Column = {
        title: 'Kurzbeschreibung',
        width: 26,
        cell: (r) => esc(r.kurzbeschreibung),
    };
    const kat: Column = {
        title: 'Kategorie',
        width: 11,
        cell: (r) => tags(r.kategorien),
    };
    const her: Column = {
        title: 'Herkunft',
        width: 10,
        cell: (r) => tags(r.herkunft),
    };
    const sta: Column = {
        title: 'Status',
        width: 8,
        cell: (r) => {
            const v = [
                r.gueltig_ab && `ab ${esc(r.gueltig_ab)}`,
                r.gueltig_bis && `bis ${esc(r.gueltig_bis)}`,
            ]
                .filter(Boolean)
                .join('<br>');

            return `${esc(status[r.status] ?? r.status)}${v ? `<br><small>${v}</small>` : ''}`;
        },
    };
    const without = (list: Column[]) =>
        list.filter(
            (c) =>
                !(o.gruppe === 'kategorie' && c === kat) &&
                !(o.gruppe === 'herkunft' && c === her) &&
                !(o.gruppe === 'status' && c === sta),
        );

    if (o.umfang === 'kompakt') {
        return without([abk(false), bez(false), kurz, kat, her]);
    }
    if (o.umfang === 'erweitert') {
        return without([
            abk(true),
            bez(true),
            kurz,
            {
                title: 'Beschreibung',
                width: 24,
                cell: (r) => esc(r.beschreibung),
            },
            kat,
            her,
        ]);
    }

    return without([
        abk(true),
        bez(true, true),
        kurz,
        { title: 'Beschreibung', width: 16, cell: (r) => esc(r.beschreibung) },
        kat,
        her,
        { title: 'Genutzt in', width: 9, cell: (r) => tags(r.genutzt_in) },
        sta,
        { title: 'Notiz', width: 12, cell: (r) => esc(r.notiz), cls: 'pre' },
    ]);
}

function table(list: GlossaryRow[], cols: Column[], compact: boolean): string {
    const total = cols.reduce((sum, c) => sum + c.width, 0);

    return `<table class="${compact ? 'compact' : 'full'}"><colgroup>${cols.map((c) => `<col style="width:${((c.width / total) * 100).toFixed(1)}%">`).join('')}</colgroup><thead><tr>${cols.map((c) => `<th>${c.title}</th>`).join('')}</tr></thead><tbody>${list
        .map(
            (r) =>
                `<tr>${cols.map((c) => `<td${c.cls ? ` class="${c.cls}"` : ''}>${c.cell(r)}</td>`).join('')}</tr>`,
        )
        .join('')}</tbody></table>`;
}

const UMFANG = {
    kompakt: 'kompakt',
    erweitert: 'erweitert',
    vollstaendig: 'vollständig',
} as const;
const GRUPPE = {
    keine: '',
    kategorie: 'nach Kategorie',
    herkunft: 'nach Herkunft',
    status: 'nach Status',
} as const;

/**
 * Druckansicht (im Druckdialog «Als PDF speichern») mit Format, Umfang, Gruppierung und Seitenumbruch (#70).
 * «kompakt»: eine Zeile je Eintrag, zu lange Texte mit «…»; sonst nichts abgeschnitten.
 */
export function printGlossary(
    rows: GlossaryRow[],
    options: PdfOptions = PDF_DEFAULT,
    status: Labels = {},
    names: Labels = {},
) {
    const compact = options.umfang === 'kompakt';
    const cols = columns(options, status);
    const body = groupRows(rows, options.gruppe, names, status)
        .map(
            ([title, list]) =>
                `<section>${title ? `<h2>${esc(title)} <small>(${list.length})</small></h2>` : ''}${table(list, cols, compact)}</section>`,
        )
        .join('');
    const page = options.format === 'hoch' ? 'portrait' : 'landscape';
    const meta = [
        `${rows.length} Einträge`,
        UMFANG[options.umfang],
        GRUPPE[options.gruppe],
        `Stand ${new Date().toLocaleDateString('de-CH')}`,
    ]
        .filter(Boolean)
        .join(' · ');
    const html = `<!doctype html><html lang="de-CH"><head><meta charset="utf-8"><title>Glossar (${UMFANG[options.umfang]})</title>
<style>@page{size:A4 ${page};margin:12mm}*{box-sizing:border-box}body{font:9pt/1.35 system-ui,sans-serif;margin:24px;color:#111}h1{font-size:15pt;margin:0 0 2px}h2{font-size:11pt;margin:14px 0 4px;break-after:avoid}h2 small{font-weight:normal;color:#666}
table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border-bottom:1px solid #ccc;padding:2px 5px;text-align:left;vertical-align:top}th{background:#f2f4f6;font-size:8pt}
.compact td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:8.5pt}
.full td{font-size:8pt;overflow-wrap:anywhere}.full .pre{white-space:pre-wrap}
small{color:#555}.url{color:#1d4f91}.tag{display:inline-block;border:1px solid #bbb;border-radius:3px;padding:0 4px;margin:0 2px 1px 0;font-size:7.5pt;line-height:1.4;background:#f6f7f8}
${options.umbruch === 'gruppe' ? 'section+section{break-before:page}' : ''}
p.meta{margin:0 0 6px;color:#555}@media print{body{margin:0}tr{break-inside:avoid}th{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head><body>
<h1>Glossar</h1><p class="meta">${meta}${rows.some((r) => r.bedeutungen > 1) ? ' · hochgestellte Zahl = Anzahl verschiedener Bedeutungen der Abkürzung' : ''}</p>${body}
<script>window.onload=()=>window.print()</script></body></html>`;
    const w = window.open('', '_blank');
    if (!w) {
        return false;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();

    return true;
}

const mdCell = (s: string) => s.replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');

/** Markdown-Datei herunterladen (Abschnitte je Kategorie). */
export function downloadMarkdown(rows: GlossaryRow[], names: Labels = {}) {
    const parts = [
        `# Glossar`,
        '',
        `${rows.length} Einträge · Stand ${new Date().toLocaleDateString('de-CH')}`,
        '',
    ];
    for (const [cat, list] of byCategory(rows)) {
        parts.push(
            `## ${names[cat] || cat}`,
            '',
            '| Abkürzung | Bezeichnung | Kurzbeschreibung | Herkunft |',
            '| --- | --- | --- | --- |',
        );
        for (const r of list) {
            const name = r.url
                ? `[${mdCell(designation(r))}](${r.url})`
                : mdCell(designation(r));
            parts.push(
                `| **${mdCell(abbreviation(r))}**${supText(r)} | ${name} | ${mdCell(r.kurzbeschreibung)} | ${mdCell(r.herkunft)} |`,
            );
        }
        parts.push('');
    }
    const blob = new Blob([parts.join('\n')], {
        type: 'text/markdown;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `glossar-${new Date().toISOString().slice(0, 10)}.md`;
    a.click();
    URL.revokeObjectURL(url);
}
