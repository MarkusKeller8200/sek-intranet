/** Eine aufgezeichnete Anfrage mit Antwort (aus dem Lesezeichen-Skript oder einer HAR-Datei). */
export type Capture = {
    id: string;
    at: number;
    method: string;
    url: string;
    status: number;
    duration: number;
    requestHeaders: Record<string, string>;
    requestBody: string | null;
    responseHeaders: Record<string, string>;
    responseBody: string | null;
    /** fetch, xhr, document … (HAR: _resourceType) */
    kind: string;
};

/** Dokumentierter Endpunkt (Operation in OpenAPI). */
export type DocOperation = {
    key: string;
    method: string;
    path: string;
    summary: string;
    tag: string;
    status: number;
    query: Record<string, string>;
    requestType: string | null;
    requestSchema: JsonSchema | null;
    responseType: string | null;
    responseSchema: JsonSchema | null;
    /** Beispiel (maskiert) nur, wenn ausdrücklich gewünscht */
    requestExample: unknown;
    responseExample: unknown;
    samples: number;
};

export type JsonSchema = {
    type?: string | string[];
    format?: string;
    properties?: Record<string, JsonSchema>;
    items?: JsonSchema;
    required?: string[];
    nullable?: boolean;
};

export type ApiDoc = {
    id?: string;
    title: string;
    /** Adresse, die zum Aufzeichnen geöffnet wird */
    start_url?: string;
    base_url: string;
    /** Zugewiesenes Plugin (plugins/<Ordner>, Schlüssel «id») */
    plugin_id?: string | null;
    recorded_at?: string;
    description: string;
    operations: DocOperation[];
    updated_at?: string;
};

/** Installiertes Plugin mit seinen Endpunkten (für Zuweisung und Abgleich). */
export type PluginCatalogEntry = {
    id: string;
    name: string;
    endpoints: { id: string; method: string; path: string; label: string }[];
};

/** Gleicher Vergleich wie App\\Support\\ApiDocs::normalize(). */
export function normalizeEndpoint(method: string, path: string): string {
    const p = path.toLowerCase().replace(/\/+$/, '').replace(/\{[^}]*\}/g, '{}');

    return `${method.toUpperCase()} ${p === '' ? '/' : p}`;
}
