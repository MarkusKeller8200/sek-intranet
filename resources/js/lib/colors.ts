/**
 * Farbpalette für Kategorien, Schulhäuser, Rollen und Zielgruppen.
 * Welche Farbe ein Wert erhält, steht in config/intranet.php → colors.
 * Die Klassen sind ausgeschrieben, damit Tailwind sie findet.
 */
export const palette = {
    brand: {
        badge: 'border-brand/30 bg-info-soft text-primary',
        dot: 'bg-brand',
        bar: 'border-l-brand',
        icon: 'text-brand',
    },
    slate: {
        badge: 'border-slate-200 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200',
        dot: 'bg-slate-400',
        bar: 'border-l-slate-400',
        icon: 'text-slate-500',
    },
    sky: {
        badge: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-200',
        dot: 'bg-sky-500',
        bar: 'border-l-sky-500',
        icon: 'text-sky-600 dark:text-sky-400',
    },
    cyan: {
        badge: 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-900 dark:bg-cyan-950 dark:text-cyan-200',
        dot: 'bg-cyan-500',
        bar: 'border-l-cyan-500',
        icon: 'text-cyan-600 dark:text-cyan-400',
    },
    teal: {
        badge: 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-200',
        dot: 'bg-teal-500',
        bar: 'border-l-teal-500',
        icon: 'text-teal-600 dark:text-teal-400',
    },
    emerald: {
        badge: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
        dot: 'bg-emerald-500',
        bar: 'border-l-emerald-500',
        icon: 'text-emerald-600 dark:text-emerald-400',
    },
    amber: {
        badge: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
        dot: 'bg-amber-500',
        bar: 'border-l-amber-500',
        icon: 'text-amber-600 dark:text-amber-400',
    },
    orange: {
        badge: 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-900 dark:bg-orange-950 dark:text-orange-200',
        dot: 'bg-orange-500',
        bar: 'border-l-orange-500',
        icon: 'text-orange-600 dark:text-orange-400',
    },
    rose: {
        badge: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200',
        dot: 'bg-rose-500',
        bar: 'border-l-rose-500',
        icon: 'text-rose-600 dark:text-rose-400',
    },
    violet: {
        badge: 'border-violet-200 bg-violet-50 text-violet-800 dark:border-violet-900 dark:bg-violet-950 dark:text-violet-200',
        dot: 'bg-violet-500',
        bar: 'border-l-violet-500',
        icon: 'text-violet-600 dark:text-violet-400',
    },
    indigo: {
        badge: 'border-indigo-200 bg-indigo-50 text-indigo-800 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-200',
        dot: 'bg-indigo-500',
        bar: 'border-l-indigo-500',
        icon: 'text-indigo-600 dark:text-indigo-400',
    },
} as const;

export type ColorName = keyof typeof palette;
export type ColorClasses = (typeof palette)[ColorName];

/** Auswahllisten mit Farben (Namen wie in den geteilten options). */
export type ColoredList =
    | 'schools'
    | 'roles'
    | 'audiences'
    | 'announcementCategories'
    | 'eventCategories'
    | 'documentCategories';

/** Schlüssel in config/intranet.php → colors. */
export const colorConfigKey: Record<ColoredList, string> = {
    schools: 'schools',
    roles: 'roles',
    audiences: 'audiences',
    announcementCategories: 'announcement_categories',
    eventCategories: 'event_categories',
    documentCategories: 'document_categories',
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
