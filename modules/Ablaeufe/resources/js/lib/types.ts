export type Phase = { key: string; titel: string };

export type Schritt = {
    id: string;
    phase: string;
    text: string;
    verantwortung: string;
    kommunikation: string;
    dokumente: string[];
    bemerkung: string;
    frist_tage: number | null;
    gilt_fuer: string[];
    gegenstueck: string | null;
};

export type Ablauf = {
    id: string;
    titel: string;
    bereich: string;
    status: string;
    beschreibung: string;
    verantwortlich: string;
    gueltig_ab: string | null;
    phasen: Phase[];
    schritte: Schritt[];
    updated_at?: string;
    geaendert_von_name?: string | null;
};

export type Gruppe = { id: string; name: string; bemerkung: string };

export type Dokument = { id: string; title: string; href: string };

/** «10 Tage vor Stichtag» / «am Stichtag» / «3 Tage nach Stichtag» */
export function fristText(tage: number | null): string {
    if (tage === null) {
        return '';
    }
    if (tage === 0) {
        return 'am Stichtag';
    }

    return tage < 0
        ? `${Math.abs(tage)} Tage vor Stichtag`
        : `${tage} Tage nach Stichtag`;
}

/** Nummer eines Schritts innerhalb seiner Phase (1, 2, …). */
export function nummer(schritte: Schritt[], schritt: Schritt): number {
    return (
        schritte
            .filter((s) => s.phase === schritt.phase)
            .findIndex((s) => s.id === schritt.id) + 1
    );
}
