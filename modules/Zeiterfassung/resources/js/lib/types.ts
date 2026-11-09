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
    status: 'offen' | 'eingereicht' | 'visiert' | 'zurueck' | 'abgeschlossen';
    bemerkung?: string | null;
    eingereicht_at?: string | null;
    visiert_at?: string | null;
    /** In ClickTime abgeschlossen (#164) */
    abgeschlossen_at?: string | null;
    abgeschlossen_stellvertretend?: boolean | null;
    /** Nachbuchungen in ClickTime nach dem Abschluss (#164) */
    abweichung?: {
        anzahl: number;
        neu: number;
        geaendert: number;
        geloescht: number;
        geprueft_at: string;
    } | null;
};

/** Gesperrt für Änderungen: eingereicht, visiert oder in ClickTime abgeschlossen. */
export function istGesperrt(status: Status['status']): boolean {
    return (
        status === 'eingereicht' ||
        status === 'visiert' ||
        status === 'abgeschlossen'
    );
}

/** Datum «JJJJ-MM-TT» als «TT.MM.» für Listen fehlender Tage (#33). */
export function tageListe(tage: string[]): string {
    return tage.map((t) => `${t.slice(8, 10)}.${t.slice(5, 7)}.`).join(', ');
}

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

/** Samstag oder Sonntag. */
export function wochenende(datum: string): boolean {
    const tag = wochentag(datum);

    return tag === 'SA' || tag === 'SO';
}

/**
 * Art eines Tages für die Farbe der Totalzeile (#103): Krankheit/Unfall vor Ferien vor Kompensation, sonst
 * Wochenende oder Feiertag («frei»), sonst «normal». Erkannt an Buchungsart, Kategorie oder Name (auch «… (clicktime)»).
 */
export function tagesArt(
    datum: string,
    liste: Eintrag[],
    k: Kategorien,
    feiertag: boolean,
): 'krank' | 'ferien' | 'kompensation' | 'frei' | 'normal' {
    const texte = liste.map((e) => {
        const name = buchungsartName(k, e.buchungsart);

        return `${e.buchungsart ?? ''} ${e.kategorie} ${name}`.toLowerCase();
    });
    const hat = (...worte: string[]) =>
        texte.some((t) => worte.some((w) => t.includes(w)));
    if (hat('krank', 'unfall')) {
        return 'krank';
    }
    if (hat('ferien')) {
        return 'ferien';
    }
    if (hat('kompensation')) {
        return 'kompensation';
    }

    return feiertag || wochenende(datum) ? 'frei' : 'normal';
}

/** Alle Tage eines Monats «JJJJ-MM» als «JJJJ-MM-TT». */
export function tageImMonat(monat: string): string[] {
    const [y, m] = monat.split('-').map(Number);
    const tage: string[] = [];
    for (let d = 1; d <= new Date(y, m, 0).getDate(); d++) {
        tage.push(`${monat}-${String(d).padStart(2, '0')}`);
    }

    return tage;
}

/** Erste bis letzte Zeit eines Tages, z. B. «07:30 – 16:24»; ohne Zeiten leer. */
export function tagesZeit(
    liste: { beginn?: string | null; ende?: string | null }[],
): string {
    const beginn = liste.flatMap((e) => (e.beginn ? [e.beginn] : []));
    const ende = liste.flatMap((e) => (e.ende ? [e.ende] : []));
    if (beginn.length === 0) {
        return '';
    }

    return `${beginn.sort()[0]} – ${ende.sort().reverse()[0] ?? ''}`;
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
