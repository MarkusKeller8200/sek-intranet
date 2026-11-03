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
    s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** Nach Kategorie gliedern (Einträge mit mehreren Kategorien erscheinen mehrfach). */
function byCategory(rows: GlossaryRow[]): [string, GlossaryRow[]][] {
    const groups = new Map<string, GlossaryRow[]>();
    for (const r of rows) {
        const cats = r.kategorien.split(';').map((c) => c.trim()).filter(Boolean);
        for (const c of cats.length ? cats : ['(ohne Kategorie)']) {
            groups.set(c, [...(groups.get(c) ?? []), r]);
        }
    }

    return [...groups.entries()]
        .sort(([a], [b]) => a.localeCompare(b, 'de-CH'))
        .map(([c, list]) => [c, [...list].sort((a, b) => abbreviation(a).localeCompare(abbreviation(b), 'de-CH'))]);
}

/** Druckansicht (im Druckdialog «Als PDF speichern»). */
export function printGlossary(rows: GlossaryRow[]) {
    const body = byCategory(rows)
        .map(
            ([cat, list]) => `<h2>${esc(cat)}</h2><table><thead><tr><th>Abkürzung</th><th>Bezeichnung</th><th>Kurzbeschreibung</th><th>Herkunft</th></tr></thead><tbody>${list
                .map(
                    (r) => `<tr><td><b>${esc(abbreviation(r))}</b>${r.abk_eigen && r.abk_offiziell && r.abk_eigen !== r.abk_offiziell ? `<br><small>offiziell: ${esc(r.abk_offiziell)}</small>` : ''}</td><td>${esc(designation(r))}${r.bez_eigen && r.bez_offiziell && r.bez_eigen !== r.bez_offiziell ? `<br><small>offiziell: ${esc(r.bez_offiziell)}</small>` : ''}</td><td>${esc(r.kurzbeschreibung)}</td><td>${esc(r.herkunft)}</td></tr>`,
                )
                .join('')}</tbody></table>`,
        )
        .join('');
    const html = `<!doctype html><html lang="de-CH"><head><meta charset="utf-8"><title>Glossar</title>
<style>body{font:10pt/1.35 system-ui,sans-serif;margin:24px;color:#111}h1{font-size:15pt;margin:0 0 4px}h2{font-size:12pt;margin:18px 0 4px}
table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #ccc;padding:3px 6px;text-align:left;vertical-align:top;font-size:9pt}th{background:#f2f4f6}
td:first-child{width:7em}small{color:#555}@media print{body{margin:0}tr{break-inside:avoid}}</style></head><body>
<h1>Glossar</h1><p>${rows.length} Einträge · Stand ${new Date().toLocaleDateString('de-CH')}</p>${body}
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
export function downloadMarkdown(rows: GlossaryRow[]) {
    const parts = [`# Glossar`, '', `${rows.length} Einträge · Stand ${new Date().toLocaleDateString('de-CH')}`, ''];
    for (const [cat, list] of byCategory(rows)) {
        parts.push(`## ${cat}`, '', '| Abkürzung | Bezeichnung | Kurzbeschreibung | Herkunft |', '| --- | --- | --- | --- |');
        for (const r of list) {
            const name = r.url ? `[${mdCell(designation(r))}](${r.url})` : mdCell(designation(r));
            parts.push(`| **${mdCell(abbreviation(r))}** | ${name} | ${mdCell(r.kurzbeschreibung)} | ${mdCell(r.herkunft)} |`);
        }
        parts.push('');
    }
    const blob = new Blob([parts.join('\n')], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `glossar-${new Date().toISOString().slice(0, 10)}.md`;
    a.click();
    URL.revokeObjectURL(url);
}
