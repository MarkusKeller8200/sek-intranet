export type Eintrag = {
    id: string;
    datum: string;
    monat: string;
    art: 'arbeit' | 'abwesenheit';
    /** Buchungsart (#99), bei älteren Einträgen vom Server aus Art und Kategorie ergänzt */
    buchungsart?: string;
    kategorie: string;
    beginn?: string | null;
    ende?: string | null;
    pause_min?: number | null;
    stunden: number;
    projekt?: string | null;
    notiz?: string | null;
    /** Herkunft (#101): «clicktime» = aus ClickTime übernommen */
    quelle?: string | null;
};

export type Status = {
    status: 'offen' | 'eingereicht' | 'visiert' | 'zurueck';
    bemerkung?: string | null;
    eingereicht_at?: string | null;
    visiert_at?: string | null;
};

/** Elektronische Unterschrift eines Monats (#43). */
export type Unterschrift = {
    status: string | null;
    pdf: string | null;
    anbieter?: { id: string; name: string; demo: boolean } | null;
    auftrag?: {
        id: string;
        status: string;
        signees: {
            name: string;
            email: string;
            order: number;
            status: string;
        }[];
    } | null;
};

export type Summen = {
    arbeit: number;
    abwesenheit: number;
    total: number;
    kategorien: Record<string, number>;
    projekte: Record<string, number>;
    buchungsarten?: Record<string, number>;
};

export type Buchungsart = {
    name: string;
    art: 'arbeit' | 'abwesenheit';
    aktiv: boolean;
};

export type Kategorien = {
    arbeit: Record<string, string>;
    abwesenheit: Record<string, string>;
    status: Record<string, string>;
    buchungsarten?: Record<string, Buchungsart>;
};

/** «2026-11» → «November 2026» */
export function monatName(monat: string): string {
    const [y, m] = monat.split('-').map(Number);

    return new Intl.DateTimeFormat('de-CH', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(y, m - 1, 1));
}

export function verschiebeMonat(monat: string, delta: number): string {
    const [y, m] = monat.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function stunden(h: number): string {
    return `${h.toFixed(2)} h`;
}

/** Stunden als «HH:MM», z. B. 8.4 → «08:24» (negativ mit «−»). */
export function hhmm(h: number): string {
    const min = Math.round(Math.abs(h) * 60);
    const hh = String(Math.floor(min / 60)).padStart(2, '0');
    const mm = String(min % 60).padStart(2, '0');

    return h < 0 && min > 0 ? `−${hh}:${mm}` : `${hh}:${mm}`;
}

/** Herkunft eines Eintrags (#102): von Hand im Intranet oder aus einem Fremdsystem. */
export function herkunft(quelle: string | null | undefined): string {
    if (!quelle) {
        return 'Intranet';
    }

    return quelle === 'clicktime' ? 'ClickTime' : quelle;
}

/** Notizen mit Projekt (falls erfasst), z. B. «Lager · Vorbereitung». */
export function notizen(e: {
    projekt?: string | null;
    notiz?: string | null;
}): string {
    return [e.projekt, e.notiz].filter(Boolean).join(' · ');
}

const WOCHENTAGE = ['SO', 'MO', 'DI', 'MI', 'DO', 'FR', 'SA'];

/** Wochentag als Abkürzung, z. B. «MO» (Datum «JJJJ-MM-TT», ohne Zeitzone). */
export function wochentag(datum: string): string {
    const [y, m, d] = datum.split('-').map(Number);

    return WOCHENTAGE[new Date(y, m - 1, d).getDay()] ?? '';
}

/** Datum als «TT.MM.», z. B. «01.10.». */
export function kurzDatum(datum: string): string {
    const [, m, d] = datum.split('-');

    return d && m ? `${d.slice(0, 2)}.${m}.` : datum;
}

/** Zeit als «07:30 – 16:24 (−30 min)»; ohne Zeiten «–». */
export function zeitraum(e: {
    beginn?: string | null;
    ende?: string | null;
    pause_min?: number | null;
}): string {
    const zeit = e.beginn ? `${e.beginn} – ${e.ende ?? ''}` : '–';

    return e.pause_min ? `${zeit} (−${e.pause_min} min)` : zeit;
}

export function kategorieName(k: Kategorien, key: string): string {
    // aus ClickTime übernommen (#101): «ct:Arbeitszeit» → «Arbeitszeit (clicktime)»
    if (key.startsWith('ct:')) {
        return `${key.slice(3)} (clicktime)`;
    }

    return k.arbeit[key] ?? k.abwesenheit[key] ?? key;
}

export function buchungsartName(
    k: Kategorien,
    key: string | undefined,
): string {
    if (!key) {
        return '';
    }

    return k.buchungsarten?.[key]?.name ?? key;
}
