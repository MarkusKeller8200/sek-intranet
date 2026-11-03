/**
 * Farbpalette für Kategorien, Schulhäuser, Rollen und Zielgruppen.
 * Welche Farbe ein Wert erhält, steht in config/intranet.php → colors.
 * Die Klassen sind ausgeschrieben, damit Tailwind sie findet.
 * «panel»: farbige Fläche (Hauptmenü der Seitenleiste), Text mit Kontrast AA.
 */
export const palette = {
    brand: {
        badge: 'border-brand/30 bg-info-soft text-primary',
        dot: 'bg-brand',
        bar: 'border-l-brand',
        icon: 'text-brand',
        panel: 'bg-info-soft text-primary hover:bg-info-soft/70',
    },
    slate: {
        badge: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        dot: 'bg-slate-400',
        bar: 'border-l-slate-400',
        icon: 'text-slate-500',
        panel: 'bg-slate-100 text-slate-800 hover:bg-slate-200/70 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-900',
    },
    sky: {
        badge: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
        dot: 'bg-sky-500',
        bar: 'border-l-sky-500',
        icon: 'text-sky-600 dark:text-sky-400',
        panel: 'bg-sky-100 text-sky-800 hover:bg-sky-200/70 dark:bg-sky-950 dark:text-sky-200 dark:hover:bg-sky-900',
    },
    cyan: {
        badge: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-200',
        dot: 'bg-cyan-500',
        bar: 'border-l-cyan-500',
        icon: 'text-cyan-600 dark:text-cyan-400',
        panel: 'bg-cyan-100 text-cyan-800 hover:bg-cyan-200/70 dark:bg-cyan-950 dark:text-cyan-200 dark:hover:bg-cyan-900',
    },
    teal: {
        badge: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200',
        dot: 'bg-teal-500',
        bar: 'border-l-teal-500',
        icon: 'text-teal-600 dark:text-teal-400',
        panel: 'bg-teal-100 text-teal-800 hover:bg-teal-200/70 dark:bg-teal-950 dark:text-teal-200 dark:hover:bg-teal-900',
    },
    emerald: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        dot: 'bg-emerald-500',
        bar: 'border-l-emerald-500',
        icon: 'text-emerald-600 dark:text-emerald-400',
        panel: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200/70 dark:bg-emerald-950 dark:text-emerald-200 dark:hover:bg-emerald-900',
    },
    amber: {
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
        dot: 'bg-amber-500',
        bar: 'border-l-amber-500',
        icon: 'text-amber-600 dark:text-amber-400',
        panel: 'bg-amber-100 text-amber-800 hover:bg-amber-200/70 dark:bg-amber-950 dark:text-amber-200 dark:hover:bg-amber-900',
    },
    orange: {
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
        dot: 'bg-orange-500',
        bar: 'border-l-orange-500',
        icon: 'text-orange-600 dark:text-orange-400',
        panel: 'bg-orange-100 text-orange-800 hover:bg-orange-200/70 dark:bg-orange-950 dark:text-orange-200 dark:hover:bg-orange-900',
    },
    rose: {
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
        dot: 'bg-rose-500',
        bar: 'border-l-rose-500',
        icon: 'text-rose-600 dark:text-rose-400',
        panel: 'bg-rose-100 text-rose-800 hover:bg-rose-200/70 dark:bg-rose-950 dark:text-rose-200 dark:hover:bg-rose-900',
    },
    violet: {
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200',
        dot: 'bg-violet-500',
        bar: 'border-l-violet-500',
        icon: 'text-violet-600 dark:text-violet-400',
        panel: 'bg-violet-100 text-violet-800 hover:bg-violet-200/70 dark:bg-violet-950 dark:text-violet-200 dark:hover:bg-violet-900',
    },
    indigo: {
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
        dot: 'bg-indigo-500',
        bar: 'border-l-indigo-500',
        icon: 'text-indigo-600 dark:text-indigo-400',
        panel: 'bg-indigo-100 text-indigo-800 hover:bg-indigo-200/70 dark:bg-indigo-950 dark:text-indigo-200 dark:hover:bg-indigo-900',
    },
} as const;

export type ColorName = keyof typeof palette;

/**
 * Druckfarben der Palette (Hintergrund, Rand, Text) für Ausgaben ohne Tailwind, z. B. PDF-Export des Glossars (#79).
 * Entsprechen den hellen Badge-Farben (…-50, …-200, …-800).
 */
export const printPalette: Record<ColorName, { bg: string; border: string; text: string }> = {
    brand: { bg: '#e8f4fb', border: '#b3e0f3', text: '#0272ad' },
    slate: { bg: '#f1f5f9', border: '#e2e8f0', text: '#334155' },
    sky: { bg: '#f0f9ff', border: '#bae6fd', text: '#075985' },
    cyan: { bg: '#ecfeff', border: '#a5f3fc', text: '#155e75' },
    teal: { bg: '#f0fdfa', border: '#99f6e4', text: '#115e59' },
    emerald: { bg: '#ecfdf5', border: '#a7f3d0', text: '#065f46' },
    amber: { bg: '#fffbeb', border: '#fde68a', text: '#92400e' },
    orange: { bg: '#fff7ed', border: '#fed7aa', text: '#9a3412' },
    rose: { bg: '#fff1f2', border: '#fecdd3', text: '#9f1239' },
    violet: { bg: '#f5f3ff', border: '#ddd6fe', text: '#5b21b6' },
    indigo: { bg: '#eef2ff', border: '#c7d2fe', text: '#3730a3' },
};
export type ColorClasses = (typeof palette)[ColorName];

/** Auswahllisten mit Farben (Namen wie in den geteilten options). */
export type ColoredList =
    | 'schools'
    | 'roles'
    | 'personTypes'
    | 'audiences'
    | 'announcementCategories'
    | 'eventCategories'
    | 'documentCategories'
    | 'guiStatuses';

/** Schlüssel in config/intranet.php → colors. */
export const colorConfigKey: Record<ColoredList, string> = {
    schools: 'schools',
    roles: 'roles',
    personTypes: 'person_types',
    audiences: 'audiences',
    announcementCategories: 'announcement_categories',
    eventCategories: 'event_categories',
    documentCategories: 'document_categories',
    guiStatuses: 'gui_statuses',
};

export function isColorName(value: unknown): value is ColorName {
    return typeof value === 'string' && value in palette;
}

/** Statusfarben (Erfolg, Warnung, Fehler, Hinweis) – aus den CSS-Tokens. */
export const statusClasses = {
    success: 'border-success/30 bg-success-soft text-success',
    warning: 'border-warning/30 bg-warning-soft text-warning',
    error: 'border-destructive/30 bg-destructive/10 text-destructive-foreground',
    info: 'border-info/30 bg-info-soft text-info',
} as const;

export type Status = keyof typeof statusClasses;
