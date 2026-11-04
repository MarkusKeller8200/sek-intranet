/**
 * Auf- und Zuklappen der Hauptmenüs in der Seitenleiste (gemeinsam für Menü und Steuerung unter dem Logo).
 *
 * - Am Bildschirm wird der Zustand je Gruppe in diesem Browser gemerkt, mobil nicht (bei jedem Öffnen zu).
 * - «Nur ein Menü offen» (Einstellung in diesem Browser): Wer ein Hauptmenü aufklappt, klappt alle anderen zu.
 */
import { useSyncExternalStore } from 'react';

const GROUPS_KEY = 'intranet.nav.groups';
const SINGLE_KEY = 'intranet.nav.single';

type State = {
    stored: Record<string, boolean>;
    mobile: Record<string, boolean>;
    single: boolean;
};

function read<T>(key: string, fallback: T): T {
    try {
        const raw = window.localStorage.getItem(key);

        return raw ? (JSON.parse(raw) as T) : fallback;
    } catch {
        return fallback;
    }
}

function write(key: string, value: unknown): void {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Ohne Speicher gilt der Zustand nur bis zum Neuladen.
    }
}

let state: State | null = null;
const listeners = new Set<() => void>();

function current(): State {
    if (state === null) {
        const stored = read<unknown>(GROUPS_KEY, {});
        state = {
            stored:
                stored && typeof stored === 'object'
                    ? (stored as Record<string, boolean>)
                    : {},
            mobile: {},
            single: read<unknown>(SINGLE_KEY, false) === true,
        };
    }

    return state;
}

function update(next: Partial<State>): void {
    state = { ...current(), ...next };
    if (next.stored) {
        write(GROUPS_KEY, state.stored);
    }
    if (next.single !== undefined) {
        write(SINGLE_KEY, state.single);
    }
    listeners.forEach((l) => l());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

const SERVER: State = { stored: {}, mobile: {}, single: false };

export function useNavGroups(): State {
    return useSyncExternalStore(subscribe, current, () => SERVER);
}

/** Eine Gruppe (oder ein Untermenü «sub:…») auf- oder zuklappen; bei «nur eines offen» die anderen Hauptmenüs zu. */
export function setGroupOpen(
    key: string,
    open: boolean,
    groupKeys: string[],
    mobile: boolean,
): void {
    const s = current();
    const map = { ...(mobile ? s.mobile : s.stored), [key]: open };
    if (open && s.single && !key.startsWith('sub:')) {
        for (const other of groupKeys) {
            if (other !== key) {
                map[other] = false;
            }
        }
    }
    update(mobile ? { mobile: map } : { stored: map });
}

/** Alle Hauptmenüs auf- oder zuklappen. */
export function setAllGroups(
    groupKeys: string[],
    open: boolean,
    mobile: boolean,
): void {
    const s = current();
    const map = { ...(mobile ? s.mobile : s.stored) };
    for (const key of groupKeys) {
        map[key] = open;
    }
    update(mobile ? { mobile: map } : { stored: map });
}

/** «Nur ein Menü offen» ein- oder ausschalten; beim Einschalten bleibt nur $keep offen. */
export function setSingleOpen(
    single: boolean,
    groupKeys: string[],
    keep: string | null,
    mobile: boolean,
): void {
    update({ single });
    if (single) {
        setAllGroups(groupKeys, false, mobile);
        if (keep !== null) {
            setGroupOpen(keep, true, groupKeys, mobile);
        }
    }
}

/** Mobil: bei jedem Öffnen des Menüs alle Hauptmenüs zu. */
export function resetMobileGroups(): void {
    update({ mobile: {} });
}
