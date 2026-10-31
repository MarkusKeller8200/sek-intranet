// i18n: nicht übersetzen (läuft in der aufgezeichneten Website bzw. erzeugt die OpenAPI-Datei)
/**
 * API-Recorder: aus aufgezeichneten Anfragen Endpunkte, Schemas und eine OpenAPI-3.1-Datei
 * (Swagger) ableiten. Läuft nur im Browser.
 */
import type { ApiDoc, Capture, DocOperation, JsonSchema } from './types';

const ID_SEGMENT =
    /^(?:\d+|[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}|[0-9a-f]{16,}|[0-9A-Za-z_-]{20,})$/i;

const STATIC =
    /\.(?:js|mjs|css|map|png|jpe?g|gif|svg|webp|avif|ico|woff2?|ttf|otf|eot|mp4|webm|mp3|wav|pdf)(?:$|\?)/i;

/** Ressourcen, die keine API sind (Skripte, Bilder, Schriften, Analytics). */
export function isNoise(capture: Capture): boolean {
    let url: URL;
    try {
        url = new URL(capture.url);
    } catch {
        return true;
    }
    if (!/^https?:$/.test(url.protocol) || STATIC.test(url.pathname)) {
        return true;
    }
    const type = header(capture.responseHeaders, 'content-type');
    if (
        /^(?:image|font|video|audio)\//.test(type) ||
        /javascript|text\/css/.test(type)
    ) {
        return true;
    }

    return /google-analytics|googletagmanager|doubleclick|hotjar|sentry\.io|clarity\.ms|matomo/.test(
        url.hostname,
    );
}

export function header(headers: Record<string, string>, name: string): string {
    const key = Object.keys(headers).find((k) => k.toLowerCase() === name);

    return key ? headers[key] : '';
}

/** Pfad mit Platzhaltern: Zahlen, UUIDs und lange IDs werden zu {id}, {id2} … */
export function templatePath(pathname: string): string {
    let n = 0;

    return (
        pathname
            .split('/')
            .map((segment) => {
                if (
                    segment !== '' &&
                    ID_SEGMENT.test(decodeURIComponent(segment))
                ) {
                    n++;

                    return n === 1 ? '{id}' : `{id${n}}`;
                }

                return segment;
            })
            .join('/') || '/'
    );
}

export function operationKey(method: string, url: string): string {
    const u = new URL(url);

    return `${method.toUpperCase()} ${u.origin}${templatePath(u.pathname)}`;
}

function parseBody(body: string | null, type: string): unknown {
    if (body === null || body === '') {
        return undefined;
    }
    if (/json/.test(type) || /^\s*[[{]/.test(body)) {
        try {
            return JSON.parse(body) as unknown;
        } catch {
            return undefined;
        }
    }
    if (/x-www-form-urlencoded/.test(type)) {
        return Object.fromEntries(new URLSearchParams(body));
    }

    return undefined;
}

const FORMATS: [RegExp, string][] = [
    [/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/, 'date-time'],
    [/^\d{4}-\d{2}-\d{2}$/, 'date'],
    [/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i, 'email'],
    [/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i, 'uuid'],
    [/^https?:\/\//, 'uri'],
];

/** JSON-Schema aus einem Beispielwert. */
export function inferSchema(value: unknown, depth = 0): JsonSchema {
    if (value === null) {
        return { nullable: true };
    }
    if (Array.isArray(value)) {
        const items = value
            .slice(0, 20)
            .reduce<JsonSchema | null>(
                (acc, v) =>
                    acc
                        ? mergeSchema(acc, inferSchema(v, depth + 1))
                        : inferSchema(v, depth + 1),
                null,
            );

        return { type: 'array', items: items ?? {} };
    }
    switch (typeof value) {
        case 'boolean':
            return { type: 'boolean' };
        case 'number':
            return { type: Number.isInteger(value) ? 'integer' : 'number' };
        case 'string': {
            const format = FORMATS.find(([re]) => re.test(value))?.[1];

            return format ? { type: 'string', format } : { type: 'string' };
        }
        case 'object': {
            if (depth > 8) {
                return { type: 'object' };
            }
            const properties: Record<string, JsonSchema> = {};
            for (const [k, v] of Object.entries(
                value as Record<string, unknown>,
            )) {
                properties[k] = inferSchema(v, depth + 1);
            }

            return {
                type: 'object',
                properties,
                required: Object.keys(properties),
            };
        }
        default:
            return {};
    }
}

/** Zwei Schemas zusammenführen (mehrere Aufzeichnungen desselben Endpunkts). */
export function mergeSchema(a: JsonSchema, b: JsonSchema): JsonSchema {
    if (a.nullable && !a.type) {
        return { ...b, nullable: true };
    }
    if (b.nullable && !b.type) {
        return { ...a, nullable: true };
    }
    if (a.type !== b.type) {
        // integer und number → number
        if ([a.type, b.type].every((t) => t === 'integer' || t === 'number')) {
            return { type: 'number' };
        }

        return a.type ? a : b;
    }
    if (a.type === 'object') {
        const properties: Record<string, JsonSchema> = { ...a.properties };
        for (const [k, v] of Object.entries(b.properties ?? {})) {
            properties[k] = properties[k] ? mergeSchema(properties[k], v) : v;
        }
        const required = (a.required ?? []).filter((k) =>
            (b.required ?? []).includes(k),
        );

        return {
            type: 'object',
            properties,
            required,
            nullable: a.nullable || b.nullable || undefined,
        };
    }
    if (a.type === 'array') {
        return {
            type: 'array',
            items: mergeSchema(a.items ?? {}, b.items ?? {}),
        };
    }

    return {
        ...a,
        format: a.format === b.format ? a.format : undefined,
        nullable: a.nullable || b.nullable || undefined,
    };
}

const SECRET_KEY =
    /pass(?:word|wort)?|secret|token|auth|session|cookie|key|pin|iban|ahv/i;

/** Beispielwerte maskieren: Geheimnisse ganz, Texte bis auf Typhinweis. */
export function mask(value: unknown, key = ''): unknown {
    if (Array.isArray(value)) {
        return value.slice(0, 2).map((v) => mask(v, key));
    }
    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value as Record<string, unknown>).map(([k, v]) => [
                k,
                mask(v, k),
            ]),
        );
    }
    if (typeof value === 'string') {
        if (SECRET_KEY.test(key)) {
            return '***';
        }
        const format = FORMATS.find(([re]) => re.test(value))?.[1];
        if (format === 'email') {
            return 'name@example.org';
        }
        if (format) {
            return value; // Datum, UUID, URL sind unkritisch
        }

        return value.length <= 3 ? value : `${value.slice(0, 1)}…`;
    }

    return value;
}

/** Anfrage-Kopfzeilen ohne Geheimnisse (für Anzeige). */
export function safeHeaders(
    headers: Record<string, string>,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(headers).map(([k, v]) => [
            k,
            /authorization|cookie|token|api-key|x-csrf|x-xsrf/i.test(k)
                ? '***'
                : v,
        ]),
    );
}

/** Neue Operation aus einer Aufzeichnung. */
export function operationFrom(
    capture: Capture,
    withExamples: boolean,
): DocOperation {
    const url = new URL(capture.url);
    const requestType =
        header(capture.requestHeaders, 'content-type').split(';')[0] || null;
    const responseType =
        header(capture.responseHeaders, 'content-type').split(';')[0] || null;
    const request = parseBody(capture.requestBody, requestType ?? '');
    const response = parseBody(capture.responseBody, responseType ?? '');
    const segments = url.pathname.split('/').filter(Boolean);
    const tag =
        segments.find(
            (s) => !/^(?:api|v\d+)$/i.test(s) && !ID_SEGMENT.test(s),
        ) ?? 'allgemein';

    return {
        key: operationKey(capture.method, capture.url),
        method: capture.method.toUpperCase(),
        path: templatePath(url.pathname),
        summary: '',
        tag,
        status: capture.status,
        query: Object.fromEntries(
            [...url.searchParams.keys()].map((k) => [
                k,
                withExamples ? String(mask(url.searchParams.get(k), k)) : '',
            ]),
        ),
        requestType: request === undefined ? null : requestType,
        requestSchema: request === undefined ? null : inferSchema(request),
        responseType,
        responseSchema: response === undefined ? null : inferSchema(response),
        requestExample:
            withExamples && request !== undefined ? mask(request) : null,
        responseExample:
            withExamples && response !== undefined ? mask(response) : null,
        samples: 1,
    };
}

/** Weitere Aufzeichnung in eine bestehende Operation einrechnen (Schemas zusammenführen). */
export function mergeOperation(
    op: DocOperation,
    capture: Capture,
): DocOperation {
    const next = operationFrom(capture, false);

    return {
        ...op,
        query: { ...next.query, ...op.query },
        requestType: op.requestType ?? next.requestType,
        requestSchema:
            op.requestSchema && next.requestSchema
                ? mergeSchema(op.requestSchema, next.requestSchema)
                : (op.requestSchema ?? next.requestSchema),
        responseSchema:
            op.responseSchema && next.responseSchema
                ? mergeSchema(op.responseSchema, next.responseSchema)
                : (op.responseSchema ?? next.responseSchema),
        samples: op.samples + 1,
    };
}

function cleanSchema(schema: JsonSchema | null): Record<string, unknown> {
    if (!schema) {
        return {};
    }
    const out: Record<string, unknown> = {};
    if (schema.type) {
        out.type = schema.nullable ? [schema.type, 'null'].flat() : schema.type;
    } else if (schema.nullable) {
        out.type = 'null';
    }
    if (schema.format) {
        out.format = schema.format;
    }
    if (schema.properties) {
        out.properties = Object.fromEntries(
            Object.entries(schema.properties).map(([k, v]) => [
                k,
                cleanSchema(v),
            ]),
        );
    }
    if (schema.required && schema.required.length > 0) {
        out.required = schema.required;
    }
    if (schema.items) {
        out.items = cleanSchema(schema.items);
    }

    return out;
}

/** OpenAPI 3.1 (Swagger) aus der Dokumentation. */
export function buildOpenApi(doc: ApiDoc): Record<string, unknown> {
    const servers = new Map<string, true>();
    const paths: Record<string, Record<string, unknown>> = {};

    for (const op of doc.operations) {
        const [, full] = op.key.split(' ');
        const origin = new URL(full.replace(/\{[^}]+\}/g, 'x')).origin;
        servers.set(origin, true);
        const params: Record<string, unknown>[] = [];
        for (const m of op.path.matchAll(/\{([^}]+)\}/g)) {
            params.push({
                name: m[1],
                in: 'path',
                required: true,
                schema: { type: 'string' },
            });
        }
        for (const [name, example] of Object.entries(op.query)) {
            params.push({
                name,
                in: 'query',
                required: false,
                schema: { type: 'string' },
                ...(example ? { example } : {}),
            });
        }
        const operation: Record<string, unknown> = {
            tags: [op.tag],
            summary: op.summary || `${op.method} ${op.path}`,
            operationId: `${op.method.toLowerCase()}${op.path.replace(/\{([^}]+)\}/g, 'By_$1').replace(/[^A-Za-z0-9]+(.)?/g, (_, c: string | undefined) => (c ? c.toUpperCase() : ''))}`,
            ...(params.length > 0 ? { parameters: params } : {}),
            responses: {
                [String(op.status || 200)]: {
                    description: op.status >= 400 ? 'Fehler' : 'OK',
                    ...(op.responseSchema && op.responseType
                        ? {
                              content: {
                                  [op.responseType]: {
                                      schema: cleanSchema(op.responseSchema),
                                      ...(op.responseExample !== null &&
                                      op.responseExample !== undefined
                                          ? { example: op.responseExample }
                                          : {}),
                                  },
                              },
                          }
                        : {}),
                },
            },
        };
        if (op.requestSchema && op.requestType) {
            operation.requestBody = {
                required: true,
                content: {
                    [op.requestType]: {
                        schema: cleanSchema(op.requestSchema),
                        ...(op.requestExample !== null &&
                        op.requestExample !== undefined
                            ? { example: op.requestExample }
                            : {}),
                    },
                },
            };
        }
        paths[op.path] ??= {};
        paths[op.path][op.method.toLowerCase()] = operation;
    }

    const base = doc.base_url.trim();

    return {
        openapi: '3.1.0',
        info: {
            title: doc.title || 'Aufgezeichnete API',
            version: new Date().toISOString().slice(0, 10),
            description:
                (doc.description ? `${doc.description}\n\n` : '') +
                'Erstellt mit dem API-Recorder des SekBülach-Intranets aus aufgezeichneten Anfragen. Schemas sind aus Beispielen abgeleitet und sollten geprüft werden.',
        },
        servers: (base ? [base] : [...servers.keys()]).map((url) => ({ url })),
        tags: [...new Set(doc.operations.map((o) => o.tag))].map((name) => ({
            name,
        })),
        paths: Object.fromEntries(
            Object.entries(paths).sort(([a], [b]) => a.localeCompare(b)),
        ),
    };
}

/** Einfache YAML-Ausgabe (für Swagger Editor). */
export function toYaml(value: unknown, indent = 0): string {
    const pad = '  '.repeat(indent);
    const scalar = (v: unknown): string => {
        if (v === null || v === undefined) {
            return 'null';
        }
        if (typeof v === 'string') {
            return v === '' ||
                /^[\s\d-]|[:#{}[\],&*?|>'"%@`]|\s$|^(?:true|false|null|yes|no)$/i.test(
                    v,
                ) ||
                v.includes('\n')
                ? JSON.stringify(v)
                : v;
        }

        return String(v);
    };
    if (Array.isArray(value)) {
        if (value.length === 0) {
            return '[]';
        }

        return value
            .map((v) =>
                v && typeof v === 'object'
                    ? `${pad}-\n${toYaml(v, indent + 1)}`
                    : `${pad}- ${scalar(v)}`,
            )
            .join('\n');
    }
    if (value && typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);
        if (entries.length === 0) {
            return `${pad}{}`;
        }

        return entries
            .map(([k, v]) => {
                const key = /^[A-Za-z_][A-Za-z0-9_.-]*$/.test(k)
                    ? k
                    : JSON.stringify(k);
                if (
                    v &&
                    typeof v === 'object' &&
                    (Array.isArray(v)
                        ? v.length > 0
                        : Object.keys(v).length > 0)
                ) {
                    return `${pad}${key}:\n${toYaml(v, indent + 1)}`;
                }

                return `${pad}${key}: ${Array.isArray(v) ? '[]' : v && typeof v === 'object' ? '{}' : scalar(v)}`;
            })
            .join('\n');
    }

    return pad + scalar(value);
}
