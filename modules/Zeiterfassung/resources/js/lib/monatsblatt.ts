// i18n: nicht übersetzen – Monatsblatt für die Ablage (Deutsch)
import type { Eintrag, Kategorien, Status, Summen } from './types';
import { kategorieName, monatName } from './types';

const esc = (s: string) =>
    s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

const STATUS: Record<string, string> = {
    offen: 'Offen',
    eingereicht: 'Eingereicht',
    visiert: 'Visiert',
    zurueck: 'Zurück an Person',
};

/** Monatsblatt als druckbare Seite öffnen (im Druckdialog «Als PDF speichern»). */
export function druckeMonatsblatt(opts: {
    person: string;
    monat: string;
    eintraege: Eintrag[];
    summen: Summen;
    status: Status;
    kategorien: Kategorien;
    visiertVon?: string | null;
}) {
    const { person, monat, eintraege, summen, status, kategorien } = opts;
    const zeilen = eintraege
        .map(
            (e) => `<tr><td>${esc(new Date(e.datum).toLocaleDateString('de-CH'))}</td>
<td>${e.art === 'abwesenheit' ? 'Abwesenheit' : 'Arbeit'}</td>
<td>${esc(kategorieName(kategorien, e.kategorie))}</td>
<td>${esc(e.beginn ?? '')}${e.ende ? `–${esc(e.ende)}` : ''}</td>
<td class="r">${e.pause_min ?? ''}</td>
<td class="r">${e.stunden.toFixed(2)}</td>
<td>${esc(e.projekt ?? '')}</td></tr>`,
        )
        .join('');
    const kat = Object.entries(summen.kategorien)
        .map(
            ([k, h]) =>
                `<tr><td>${esc(kategorieName(kategorien, k))}</td><td class="r">${h.toFixed(2)}</td></tr>`,
        )
        .join('');
    const html = `<!doctype html><html lang="de-CH"><head><meta charset="utf-8">
<title>Zeiterfassung ${esc(monat)} – ${esc(person)}</title>
<style>
body{font:11pt/1.4 system-ui,sans-serif;margin:24px;color:#111}
h1{font-size:16pt;margin:0 0 4px}p{margin:0 0 12px;color:#444}
table{border-collapse:collapse;width:100%;margin:8px 0 16px}
th,td{border-bottom:1px solid #ccc;padding:4px 6px;text-align:left;font-size:10pt}
th{background:#f2f4f6}.r{text-align:right}
.sig{display:flex;gap:48px;margin-top:40px}.sig div{flex:1;border-top:1px solid #333;padding-top:4px;font-size:9pt}
@media print{body{margin:0}}
</style></head><body>
<h1>Zeiterfassung ${esc(monatName(monat))}</h1>
<p>${esc(person)} · Status: ${esc(STATUS[status.status] ?? status.status)}${opts.visiertVon ? ` (${esc(opts.visiertVon)})` : ''}</p>
<table><thead><tr><th>Datum</th><th>Art</th><th>Kategorie</th><th>Zeit</th><th class="r">Pause</th><th class="r">Stunden</th><th>Projekt</th></tr></thead>
<tbody>${zeilen || '<tr><td colspan="7">Keine Einträge</td></tr>'}</tbody></table>
<table style="width:50%"><thead><tr><th>Kategorie</th><th class="r">Stunden</th></tr></thead><tbody>${kat}
<tr><th>Arbeit</th><th class="r">${summen.arbeit.toFixed(2)}</th></tr>
<tr><th>Abwesenheit</th><th class="r">${summen.abwesenheit.toFixed(2)}</th></tr>
<tr><th>Total</th><th class="r">${summen.total.toFixed(2)}</th></tr></tbody></table>
<div class="sig"><div>Datum, Unterschrift Mitarbeiter:in</div><div>Datum, Visum Schulleitung</div></div>
<script>window.onload=()=>window.print()</script>
</body></html>`;
    const w = window.open('', '_blank');
    if (!w) {
        return false;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();

    return true;
}
