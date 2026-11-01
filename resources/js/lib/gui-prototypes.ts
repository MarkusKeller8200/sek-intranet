/** Typen des Menüs «GUI-Prototypen» (App\Support\GuiPrototypes). */

export type GuiStatus = 'entwicklung' | 'pruefung' | 'freigegeben';

export type GuiLogEntry = {
    status: GuiStatus;
    at: string;
    by: string | null;
    comment: string | null;
};

export type GuiVersion = {
    version: string;
    status: GuiStatus;
    changes: string;
    created_at: string | null;
    created_by: string | null;
    review_at?: string | null;
    review_by?: string | null;
    released_at?: string | null;
    released_by?: string | null;
    log?: GuiLogEntry[];
};

export type GuiWish = {
    id: string;
    text: string;
    by: string | null;
    at: string;
    version: string | null;
};

export type GuiRework = {
    file: string;
    version: string;
    done_at: string | null;
    done_by: string | null;
};

export type GuiSummary = {
    released: GuiVersion | null;
    open: GuiVersion | null;
    rework_total: number;
    rework_open: number;
    wishes_open: number;
};

export type GuiRecord = {
    key: string;
    versions: GuiVersion[];
    wishes: GuiWish[];
    rework: GuiRework[];
};

export type GuiComponent = {
    key: string;
    title: string;
    icon: string;
    description: string;
    imports: string[];
};

/** Name zu einer Personen-ID (oder «–»). */
export function nameOf(
    names: Record<string, string>,
    id: string | null | undefined,
): string {
    return id ? (names[id] ?? 'Unbekannt') : '–';
}
