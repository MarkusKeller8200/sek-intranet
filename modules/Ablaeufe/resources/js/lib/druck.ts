// i18n: nicht übersetzen – Druckansicht für die Ablage (Deutsch)
import type { Ablauf, Dokument, Gruppe } from './types';
import { fristText } from './types';

const esc = (s: string) =>
    s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

/** Ablauf als druckbare Seite öffnen (im Druckdialog «Als PDF speichern»). */
export function druckeAblauf(
    ablauf: Ablauf,
    gruppen: Gruppe[],
    dokumente: Dokument[],
    bereich: string,
) {
    const gruppe = (id: string) => gruppen.find((g) => g.id === id)?.name ?? id;
    const dokument = (id: string) =>
        dokumente.find((d) => d.id === id)?.title ?? id;
    const phasen = ablauf.phasen
        .map((p) => {
            const zeilen = ablauf.schritte
                .filter((s) => s.phase === p.key)
                .map(
                    (s, i) => `<tr><td class="r">${i + 1}</td><td><span class="box"></span> ${esc(s.text)}${
                        s.gilt_fuer.length
                            ? `<br><small>gilt für: ${esc(s.gilt_fuer.map(gruppe).join(', '))}</small>`
                            : ''
                    }</td><td>${esc(s.verantwortung)}</td><td>${esc(s.kommunikation)}</td><td>${esc(
                        s.dokumente.map(dokument).join(', '),
                    )}</td><td>${esc([fristText(s.frist_tage), s.bemerkung].filter(Boolean).join(' · '))}</td></tr>`,
                )
                .join('');

            return `<h2>${esc(p.titel)}</h2><table><thead><tr><th class="r">Nr.</th><th>Schritt</th><th>Verantwortung</th><th>Kommunikation an</th><th>Dokumente</th><th>Bemerkungen</th></tr></thead><tbody>${
                zeilen || '<tr><td colspan="6">Keine Schritte</td></tr>'
            }</tbody></table>`;
        })
        .join('');
    const html = `<!doctype html><html lang="de-CH"><head><meta charset="utf-8">
<title>${esc(ablauf.titel)}</title>
<style>
body{font:10pt/1.35 system-ui,sans-serif;margin:24px;color:#111}
h1{font-size:15pt;margin:0 0 4px}h2{font-size:12pt;margin:18px 0 4px}p{margin:0 0 8px;color:#444}
table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #ccc;padding:4px 6px;text-align:left;vertical-align:top;font-size:9pt}
th{background:#f2f4f6}.r{text-align:right;width:2.5em}small{color:#555}
.box{display:inline-block;width:9px;height:9px;border:1px solid #333;margin-right:3px}
@media print{body{margin:0}tr{break-inside:avoid}}
</style></head><body>
<h1>${esc(ablauf.titel)}</h1>
<p>${esc(bereich)}${ablauf.verantwortlich ? ` · Verantwortlich: ${esc(ablauf.verantwortlich)}` : ''}${
        ablauf.gueltig_ab
            ? ` · gültig ab ${esc(new Date(ablauf.gueltig_ab).toLocaleDateString('de-CH'))}`
            : ''
    }</p>
${ablauf.beschreibung ? `<p>${esc(ablauf.beschreibung)}</p>` : ''}
${phasen}
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
