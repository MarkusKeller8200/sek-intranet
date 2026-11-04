/**
 * Beispielwerte für die API-Tester (Plugins und eigene REST-API), nach dem Vorbild von TagStudio:
 * Parameter sind mit Beispielen vorausgefüllt, IDs aus der letzten Antwort werden übernommen, der Body lässt sich
 * aus «Mögliche Werte» zusammensetzen und wird laufend als JSON geprüft.
 */

/** Feld eines Bodys (plugin.json → endpoints[].fields). */
export type ApiField = {
    name: string;
    type?: string;
    required?: boolean;
    help?: string;
    example?: unknown;
    choices?: string[];
};

/** Beispielwert eines Felds: Beispiel, sonst erste Auswahl, sonst leerer Wert seines Typs. */
export function exampleOf(field: ApiField): unknown {
    if (field.example !== undefined && field.example !== null) {
        return field.example;
    }
    if (field.choices && field.choices.length > 0) {
        return field.choices[0];
    }
    switch (field.type) {
        case 'integer':
        case 'number':
            return 0;
        case 'boolean':
            return false;
        case 'array':
            return [];
        case 'object':
            return {};
        default:
            return '';
    }
}

/** Wert für die Anzeige in einer Liste (kurz, einzeilig). */
export function showValue(value: unknown): string {
    if (value === undefined || value === null) {
        return '';
    }

    return typeof value === 'string' ? value : JSON.stringify(value);
}

export type JsonState = { ok: boolean; empty: boolean; message: string };

/** Body prüfen: leer, gültiges JSON-Objekt oder Fehler mit Meldung. */
export function jsonState(text: string): JsonState {
    if (text.trim() === '') {
        return {
            ok: true,
            empty: true,
            message: 'Leer – es wird kein Inhalt gesendet.',
        };
    }
    try {
        const value: unknown = JSON.parse(text);
        // Objekt oder Liste (z. B. Reihenfolge als Liste von IDs)
        if (value === null || typeof value !== 'object') {
            return {
                ok: false,
                empty: false,
                message: 'Erwartet wird ein JSON-Objekt oder eine Liste.',
            };
        }

        return { ok: true, empty: false, message: 'Gültiges JSON.' };
    } catch (e) {
        return {
            ok: false,
            empty: false,
            message: `Kein gültiges JSON: ${e instanceof Error ? e.message : ''}`,
        };
    }
}

function parseObject(text: string): Record<string, unknown> | null {
    if (text.trim() === '') {
        return {};
    }
    try {
        const value: unknown = JSON.parse(text);

        return value !== null &&
            typeof value === 'object' &&
            !Array.isArray(value)
            ? (value as Record<string, unknown>)
            : null;
    } catch {
        return null;
    }
}

export function pretty(value: unknown): string {
    return JSON.stringify(value, null, 2);
}

/** Ein oder mehrere Felder mit Beispielwert in den Body einsetzen (bestehende Werte bleiben, ausser bei force). */
export function insertFields(
    text: string,
    fields: ApiField[],
    force = true,
): string | null {
    const body = parseObject(text);
    if (body === null) {
        return null;
    }
    for (const field of fields) {
        if (force || !(field.name in body)) {
            body[field.name] = exampleOf(field);
        }
    }

    return pretty(body);
}

/** Body neu einrücken; null, wenn er kein gültiges JSON ist. */
export function formatJson(text: string): string | null {
    if (text.trim() === '') {
        return '';
    }
    try {
        return pretty(JSON.parse(text));
    } catch {
        return null;
    }
}

/**
 * IDs aus einer Antwort sammeln: für jeden gesuchten Namen (z. B. documentId, signeeId, id) den ersten
 * einfachen Wert in der Antwort, auch verschachtelt (Listen, Unterobjekte).
 */
export function collectIds(
    json: unknown,
    names: string[],
): Record<string, string> {
    const found: Record<string, string> = {};
    const wanted = new Set(names);
    const walk = (value: unknown, depth: number) => {
        if (depth > 6 || value === null || typeof value !== 'object') {
            return;
        }
        if (Array.isArray(value)) {
            value.slice(0, 50).forEach((v) => walk(v, depth + 1));

            return;
        }
        for (const [key, v] of Object.entries(
            value as Record<string, unknown>,
        )) {
            if (
                wanted.has(key) &&
                !(key in found) &&
                (typeof v === 'string' || typeof v === 'number') &&
                String(v) !== ''
            ) {
                found[key] = String(v);
            }
        }
        for (const v of Object.values(value as Record<string, unknown>)) {
            walk(v, depth + 1);
        }
    };
    walk(json, 0);

    return found;
}

/** Text einer Antwort als JSON lesen (sonst null). */
export function parseJson(text: string | undefined | null): unknown {
    if (!text) {
        return null;
    }
    try {
        return JSON.parse(text) as unknown;
    } catch {
        return null;
    }
}
