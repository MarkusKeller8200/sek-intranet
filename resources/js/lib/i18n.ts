/**
 * Mehrsprachigkeit (Konzept: docs/KONZEPT-SPRACHEN.md, Vorbild TagStudio).
 *
 * Übersetzungsschicht über dem DOM statt Schlüsseln im Code: Der deutsche Text ist der
 * Schlüssel, die Kataloge in resources/js/i18n/*.json enthalten je Text
 * ["English", "Züritüütsch"]. Zahlen werden beim Nachschlagen zu {n}.
 * Daten (Namen, Einträge, Eingaben) sind mit translate="no" geschützt.
 */
import common from '@/i18n/common.json';
import concepts from '@/i18n/concepts.json';
import grid from '@/i18n/grid.json';
import navigation from '@/i18n/navigation.json';
import options from '@/i18n/options.json';
import pages from '@/i18n/pages.json';
import people from '@/i18n/people.json';
import system from '@/i18n/system.json';

export const LANGUAGES = {
    'de-CH': { label: 'Deutsch', native: 'Deutsch', short: 'DE' },
    en: { label: 'Englisch', native: 'English', short: 'EN' },
    'gsw-u-sd-chzh': { label: 'Zürichdeutsch', native: 'Züritüütsch', short: 'ZH' },
} as const;

export type Language = keyof typeof LANGUAGES;

/** Spalten in den Katalogen (Reihenfolge der Übersetzungen je Text). */
export const COLUMNS: Exclude<Language, 'de-CH'>[] = ['en', 'gsw-u-sd-chzh'];

type Catalog = Record<string, [string, string]>;

export const CATALOGS: Record<string, Catalog> = {
    common: common as unknown as Catalog,
    concepts: concepts as unknown as Catalog,
    grid: grid as unknown as Catalog,
    navigation: navigation as unknown as Catalog,
    options: options as unknown as Catalog,
    pages: pages as unknown as Catalog,
    people: people as unknown as Catalog,
    system: system as unknown as Catalog,
};

const STORAGE_KEY = 'lang';
const ATTRIBUTES = ['title', 'placeholder', 'aria-label', 'alt'] as const;
const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'CODE', 'PRE', 'TEXTAREA', 'KBD', 'SAMP']);
const NUMBER = /\d+(?:['’.,]\d+)*/g;

let current: Language = 'de-CH';
let table = new Map<string, string>();
const listeners = new Set<() => void>();
/** Texte ohne Übersetzung, die in diesem Browser vorkamen (Sprachkonzept-Seite zeigt sie). */
export const missing = new Set<string>();

const originals = new WeakMap<Text, string>();
const written = new WeakMap<Text, string>();
const attrOriginals = new WeakMap<Element, Map<string, string>>();
const attrWritten = new WeakMap<Element, Map<string, string>>();
let observer: MutationObserver | null = null;
let busy = false;

export function isLanguage(value: unknown): value is Language {
    return typeof value === 'string' && value in LANGUAGES;
}

export function getLanguage(): Language {
    return current;
}

function buildTable(language: Language) {
    table = new Map();
    const column = COLUMNS.indexOf(language as Exclude<Language, 'de-CH'>);
    if (column < 0) {
        return;
    }
    for (const catalog of Object.values(CATALOGS)) {
        for (const [german, translations] of Object.entries(catalog)) {
            const text = translations[column];
            if (text) {
                table.set(german, text);
            }
        }
    }
}

/** Schlüssel eines Textes: Leerraum vereinheitlicht, Zahlen als {n}. */
export function keyOf(text: string): { key: string; numbers: string[] } {
    const clean = text.replace(/\s+/g, ' ').trim();
    const numbers = clean.match(NUMBER) ?? [];

    return { key: clean.replace(NUMBER, '{n}'), numbers };
}

function fill(template: string, numbers: string[]): string {
    let i = 0;

    return template.replace(/\{n\}/g, () => numbers[i++] ?? '');
}

/** Übersetzt einen Text in die aktuelle Sprache; null, wenn kein Eintrag passt. */
export function translate(text: string): string | null {
    if (current === 'de-CH') {
        return null;
    }
    const { key, numbers } = keyOf(text);
    if (key === '' || !/[A-Za-zÄÖÜäöü]/.test(key)) {
        return null;
    }

    const direct = table.get(key);
    if (direct !== undefined) {
        return fill(direct, numbers);
    }

    // «Teil: Rest» und «Teil · Teil» einzeln übersetzen
    for (const separator of [' · ', ': ', ' – ']) {
        if (key.includes(separator)) {
            const parts = text.trim().split(separator);
            const translated = parts.map((p) => translate(p) ?? p);
            if (translated.some((t, i) => t !== parts[i])) {
                return translated.join(separator);
            }
        }
    }

    // Text mit Auslassungszeichen am Ende (Menüpunkte «… …»)
    if (key.endsWith(' …')) {
        const base = translate(key.slice(0, -2));
        if (base !== null) {
            return `${base} …`;
        }
    }

    missing.add(key);

    return null;
}

/** Für Texte, die nicht im DOM landen (z. B. confirm, document.title). */
export function t(text: string): string {
    return translate(text) ?? text;
}

function skipped(node: Node): boolean {
    let element: Element | null =
        node.nodeType === Node.ELEMENT_NODE ? (node as Element) : node.parentElement;
    while (element) {
        if (SKIP_TAGS.has(element.tagName) || element.getAttribute('translate') === 'no') {
            return true;
        }
        element = element.parentElement;
    }

    return false;
}

function processText(node: Text) {
    if (skipped(node)) {
        return;
    }
    const now = node.data;
    // Hat React den Text seit unserer letzten Änderung neu gesetzt, ist das der neue Ausgangstext
    if (written.get(node) !== now) {
        originals.set(node, now);
    }
    const original = originals.get(node) ?? now;
    const leading = /^\s*/.exec(original)?.[0] ?? '';
    const trailing = /\s*$/.exec(original)?.[0] ?? '';
    const translated = translate(original);
    const next = translated === null ? original : leading + translated + trailing;
    if (next !== now) {
        node.data = next;
    }
    written.set(node, next);
}

function processAttributes(element: Element) {
    if (skipped(element)) {
        return;
    }
    let stored = attrOriginals.get(element);
    let done = attrWritten.get(element);
    for (const name of ATTRIBUTES) {
        const value = element.getAttribute(name);
        if (value === null || value === '') {
            continue;
        }
        stored ??= new Map();
        done ??= new Map();
        if (done.get(name) !== value) {
            stored.set(name, value);
        }
        const original = stored.get(name) ?? value;
        const next = translate(original) ?? original;
        if (next !== value) {
            element.setAttribute(name, next);
        }
        done.set(name, next);
    }
    if (stored && done) {
        attrOriginals.set(element, stored);
        attrWritten.set(element, done);
    }
}

function processTree(root: Node) {
    if (root.nodeType === Node.TEXT_NODE) {
        processText(root as Text);

        return;
    }
    if (root.nodeType !== Node.ELEMENT_NODE && root.nodeType !== Node.DOCUMENT_NODE) {
        return;
    }
    if (root.nodeType === Node.ELEMENT_NODE) {
        if (skipped(root)) {
            return;
        }
        processAttributes(root as Element);
    }
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT);
    let node = walker.nextNode();
    while (node) {
        if (node.nodeType === Node.TEXT_NODE) {
            processText(node as Text);
        } else {
            processAttributes(node as Element);
        }
        node = walker.nextNode();
    }
}

function run(fn: () => void) {
    if (busy) {
        return;
    }
    busy = true;
    try {
        fn();
    } finally {
        busy = false;
    }
}

function startObserver() {
    if (observer || typeof MutationObserver === 'undefined') {
        return;
    }
    observer = new MutationObserver((mutations) =>
        run(() => {
            for (const m of mutations) {
                if (m.type === 'characterData') {
                    processText(m.target as Text);
                } else if (m.type === 'attributes') {
                    processAttributes(m.target as Element);
                } else {
                    m.addedNodes.forEach(processTree);
                }
            }
        }),
    );
    observer.observe(document.body, {
        subtree: true,
        childList: true,
        characterData: true,
        attributes: true,
        attributeFilter: [...ATTRIBUTES],
    });
}

function storedLanguage(): Language {
    try {
        const value = localStorage.getItem(STORAGE_KEY);
        if (isLanguage(value)) {
            return value;
        }
    } catch {
        // kein Speicher: Standard
    }

    return 'de-CH';
}

export function setLanguage(language: Language) {
    current = language;
    try {
        localStorage.setItem(STORAGE_KEY, language);
    } catch {
        // ohne Speicher nur bis zum Neuladen
    }
    document.documentElement.lang = language === 'de-CH' ? 'de-CH' : language === 'en' ? 'en' : 'gsw-CH';
    buildTable(language);
    // Ganze Seite aus den Ausgangstexten neu übersetzen (zurück auf Deutsch ohne Neuladen)
    run(() => processTree(document.body));
    if (language !== 'de-CH') {
        startObserver();
    }
    listeners.forEach((listener) => listener());
}

/** Beim Start: Sprache dieses Browsers übernehmen. */
export function initializeLanguage() {
    if (typeof window === 'undefined') {
        return;
    }
    const language = storedLanguage();
    current = language;
    buildTable(language);
    document.documentElement.lang = language === 'de-CH' ? 'de-CH' : language === 'en' ? 'en' : 'gsw-CH';
    if (language !== 'de-CH') {
        const begin = () => {
            run(() => processTree(document.body));
            startObserver();
            document.documentElement.classList.remove('i18n-wait');
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', begin, { once: true });
        } else {
            begin();
        }
    }
}

export function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/** Anteil übersetzter Texte je Sprache (für die Sprachkonzept-Seite). */
export function coverage(): { total: number; byLanguage: Record<string, number> } {
    const all = Object.values(CATALOGS).flatMap((c) => Object.entries(c));
    const byLanguage: Record<string, number> = {};
    COLUMNS.forEach((language, i) => {
        byLanguage[language] = all.filter(([, translations]) => Boolean(translations[i])).length;
    });

    return { total: all.length, byLanguage };
}
