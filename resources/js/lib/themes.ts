/** Themes (Farbkonzepte), siehe App\Support\Themes. */
import type { CSSProperties } from 'react';

export type Mode = 'light' | 'dark';
export type Colors = Record<string, string>;

export type ThemeData = {
    id: string;
    name: string;
    description: string;
    light: Colors;
    dark: Colors;
    builtin: boolean;
    active: boolean;
};

export type ThemeDefaults = { light: Colors; dark: Colors };

/** Vollständige Farben eines Modus: Vorgabe aus app.css + Abweichungen des Themes. */
export function resolveColors(theme: Pick<ThemeData, 'light' | 'dark'> | null, defaults: ThemeDefaults, mode: Mode): Colors {
    return { ...defaults[mode], ...(theme?.[mode] ?? {}) };
}

/**
 * CSS-Variablen für einen Bereich (Vorschau). Tailwind löst `--color-*` bereits am <html> auf;
 * damit ein Teilbereich andere Farben zeigt, werden beide Namen gesetzt.
 */
export function cssVars(colors: Colors): CSSProperties {
    return Object.fromEntries(
        Object.entries(colors).flatMap(([k, v]) => [
            [`--${k}`, v],
            [`--color-${k}`, v],
        ]),
    ) as CSSProperties;
}

function luminance(hex: string): number {
    const n = parseInt(hex.slice(1), 16);
    const channel = (c: number) => {
        const s = c / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * channel((n >> 16) & 255) + 0.7152 * channel((n >> 8) & 255) + 0.0722 * channel(n & 255);
}

/** Kontrastverhältnis nach WCAG (1 bis 21). */
export function contrast(a: string, b: string): number {
    if (!/^#[0-9a-f]{6}$/i.test(a) || !/^#[0-9a-f]{6}$/i.test(b)) {
        return 0;
    }
    const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (l1 + 0.05) / (l2 + 0.05);
}

/** Paare Schrift/Fläche, die mindestens AA (4.5:1) erreichen müssen. */
export const CONTRAST_PAIRS: { label: string; fg: string; bg: string }[] = [
    { label: 'Text auf Hintergrund', fg: 'foreground', bg: 'background' },
    { label: 'Text auf Karte', fg: 'card-foreground', bg: 'card' },
    { label: 'Leiser Text auf Hintergrund', fg: 'muted-foreground', bg: 'background' },
    { label: 'Button-Schrift auf Hauptfarbe', fg: 'primary-foreground', bg: 'primary' },
    { label: 'Hauptfarbe (Links) auf Hintergrund', fg: 'primary', bg: 'background' },
    { label: 'Akzent-Schrift auf Akzent', fg: 'accent-foreground', bg: 'accent' },
    { label: 'Neben-Schrift auf Neben', fg: 'secondary-foreground', bg: 'secondary' },
    { label: 'Menü-Schrift auf Seitenleiste', fg: 'sidebar-foreground', bg: 'sidebar' },
    { label: 'Erfolg auf hellem Erfolg', fg: 'success', bg: 'success-soft' },
    { label: 'Warnung auf heller Warnung', fg: 'warning', bg: 'warning-soft' },
];

/** Lesbare Bezeichnungen der Farbwerte. */
export const TOKEN_LABELS: Record<string, string> = {
    brand: 'Marke (Logo-Blau, Linien)',
    primary: 'Hauptfarbe (Buttons, Links)',
    'primary-foreground': 'Schrift auf Hauptfarbe',
    ring: 'Fokusrahmen',
    background: 'Hintergrund',
    foreground: 'Text',
    card: 'Karte',
    'card-foreground': 'Text auf Karte',
    popover: 'Popover / Menü',
    'popover-foreground': 'Text im Popover',
    muted: 'Leise Fläche',
    'muted-foreground': 'Leiser Text',
    border: 'Linien',
    input: 'Rahmen Eingabefeld',
    accent: 'Akzent (Hover, aktiv)',
    'accent-foreground': 'Text auf Akzent',
    secondary: 'Neben-Button',
    'secondary-foreground': 'Text Neben-Button',
    destructive: 'Gefahr / Löschen',
    'destructive-foreground': 'Text Fehler',
    success: 'Erfolg',
    'success-soft': 'Erfolg hell',
    warning: 'Warnung',
    'warning-soft': 'Warnung hell',
    info: 'Hinweis',
    'info-soft': 'Hinweis hell',
    sidebar: 'Seitenleiste',
    'sidebar-foreground': 'Text Seitenleiste',
    'sidebar-primary': 'Seitenleiste Hauptfarbe',
    'sidebar-primary-foreground': 'Schrift Seitenleiste Hauptfarbe',
    'sidebar-accent': 'Seitenleiste aktiv',
    'sidebar-accent-foreground': 'Text Seitenleiste aktiv',
    'sidebar-border': 'Linien Seitenleiste',
    'sidebar-ring': 'Fokus Seitenleiste',
    'chart-1': 'Diagramm 1',
    'chart-2': 'Diagramm 2',
    'chart-3': 'Diagramm 3',
    'chart-4': 'Diagramm 4',
    'chart-5': 'Diagramm 5',
};
