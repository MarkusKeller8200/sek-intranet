import type { ReactNode } from 'react';

/** Spalte der bearbeitbaren Tabelle. */
export type GridColumn<T> = {
    id: keyof T & string;
    title: string;
    type?: 'text' | 'email' | 'tel' | 'select' | 'multi' | 'bool' | 'date';
    /** «select»: Schlüssel → Bezeichnung; «multi»: erlaubte Werte (Schlüssel = gespeicherter Text) */
    options?: Record<string, string>;
    /** «select»/«multi»: Farbe je Wert aus der Palette des Farbkonzepts (farbige Labels in Liste und Filter) */
    colors?: Record<string, string>;
    editable?: boolean;
    /** Leer lassen nicht erlaubt (Entf überspringt die Spalte) */
    required?: boolean;
    /** Anzeige ausserhalb des Bearbeitens */
    render?: (row: T) => ReactNode;
    hidden?: boolean;
    /** Zusätzlicher Suchtext der Zeile für die Suche (z. B. Aliase, auch wenn deren Spalten ausgeblendet sind) */
    search?: (row: T) => string;
    className?: string;
    /** Mehrfachwerte als Text (z. B. «A; B»): beim Gruppieren erscheint die Zeile in jeder Gruppe; bei «multi» Trennzeichen (Standard «;»). */
    separator?: string;
};

export type GridRow = { id: string };

export type FilterOp =
    | 'contains'
    | 'starts'
    | 'ends'
    | 'equals'
    | 'empty'
    | 'notEmpty'
    | 'in'
    | 'notIn'
    | 'yes'
    | 'no'
    | 'before'
    | 'after'
    | 'between'
    | 'today'
    | 'thisWeek'
    | 'thisMonth'
    | 'lastMonth'
    | 'thisYear';

export type GridFilter = {
    op: FilterOp;
    value?: string;
    value2?: string;
    values?: string[];
};

export type SortRule = { id: string; desc: boolean };

/** Alles, was eine Ansicht ausmacht (lokal gemerkt oder als Ansicht gespeichert). */
export type GridViewState = {
    order?: string[];
    visibility?: Record<string, boolean>;
    sizing?: Record<string, number>;
    pinned?: string[];
    sorting?: SortRule[];
    filters?: Record<string, GridFilter>;
    logic?: 'and' | 'or';
    search?: string;
    grouping?: string | null;
    viewId?: string | null;
};

export type SavedView = {
    id: string;
    name: string;
    shared: boolean;
    mine: boolean;
    state: GridViewState;
};

/** Eine Zellenänderung (für Speichern, Rückgängig, Wiederholen). */
export type CellChange<T> = {
    id: string;
    field: keyof T & string;
    old: unknown;
    value: unknown;
};

export type SaveSource =
    | 'Tabelle'
    | 'Einfügen'
    | 'Rückgängig'
    | 'Wiederholen'
    | 'Löschen';
