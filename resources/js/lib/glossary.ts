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
    s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

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
    own && off && own !== off ? `<br><small>offiziell: ${esc(off)}</small>` : '';

/** Kompakt (A4 hoch): je Eintrag eine Zeile, Kurzbeschreibung mit «…» abgeschnitten. */
function compactTable(list: GlossaryRow[]): string {
    return `<table class="compact"><colgroup><col style="width:12%"><col style="width:44%"><col style="width:32%"><col style="width:12%"></colgroup><thead><tr><th>Abkürzung</th><th>Bezeichnung</th><th>Kurzbeschreibung</th><th>Herkunft</th></tr></thead><tbody>${list
        .map(
            (r) =>
                `<tr><td><b>${esc(abbreviation(r))}</b></td><td title="${esc(designation(r))}">${esc(designation(r))}</td><td title="${esc(r.kurzbeschreibung)}">${esc(r.kurzbeschreibung)}</td><td>${tags(r.herkunft)}</td></tr>`,
        )
        .join('')}</tbody></table>`;
}

/** Voll (A4 quer): alle sinnvollen Felder, nichts abgeschnitten. */
function fullTable(list: GlossaryRow[], status: Record<string, string>): string {
    const validity = (r: GlossaryRow) =>
        [r.gueltig_ab && `ab ${esc(r.gueltig_ab)}`, r.gueltig_bis && `bis ${esc(r.gueltig_bis)}`]
            .filter(Boolean)
            .join('<br>');

    return `<table class="full"><colgroup><col style="width:9%"><col style="width:20%"><col style="width:16%"><col style="width:18%"><col style="width:8%"><col style="width:9%"><col style="width:7%"><col style="width:13%"></colgroup><thead><tr><th>Abkürzung</th><th>Bezeichnung</th><th>Kurzbeschreibung</th><th>Beschreibung</th><th>Herkunft</th><th>Genutzt in</th><th>Status</th><th>Notiz</th></tr></thead><tbody>${list
        .map((r) => {
            const aliases = [r.aliase_offiziell, r.alias_eigen].filter(Boolean).join('; ');

            return `<tr><td><b>${esc(abbreviation(r))}</b>${official(r.abk_eigen, r.abk_offiziell)}${aliases ? `<br><small>Alias: ${esc(aliases)}</small>` : ''}</td><td>${esc(designation(r))}${official(r.bez_eigen, r.bez_offiziell)}${r.url ? `<br><small class="url">${esc(r.url)}</small>` : ''}</td><td>${esc(r.kurzbeschreibung)}</td><td>${esc(r.beschreibung)}</td><td>${tags(r.herkunft)}</td><td>${tags(r.genutzt_in)}</td><td>${esc(status[r.status] ?? r.status)}${validity(r) ? `<br><small>${validity(r)}</small>` : ''}</td><td class="pre">${esc(r.notiz)}</td></tr>`;
        })
        .join('')}</tbody></table>`;
}

/**
 * Druckansicht nach Kategorie (im Druckdialog «Als PDF speichern»):
 * «kompakt» A4 hoch mit einer Zeile je Eintrag, «voll» A4 quer mit allen Feldern.
 */
export function printGlossary(
    rows: GlossaryRow[],
    mode: 'kompakt' | 'voll' = 'kompakt',
    status: Record<string, string> = {},
) {
    const body = byCategory(rows)
        .map(
            ([cat, list]) =>
                `<section><h2>${esc(cat)} <small>(${list.length})</small></h2>${mode === 'kompakt' ? compactTable(list) : fullTable(list, status)}</section>`,
        )
        .join('');
    const page = mode === 'kompakt' ? 'portrait' : 'landscape';
    const html = `<!doctype html><html lang="de-CH"><head><meta charset="utf-8"><title>Glossar${mode === 'voll' ? ' (voll)' : ''}</title>
<style>@page{size:A4 ${page};margin:12mm}*{box-sizing:border-box}body{font:9pt/1.35 system-ui,sans-serif;margin:24px;color:#111}h1{font-size:15pt;margin:0 0 2px}h2{font-size:11pt;margin:14px 0 4px;break-after:avoid}h2 small{font-weight:normal;color:#666}
table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border-bottom:1px solid #ccc;padding:2px 5px;text-align:left;vertical-align:top}th{background:#f2f4f6;font-size:8pt}
.compact td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:8.5pt}
.full td{font-size:8pt;overflow-wrap:anywhere}.full .pre{white-space:pre-wrap}
small{color:#555}.url{color:#1d4f91}.tag{display:inline-block;border:1px solid #bbb;border-radius:3px;padding:0 4px;margin:0 2px 1px 0;font-size:7.5pt;line-height:1.4;background:#f6f7f8}
p.meta{margin:0 0 6px;color:#555}@media print{body{margin:0}tr{break-inside:avoid}th{-webkit-print-color-adjust:exact;print-color-adjust:exact}}</style></head><body>
<h1>Glossar</h1><p class="meta">${rows.length} Einträge · ${mode === 'kompakt' ? 'kompakt' : 'alle Felder'} · Stand ${new Date().toLocaleDateString('de-CH')}</p>${body}
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
    const parts = [
        `# Glossar`,
        '',
        `${rows.length} Einträge · Stand ${new Date().toLocaleDateString('de-CH')}`,
        '',
    ];
    for (const [cat, list] of byCategory(rows)) {
        parts.push(
            `## ${cat}`,
            '',
            '| Abkürzung | Bezeichnung | Kurzbeschreibung | Herkunft |',
            '| --- | --- | --- | --- |',
        );
        for (const r of list) {
            const name = r.url
                ? `[${mdCell(designation(r))}](${r.url})`
                : mdCell(designation(r));
            parts.push(
                `| **${mdCell(abbreviation(r))}** | ${name} | ${mdCell(r.kurzbeschreibung)} | ${mdCell(r.herkunft)} |`,
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
