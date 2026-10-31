// i18n: nicht übersetzen (Hilfsfunktionen ohne sichtbare Texte)
/** Tabellen aus Antworten von Fachanwendungen (Verbindung zum Tab). */
// Werte mit solchen Schlüsseln nie anzeigen (Lizenzschlüssel, Tokens, Passwörter)
const HIDDEN = /key|token|pass|secret|pin|code/i;

/** Erste Liste von Objekten in einer Antwort als Tabelle (höchstens 10 Spalten, geheime Felder ausgeblendet). */
export function toTable(
    json: unknown,
): { columns: string[]; rows: string[][] } | null {
    const queue: unknown[] = [json];
    while (queue.length > 0) {
        const value = queue.shift();
        if (
            Array.isArray(value) &&
            value.length > 0 &&
            value.every((v) => v && typeof v === 'object' && !Array.isArray(v))
        ) {
            const columns = Array.from(
                new Set(value.flatMap((v) => Object.keys(v as object))),
            )
                .filter((c) => !HIDDEN.test(c))
                .filter((c) =>
                    value.some((v) =>
                        ['string', 'number', 'boolean'].includes(
                            typeof (v as Record<string, unknown>)[c],
                        ),
                    ),
                )
                .slice(0, 10);

            return {
                columns,
                rows: value
                    .slice(0, 500)
                    .map((v) =>
                        columns.map((c) =>
                            String((v as Record<string, unknown>)[c] ?? ''),
                        ),
                    ),
            };
        }
        if (value && typeof value === 'object') {
            queue.push(...Object.values(value as object));
        }
    }

    return null;
}

export function csv(columns: string[], rows: string[][]): string {
    const cell = (v: string) =>
        /[";\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v;

    return [columns, ...rows].map((r) => r.map(cell).join(';')).join('\n');
}
