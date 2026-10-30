// i18n: nicht übersetzen (Inhalte von PDF, Kärtchen und Skript bleiben deutsch)
/**
 * PDF pro Schüler:in ohne fremde Bibliothek (aus zugangsdaten-generator.html übernommen).
 * Schreibt ein gültiges PDF direkt, mit Helvetica (WinAnsi) und dem Schullogo als Bild.
 */
import { LOGO_ASPECT, LOGO_SVG } from './assets';
import { groupByClass } from './generator';
import type { Student } from './generator';

type RGB = [number, number, number];

const colorOf = (rgb: RGB) =>
    `${(rgb[0] / 255).toFixed(3)} ${(rgb[1] / 255).toFixed(3)} ${(rgb[2] / 255).toFixed(3)}`;

class SimplePDF {
    mmToPt = 72 / 25.4;
    pageWmm = 210;
    pageHmm = 297;
    pageWpt = this.pageWmm * this.mmToPt;
    pageHpt = this.pageHmm * this.mmToPt;
    pages: string[] = [];
    current = '';
    logo: { bytes: Uint8Array; width: number; height: number } | null = null;

    addPage() {
        if (this.current.length) {
            this.pages.push(this.current);
        }
        this.current = '';
    }

    finish() {
        if (this.current.length) {
            this.pages.push(this.current);
        }
        this.current = '';
    }

    mm(v: number) {
        return v * this.mmToPt;
    }

    // Abstand von oben (mm) → PDF-Koordinate von unten
    y(v: number) {
        return this.pageHpt - this.mm(v);
    }

    esc(str: string): string {
        let out = '';
        const map: Record<string, number> = {
            '’': 39, '‘': 39, '“': 34, '”': 34, '–': 45, '—': 45, '…': 46,
        };
        for (const ch of String(str)) {
            let code = ch.codePointAt(0) ?? 63;
            if (code > 255) {
                code = map[ch] !== undefined ? map[ch] : 63; // sonst «?»
            }
            const c = String.fromCharCode(code);
            out += c === '(' || c === ')' || c === '\\' ? '\\' + c : c;
        }

        return out;
    }

    fillRectMM(xmm: number, yTopMm: number, wmm: number, hmm: number, rgb: RGB) {
        const x = this.mm(xmm), yTop = this.y(yTopMm), h = this.mm(hmm), w = this.mm(wmm);
        this.current += `${colorOf(rgb)} rg\n`;
        this.current += `${x.toFixed(2)} ${(yTop - h).toFixed(2)} ${w.toFixed(2)} ${h.toFixed(2)} re f\n`;
    }

    lineMM(x1mm: number, y1mm: number, x2mm: number, y2mm: number, rgb: RGB, widthMm: number) {
        const x1 = this.mm(x1mm), y1 = this.y(y1mm), x2 = this.mm(x2mm), y2 = this.y(y2mm);
        this.current += `${colorOf(rgb)} RG\n`;
        this.current += `${this.mm(widthMm).toFixed(2)} w\n`;
        this.current += `${x1.toFixed(2)} ${y1.toFixed(2)} m ${x2.toFixed(2)} ${y2.toFixed(2)} l S\n`;
    }

    dashedLineMM(x1mm: number, y1mm: number, x2mm: number, y2mm: number, rgb: RGB, widthMm: number, dashMm = 2.2) {
        const x1 = this.mm(x1mm), y1 = this.y(y1mm), x2 = this.mm(x2mm), y2 = this.y(y2mm);
        const dash = this.mm(dashMm).toFixed(2);
        this.current += `${colorOf(rgb)} RG\n`;
        this.current += `${this.mm(widthMm).toFixed(2)} w\n`;
        this.current += `[${dash} ${dash}] 0 d\n`;
        this.current += `${x1.toFixed(2)} ${y1.toFixed(2)} m ${x2.toFixed(2)} ${y2.toFixed(2)} l S\n`;
        this.current += `[] 0 d\n`;
    }

    rectStrokeMM(xmm: number, yTopMm: number, wmm: number, hmm: number, rgb: RGB, widthMm: number) {
        const x = this.mm(xmm), yTop = this.y(yTopMm), h = this.mm(hmm), w = this.mm(wmm);
        this.current += `${colorOf(rgb)} RG\n`;
        this.current += `${this.mm(widthMm).toFixed(2)} w\n`;
        this.current += `${x.toFixed(2)} ${(yTop - h).toFixed(2)} ${w.toFixed(2)} ${h.toFixed(2)} re S\n`;
    }

    textMM(xmm: number, ymm: number, str: string, { bold = false, size = 10, rgb = [0, 0, 0] as RGB } = {}) {
        const x = this.mm(xmm), y = this.y(ymm);
        const font = bold ? 'F2' : 'F1';
        this.current += `BT /${font} ${size} Tf ${colorOf(rgb)} rg ${x.toFixed(2)} ${y.toFixed(2)} Td (${this.esc(str)}) Tj ET\n`;
    }

    setLogo(bytes: Uint8Array, width: number, height: number) {
        this.logo = { bytes, width, height };
    }

    placeImageMM(xmm: number, yTopMm: number, wmm: number, hmm: number) {
        if (!this.logo) {
            return;
        }
        const x = this.mm(xmm), yTop = this.y(yTopMm), w = this.mm(wmm), h = this.mm(hmm);
        this.current += `q\n${w.toFixed(2)} 0 0 ${h.toFixed(2)} ${x.toFixed(2)} ${(yTop - h).toFixed(2)} cm\n/Im1 Do\nQ\n`;
    }

    bytesToBinaryString(bytes: Uint8Array): string {
        let result = '';
        const chunk = 0x8000;
        for (let i = 0; i < bytes.length; i += chunk) {
            result += String.fromCharCode(...bytes.subarray(i, i + chunk));
        }

        return result;
    }

    build(): string {
        this.finish();
        const n = this.pages.length;
        const fontRegularId = 3 + n * 2;
        const fontBoldId = fontRegularId + 1;
        const imageId = this.logo ? fontBoldId + 1 : null;
        const xobjectDict = this.logo ? ` /XObject << /Im1 ${imageId} 0 R >>` : '';

        const objects: string[] = [];
        objects.push(`1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n`);
        const kids: string[] = [];
        for (let i = 0; i < n; i++) {
            kids.push(`${3 + i * 2} 0 R`);
        }
        objects.push(`2 0 obj\n<< /Type /Pages /Kids [${kids.join(' ')}] /Count ${n} >>\nendobj\n`);

        for (let i = 0; i < n; i++) {
            const pageId = 3 + i * 2;
            const contentId = pageId + 1;
            objects.push(`${pageId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${this.pageWpt.toFixed(2)} ${this.pageHpt.toFixed(2)}] /Resources << /Font << /F1 ${fontRegularId} 0 R /F2 ${fontBoldId} 0 R >>${xobjectDict} >> /Contents ${contentId} 0 R >>\nendobj\n`);
            const stream = this.pages[i];
            objects.push(`${contentId} 0 obj\n<< /Length ${stream.length} >>\nstream\n${stream}endstream\nendobj\n`);
        }

        objects.push(`${fontRegularId} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n`);
        objects.push(`${fontBoldId} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n`);

        if (this.logo) {
            const imgData = this.bytesToBinaryString(this.logo.bytes);
            objects.push(`${imageId} 0 obj\n<< /Type /XObject /Subtype /Image /Width ${this.logo.width} /Height ${this.logo.height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length ${imgData.length} >>\nstream\n${imgData}\nendstream\nendobj\n`);
        }

        let out = '%PDF-1.4\n';
        const offsets: number[] = [];
        objects.forEach((obj) => {
            offsets.push(out.length);
            out += obj;
        });
        const xrefStart = out.length;
        out += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
        offsets.forEach((off) => {
            out += `${String(off).padStart(10, '0')} 00000 n \n`;
        });
        out += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefStart}\n%%EOF`;

        return out;
    }
}

function rasterizeLogo(targetWidthPx: number): Promise<{ rgb: Uint8Array; width: number; height: number }> {
    return new Promise((resolve, reject) => {
        const targetHeightPx = Math.round(targetWidthPx / LOGO_ASPECT);
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = targetWidthPx;
            canvas.height = targetHeightPx;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                reject(new Error('Kein Canvas verfügbar.'));

                return;
            }
            ctx.drawImage(img, 0, 0, targetWidthPx, targetHeightPx);
            let imageData: ImageData;
            try {
                imageData = ctx.getImageData(0, 0, targetWidthPx, targetHeightPx);
            } catch (e) {
                reject(e instanceof Error ? e : new Error(String(e)));

                return;
            }
            const { data, width, height } = imageData;
            const rgb = new Uint8Array(width * height * 3);
            for (let i = 0, j = 0; i < data.length; i += 4, j += 3) {
                rgb[j] = data[i];
                rgb[j + 1] = data[i + 1];
                rgb[j + 2] = data[i + 2];
            }
            resolve({ rgb, width, height });
        };
        img.onerror = () => reject(new Error('SVG-Logo konnte vom Browser nicht decodiert werden.'));
        img.src = 'data:image/svg+xml;base64,' + btoa(LOGO_SVG);
    });
}

const BRAND: Record<'ink' | 'accent' | 'slate' | 'paper' | 'good' | 'lineC', RGB> = {
    ink: [31, 29, 31], // #1F1D1F – Schwarz des Logos
    accent: [5, 159, 235], // #059FEB – Blau des Logos
    slate: [90, 101, 112],
    paper: [253, 253, 253],
    good: [27, 138, 79], // Grün für den PIN
    lineC: [225, 229, 233],
};

function drawPageHeader(pdf: SimplePDF, subtitle?: string) {
    const marginX = 20;
    pdf.fillRectMM(0, 0, pdf.pageWmm, pdf.pageHmm, BRAND.paper);
    const logoWmm = 55;
    const logoHmm = logoWmm / LOGO_ASPECT;
    pdf.placeImageMM(marginX, 14, logoWmm, logoHmm);
    pdf.textMM(marginX + logoWmm + 10, 20, 'Meine Zugangsdaten', { bold: true, size: 16, rgb: BRAND.ink });
    pdf.textMM(marginX + logoWmm + 10, 27, subtitle || 'iPad & Microsoft 365 Login', { size: 9.5, rgb: BRAND.slate });
    pdf.lineMM(marginX, 34, pdf.pageWmm - marginX, 34, BRAND.accent, 0.8);
}

function drawClassDividerPage(pdf: SimplePDF, klasse: string, lehrperson: string, students: Student[]) {
    const marginX = 20;
    const { ink, accent, slate, lineC } = BRAND;

    drawPageHeader(pdf, 'Übersicht Klasse');

    let y = 50;
    pdf.textMM(marginX, y, `Klasse ${klasse}`, { bold: true, size: 20, rgb: ink });
    y += 10;
    pdf.textMM(marginX, y, `Lehrperson: ${lehrperson || '—'}`, { size: 11, rgb: slate });
    y += 9;
    pdf.textMM(marginX, y, `Anzahl Schüler:innen: ${students.length}`, { bold: true, size: 14, rgb: ink });
    y += 8;
    pdf.lineMM(marginX, y, pdf.pageWmm - marginX, y, accent, 0.8);
    y += 10;

    const numColW = 10;
    const nameColW = 65;
    const emailColW = 60;
    const invColX = marginX + numColW + nameColW + emailColW + 5;
    const invColW = pdf.pageWmm - marginX - invColX;

    const drawTableHeader = () => {
        pdf.textMM(marginX, y, '#', { bold: true, size: 8.5, rgb: accent });
        pdf.textMM(marginX + numColW, y, 'NAME', { bold: true, size: 8.5, rgb: accent });
        pdf.textMM(marginX + numColW + nameColW, y, 'E-MAIL-ADRESSE', { bold: true, size: 8.5, rgb: accent });
        pdf.textMM(invColX, y, 'INV.-NR.', { bold: true, size: 8.5, rgb: accent });
        y += 3;
        pdf.lineMM(marginX, y, pdf.pageWmm - marginX, y, lineC, 0.3);
        y += 6;
    };

    drawTableHeader();

    const bottomLimit = pdf.pageHmm - 20;
    const rowH = 9;
    const boxH = 6;

    students.forEach((s, i) => {
        if (y > bottomLimit) {
            pdf.addPage();
            drawPageHeader(pdf, `Übersicht Klasse ${klasse} (Forts.)`);
            y = 50;
            drawTableHeader();
        }
        const name = `${s.vorname} ${s.nachname}`.trim() || '—';
        pdf.textMM(marginX, y, `${i + 1}.`, { size: 9.5, rgb: slate });
        pdf.textMM(marginX + numColW, y, name, { size: 10, rgb: ink });
        pdf.textMM(marginX + numColW + nameColW, y, s.benutzer || '—', { size: 8.5, rgb: slate });
        pdf.rectStrokeMM(invColX, y - 4.5, invColW, boxH, lineC, 0.3);
        y += rowH;
    });
}

function drawStudentPage(pdf: SimplePDF, d: Student, ort: string, dateStr: string, idx: number, total: number) {
    const marginX = 20;
    const { ink, accent, slate, good, lineC } = BRAND;

    drawPageHeader(pdf);

    if (idx && total) {
        const label = `Nr. ${idx} / ${total}`;
        const approxWidth = label.length * 2.6; // grobe Breite für Rechtsbündigkeit
        pdf.textMM(pdf.pageWmm - marginX - approxWidth, 20, label, { bold: true, size: 11, rgb: accent });
    }

    let y = 46;

    const sectionTitle = (title: string) => {
        pdf.textMM(marginX, y, title.toUpperCase(), { bold: true, size: 9.5, rgb: accent });
        pdf.lineMM(marginX, y + 2.2, pdf.pageWmm - marginX, y + 2.2, lineC, 0.3);
        y += 9;
    };

    const fieldRow = (label: string, value: string, opts: { bold?: boolean; size?: number; color?: RGB } = {}) => {
        pdf.textMM(marginX, y, label, { size: 9.5, rgb: slate });
        const text = value && String(value).length ? String(value) : '—';
        pdf.textMM(marginX, y + 6.5, text, { bold: !!opts.bold, size: opts.size || 13, rgb: opts.color || ink });
        pdf.lineMM(marginX, y + 9.5, pdf.pageWmm - marginX, y + 9.5, lineC, 0.2);
        y += 16;
    };

    sectionTitle('Klasse und Lehrperson');
    fieldRow('Klasse', d.klasse);
    fieldRow('Lehrperson', d.lehrperson);

    y += 3;
    sectionTitle('Schüler:in und Gerät');
    fieldRow('Vorname', d.vorname);
    fieldRow('Nachname', d.nachname);
    fieldRow('Deine iPad-Nummer', d.inventar);

    y += 3;
    sectionTitle('Anmeldung Microsoft 365');
    fieldRow('Deine Mail-Adresse', d.benutzer);
    fieldRow('Persönliches Passwort', d.pw, { bold: true, size: 15 });
    fieldRow('Persönlicher Entsperrcode', d.pin, { bold: true, size: 15, color: good });

    pdf.textMM(marginX, pdf.pageHmm - 70, `${ort}, ${dateStr}`, { bold: true, size: 9.5, rgb: ink });
    pdf.textMM(marginX, pdf.pageHmm - 60, 'Bitte gut aufbewahren und nicht an Dritte weitergeben.', { size: 7.5, rgb: slate });
    pdf.textMM(marginX, pdf.pageHmm - 56, 'Zugangsdaten nach der erfolgreichen Erstanmeldung der Lehrperson übergeben.', { size: 7.5, rgb: slate });
    pdf.textMM(marginX, pdf.pageHmm - 52, 'Wer Passwort und PIN ändert, ist anschliessend selbst dafür verantwortlich.', { size: 7.5, rgb: slate });

    // Abschnitt zum Ausschneiden
    const cutW = pdf.pageWmm - marginX * 2;
    const cutLineY = pdf.pageHmm - 44;
    pdf.dashedLineMM(marginX, cutLineY, pdf.pageWmm - marginX, cutLineY, lineC, 0.4);
    pdf.textMM(pdf.pageWmm / 2 - 24, cutLineY - 3, '- - -  zum Ausschneiden  - - -', { size: 7, rgb: slate });

    const boxTop = cutLineY + 5;
    const boxH = 30;
    pdf.rectStrokeMM(marginX, boxTop, cutW, boxH, lineC, 0.4);

    const padX = marginX + 6;
    pdf.textMM(padX, boxTop + 7, `${d.vorname} ${d.nachname}`, { bold: true, size: 12, rgb: ink });
    pdf.textMM(padX, boxTop + 13, 'E-Mail Adresse / Benutzer', { size: 7.5, rgb: slate });
    pdf.textMM(padX, boxTop + 18.5, d.benutzer || '—', { bold: true, size: 11, rgb: ink });

    const colWidth = cutW / 2;
    pdf.textMM(padX, boxTop + 27, 'Passwort:', { size: 8, rgb: slate });
    pdf.textMM(padX + 22, boxTop + 27, d.pw || '—', { bold: true, size: 11, rgb: ink });
    pdf.textMM(padX + colWidth, boxTop + 27, 'Entsperrcode:', { size: 8, rgb: slate });
    pdf.textMM(padX + colWidth + 27, boxTop + 27, d.pin || '—', { bold: true, size: 11, rgb: good });
}

/** PDF mit Klassen-Übersicht und einer Seite je Schüler:in, als Bytes. */
export async function buildStudentPdf(data: Student[], ortInput: string): Promise<Uint8Array> {
    let logo: { rgb: Uint8Array; width: number; height: number } | null = null;
    try {
        logo = await rasterizeLogo(720); // ~300 dpi bei 55–60 mm Breite
    } catch (e) {
        console.error('Logo konnte nicht gerendert werden:', e);
    }

    const ort = ortInput.trim() || 'Bülach';
    const dateStr = new Date().toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' });

    const pdf = new SimplePDF();
    if (logo) {
        pdf.setLogo(logo.rgb, logo.width, logo.height);
    }

    let isFirstPage = true;
    groupByClass(data).forEach((students, klasse) => {
        const lehrperson = Array.from(
            new Set(students.map((s) => s.lehrperson).filter((v) => v && v.length)),
        ).join(', ');

        if (!isFirstPage) {
            pdf.addPage();
        }
        isFirstPage = false;
        drawClassDividerPage(pdf, klasse, lehrperson, students);

        students.forEach((d, i) => {
            pdf.addPage();
            drawStudentPage(pdf, d, ort, dateStr, i + 1, students.length);
        });
    });

    const pdfString = pdf.build();
    const bytes = new Uint8Array(pdfString.length);
    for (let i = 0; i < pdfString.length; i++) {
        bytes[i] = pdfString.charCodeAt(i) & 0xff;
    }

    return bytes;
}
