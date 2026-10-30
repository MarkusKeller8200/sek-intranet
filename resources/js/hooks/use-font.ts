import { useSyncExternalStore } from 'react';

/** Wählbare Schriften (selbst gehostet über laravel-vite-plugin, siehe vite.config.ts). */
export const FONTS = {
    'open-sans': {
        label: 'Open Sans',
        hint: 'Standard (wie sekbuelach.ch)',
        stack: "'Open Sans', ui-sans-serif, system-ui, sans-serif",
    },
    atkinson: {
        label: 'Atkinson Hyperlegible',
        hint: 'Besonders gut lesbar, klare Buchstabenformen',
        stack: "'Atkinson Hyperlegible', ui-sans-serif, system-ui, sans-serif",
    },
    inter: {
        label: 'Inter',
        hint: 'Kompakt, gut für Tabellen',
        stack: "'Inter', ui-sans-serif, system-ui, sans-serif",
    },
    serif: {
        label: 'Source Serif 4',
        hint: 'Serifenschrift für längere Texte',
        stack: "'Source Serif 4', Georgia, 'Times New Roman', serif",
    },
    system: {
        label: 'Systemschrift',
        hint: 'Schrift des Betriebssystems, lädt nichts nach',
        stack: "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
    },
} as const;

export type FontKey = keyof typeof FONTS;

const STORAGE_KEY = 'font';
const listeners = new Set<() => void>();
let current: FontKey = 'open-sans';

const isFontKey = (value: unknown): value is FontKey =>
    typeof value === 'string' && value in FONTS;

function apply(font: FontKey): void {
    if (typeof document === 'undefined') {
        return;
    }
    if (font === 'open-sans') {
        document.documentElement.style.removeProperty('--font-sans');
    } else {
        document.documentElement.style.setProperty(
            '--font-sans',
            FONTS[font].stack,
        );
    }
    document.documentElement.dataset.font = font;
}

/** Beim Start: gewählte Schrift aus diesem Browser übernehmen. */
export function initializeFont(): void {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        current = isFontKey(stored) ? stored : 'open-sans';
    } catch {
        current = 'open-sans';
    }
    apply(current);
}

export function useFont(): {
    font: FontKey;
    updateFont: (font: FontKey) => void;
} {
    const font = useSyncExternalStore(
        (callback) => {
            listeners.add(callback);

            return () => listeners.delete(callback);
        },
        () => current,
        (): FontKey => 'open-sans',
    );

    const updateFont = (next: FontKey) => {
        current = next;
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // ohne Speicher nur bis zum Neuladen
        }
        apply(next);
        listeners.forEach((listener) => listener());
    };

    return { font, updateFont };
}
