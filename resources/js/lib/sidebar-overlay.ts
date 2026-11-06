import { useSyncExternalStore } from 'react';

/**
 * Menü überlagernd (#153): Das Seitenmenü nimmt keinen Platz ein und öffnet über dem Inhalt – z. B. wenn eine
 * Fachanwendung im Browser geteilt neben dem Intranet läuft. Gilt für diesen Browser.
 */
export type SidebarOverlay = 'aus' | 'schmal' | 'immer';

export const SIDEBAR_OVERLAY_LABELS: Record<SidebarOverlay, string> = {
    aus: 'Aus',
    schmal: 'Bei schmalem Fenster',
    immer: 'Immer',
};

/** «Bei schmalem Fenster»: unter dieser Breite (px) überlagernd */
export const SIDEBAR_OVERLAY_BREAKPOINT = 1280;

const STORAGE_KEY = 'sidebar_overlay';
const EVENT = 'sidebar-overlay-change';

const narrow =
    typeof window === 'undefined'
        ? undefined
        : window.matchMedia(`(max-width: ${SIDEBAR_OVERLAY_BREAKPOINT - 1}px)`);

export function sidebarOverlaySetting(): SidebarOverlay {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'schmal' || value === 'immer' ? value : 'aus';
    } catch {
        return 'aus';
    }
}

export function setSidebarOverlay(value: SidebarOverlay): void {
    try {
        localStorage.setItem(STORAGE_KEY, value);
    } catch {
        // ohne Speicher gilt die Einstellung nur bis zum Neuladen nicht
    }
    window.dispatchEvent(new Event(EVENT));
}

function subscribe(callback: () => void): () => void {
    window.addEventListener(EVENT, callback);
    window.addEventListener('storage', callback);
    narrow?.addEventListener('change', callback);

    return () => {
        window.removeEventListener(EVENT, callback);
        window.removeEventListener('storage', callback);
        narrow?.removeEventListener('change', callback);
    };
}

function overlayActive(): boolean {
    const setting = sidebarOverlaySetting();

    return setting === 'immer' || (setting === 'schmal' && !!narrow?.matches);
}

/** Einstellung (für das Formular) */
export function useSidebarOverlaySetting(): SidebarOverlay {
    return useSyncExternalStore(subscribe, sidebarOverlaySetting, () => 'aus');
}

/** Ist das Menü gerade überlagernd? */
export function useSidebarOverlay(): boolean {
    return useSyncExternalStore(subscribe, overlayActive, () => false);
}
