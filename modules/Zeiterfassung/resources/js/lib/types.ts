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

export function kategorieName(k: Kategorien, key: string): string {
    return k.arbeit[key] ?? k.abwesenheit[key] ?? key;
}

export function buchungsartName(k: Kategorien, key: string | undefined): string {
    if (!key) {
        return '';
    }

    return k.buchungsarten?.[key]?.name ?? key;
}
