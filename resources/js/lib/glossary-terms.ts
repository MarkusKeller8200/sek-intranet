/**
 * Glossar-Begriffe für den Baustein «Begriff» (#60) und Begriffe im Freitext (#61).
 *
 * Die Liste kommt von `GET /glossar/begriffe` (mit ETag) und wird einmal je Seitenaufruf geladen. Nach einem
 * Seitenwechsel im Intranet fragt der Browser beim nächsten Gebrauch erneut nach (meist «unverändert»),
 * damit eine geänderte eigene Bezeichnung sofort überall erscheint.
 *
 * Abschaltbar je Person in diesem Browser (Darstellung → «Begriffe hervorheben»).
 */
import { router } from '@inertiajs/react';
import { useEffect, useSyncExternalStore } from 'react';

export type TermEntry = {
    id: string;
    abk: string;
    bez: string;
    kurz: string;
    match: string[];
    historisch: boolean;
};

export type TermIndex = {
    byId: Map<string, TermEntry>;
    byMatch: Map<string, TermEntry[]>;
    /** Erkennt Begriffe im Freitext: Gruppe 1 = Zeichen davor, Gruppe 2 = Begriff. */
    pattern: RegExp | null;
};

const EMPTY: TermIndex = { byId: new Map(), byMatch: new Map(), pattern: null };

let index: TermIndex = EMPTY;
let loading: Promise<void> | null = null;
let stale = true;
let subscribedToRouter = false;
const listeners = new Set<() => void>();

function emit(): void {
    listeners.forEach((l) => l());
}

/**
 * Nur Schreibweisen mit Grossbuchstabe, Ziffer oder Satzzeichen (z. B. «VSA», «K03», «lit.») werden im
 * Freitext erkannt, mindestens zwei Zeichen. So bleiben gewöhnliche Wörter unberührt.
 */
export function isMatchable(token: string): boolean {
    return token.length >= 2 && /[\p{Lu}\d.&/-]/u.test(token);
}

function escape(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function buildIndex(entries: TermEntry[]): TermIndex {
    const byId = new Map<string, TermEntry>();
    const byMatch = new Map<string, TermEntry[]>();
    for (const e of entries) {
        byId.set(e.id, e);
        for (const m of e.match) {
            const list = byMatch.get(m) ?? [];
            if (!list.includes(e)) {
                list.push(e);
            }
            byMatch.set(m, list);
        }
    }
    const tokens = [...byMatch.keys()]
        .filter(isMatchable)
        .sort((a, b) => b.length - a.length);
    // Ganze Wörter: davor kein Buchstabe/keine Ziffer, danach ebenso (ohne Lookbehind für ältere iPads)
    const pattern =
        tokens.length > 0
            ? new RegExp(
                  `(^|[^\\p{L}\\p{N}_])(${tokens.map(escape).join('|')})(?=$|[^\\p{L}\\p{N}_])`,
                  'gu',
              )
            : null;

    return { byId, byMatch, pattern };
}

async function load(): Promise<void> {
    try {
        const res = await fetch('/glossar/begriffe', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = (await res.json()) as { terms?: TermEntry[] };
            index = buildIndex(data.terms ?? []);
            emit();
        }
    } catch {
        // ohne Netz: bisherige Liste behalten, Begriffe erscheinen sonst als normaler Text
    } finally {
        loading = null;
    }
}

function ensureLoaded(): void {
    if (!subscribedToRouter) {
        subscribedToRouter = true;
        router.on('navigate', () => {
            stale = true;
        });
    }
    if (stale && !loading) {
        stale = false;
        loading = load();
    }
}

/** Nur für Tests: Liste direkt setzen. */
export function setTermsForTest(entries: TermEntry[]): void {
    index = buildIndex(entries);
    stale = false;
    emit();
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
}

/** Begriffe laden (falls nötig) und bei Änderungen neu zeichnen. */
export function useGlossaryTerms(active: boolean): TermIndex {
    useEffect(() => {
        if (active) {
            ensureLoaded();
        }
    }, [active]);

    const current = useSyncExternalStore(
        subscribe,
        () => index,
        () => EMPTY,
    );

    return active ? current : EMPTY;
}

// ---------- Einstellung «Begriffe hervorheben» (je Person in diesem Browser) ----------

const SETTING_KEY = 'sbi-terms-highlight';
const settingListeners = new Set<() => void>();

function readSetting(): boolean {
    try {
        return window.localStorage.getItem(SETTING_KEY) !== 'off';
    } catch {
        return true;
    }
}

export function setTermHighlight(on: boolean): void {
    try {
        window.localStorage.setItem(SETTING_KEY, on ? 'on' : 'off');
    } catch {
        // ohne Speicher: gilt bis zum Neuladen
    }
    settingValue = on;
    settingListeners.forEach((l) => l());
}

let settingValue: boolean | null = null;

function settingSnapshot(): boolean {
    if (settingValue === null) {
        settingValue = readSetting();
    }

    return settingValue;
}

function subscribeSetting(listener: () => void): () => void {
    settingListeners.add(listener);

    return () => {
        settingListeners.delete(listener);
    };
}

export function useTermHighlight(): boolean {
    return useSyncExternalStore(subscribeSetting, settingSnapshot, () => true);
}
