// i18n: nicht übersetzen (läuft in der aufgezeichneten Website bzw. erzeugt die OpenAPI-Datei)
/**
 * Aufzeichnung: Lesezeichen-Skript (läuft in der aufgezeichneten Website) und HAR-Import.
 * Das Skript meldet jede fetch-/XHR-Anfrage mit Antwort per postMessage an das Recorder-Fenster
 * (window.opener). Ohne Opener sammelt es und bietet «Kopieren» an.
 */
import type { Capture } from './types';

export const MESSAGE_TYPE = 'sbi-api-recorder';

const MAX_BODY = 60_000;

/**
 * Quelltext des Lesezeichens. `origin` = Adresse des Intranets (Ziel der Meldungen).
 * Bewusst ohne Abhängigkeiten und als ES5-nahe Funktion, damit es in jeder Seite läuft.
 */
export function bookmarkletSource(origin: string): string {
    const body = function (ORIGIN: string, TYPE: string, MAX: number) {
        const w = window as unknown as Record<string, unknown>;
        if (w.__sbiRecorder) {
            (w.__sbiRecorder as { show: () => void }).show();

            return;
        }
        const list: unknown[] = [];
        let n = 0;
        const cut = (s: unknown): string | null =>
            typeof s === 'string'
                ? s.length > MAX
                    ? s.slice(0, MAX)
                    : s
                : s == null
                  ? null
                  : '[binär]';
        const send = (entry: Record<string, unknown>) => {
            n++;
            entry.id = `${Date.now()}-${n}`;
            entry.at = Date.now();
            list.push(entry);
            badge.textContent = `● SBI-Recorder · ${n}`;
            try {
                if (window.opener) {
                    (window.opener as Window).postMessage(
                        { type: TYPE, entry },
                        ORIGIN,
                    );
                }
            } catch {
                /* Opener nicht erreichbar */
            }
        };
        const headersOf = (h: Headers): Record<string, string> => {
            const out: Record<string, string> = {};
            h.forEach((v, k) => {
                out[k] = v;
            });

            return out;
        };
        const reqHeaders = (
            h: HeadersInit | undefined,
        ): Record<string, string> => {
            if (!h) {
                return {};
            }
            if (h instanceof Headers) {
                return headersOf(h);
            }
            if (Array.isArray(h)) {
                return Object.fromEntries(h);
            }

            return { ...(h as Record<string, string>) };
        };

        // fetch
        const origFetch = window.fetch;
        window.fetch = async function (
            input: RequestInfo | URL,
            init?: RequestInit,
        ) {
            const started = performance.now();
            const req = input instanceof Request ? input : null;
            const url = new URL(req ? req.url : String(input), location.href)
                .href;
            const method = (init?.method ?? req?.method ?? 'GET').toUpperCase();
            const bodyIn = init?.body ?? null;
            const response = await origFetch.apply(this, [input, init] as [
                RequestInfo | URL,
                RequestInit | undefined,
            ]);
            const copy = response.clone();
            copy.text()
                .then((text) =>
                    send({
                        method,
                        url,
                        status: response.status,
                        duration: Math.round(performance.now() - started),
                        requestHeaders: {
                            ...(req ? headersOf(req.headers) : {}),
                            ...reqHeaders(init?.headers),
                        },
                        requestBody: cut(bodyIn),
                        responseHeaders: headersOf(response.headers),
                        responseBody: cut(text),
                        kind: 'fetch',
                    }),
                )
                .catch(() => undefined);

            return response;
        };

        // XMLHttpRequest
        const open = XMLHttpRequest.prototype.open;
        const sendXhr = XMLHttpRequest.prototype.send;
        const setHeader = XMLHttpRequest.prototype.setRequestHeader;
        type Meta = {
            m: string;
            u: string;
            h: Record<string, string>;
            t: number;
        };
        const meta = new WeakMap<XMLHttpRequest, Meta>();
        XMLHttpRequest.prototype.open = function (
            this: XMLHttpRequest,
            method: string,
            url: string | URL,
            ...rest: unknown[]
        ) {
            meta.set(this, {
                m: method.toUpperCase(),
                u: new URL(String(url), location.href).href,
                h: {},
                t: 0,
            });

            return (open as (...a: unknown[]) => void).apply(this, [
                method,
                url,
                ...rest,
            ]);
        } as typeof XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.setRequestHeader = function (
            this: XMLHttpRequest,
            k: string,
            v: string,
        ) {
            const m = meta.get(this);
            if (m) {
                m.h[k] = v;
            }

            return setHeader.call(this, k, v);
        };
        XMLHttpRequest.prototype.send = function (
            this: XMLHttpRequest,
            data?: Document | XMLHttpRequestBodyInit | null,
        ) {
            const m = meta.get(this);
            if (m) {
                m.t = performance.now();
                this.addEventListener('loadend', () => {
                    const headers: Record<string, string> = {};
                    this.getAllResponseHeaders()
                        .trim()
                        .split(/[\r\n]+/)
                        .forEach((line) => {
                            const i = line.indexOf(':');
                            if (i > 0) {
                                headers[line.slice(0, i).trim().toLowerCase()] =
                                    line.slice(i + 1).trim();
                            }
                        });
                    let text: string | null = null;
                    try {
                        text =
                            this.responseType === '' ||
                            this.responseType === 'text'
                                ? this.responseText
                                : this.responseType === 'json'
                                  ? JSON.stringify(this.response)
                                  : null;
                    } catch {
                        text = null;
                    }
                    send({
                        method: m.m,
                        url: m.u,
                        status: this.status,
                        duration: Math.round(performance.now() - m.t),
                        requestHeaders: m.h,
                        requestBody: cut(data),
                        responseHeaders: headers,
                        responseBody: cut(text),
                        kind: 'xhr',
                    });
                });
            }

            return sendXhr.call(this, data);
        };

        // Anzeige in der Seite
        const badge = document.createElement('button');
        badge.type = 'button';
        badge.textContent = '● SBI-Recorder · 0';
        badge.title = window.opener
            ? 'Aufnahme läuft – Anfragen gehen an den API-Recorder im Intranet'
            : 'Aufnahme läuft – klicken zum Kopieren (kein Recorder-Fenster verbunden)';
        badge.setAttribute(
            'style',
            'position:fixed;z-index:2147483647;right:12px;bottom:12px;padding:8px 12px;border-radius:999px;border:0;background:#c81e1e;color:#fff;font:600 13px system-ui,sans-serif;box-shadow:0 2px 10px rgba(0,0,0,.25);cursor:pointer',
        );
        badge.onclick = () => {
            if (!window.opener) {
                void navigator.clipboard
                    .writeText(JSON.stringify(list))
                    .then(() => {
                        badge.textContent = `✓ ${list.length} kopiert`;
                    });
            }
        };
        document.body.appendChild(badge);
        w.__sbiRecorder = {
            show: () => document.body.appendChild(badge),
            list,
        };
        try {
            if (window.opener) {
                (window.opener as Window).postMessage(
                    { type: TYPE, hello: location.href },
                    ORIGIN,
                );
            }
        } catch {
            /* egal */
        }
    };

    return `javascript:(${body.toString()})(${JSON.stringify(origin)},${JSON.stringify(MESSAGE_TYPE)},${MAX_BODY});void 0`;
}

type HarEntry = {
    startedDateTime?: string;
    time?: number;
    _resourceType?: string;
    request?: {
        method?: string;
        url?: string;
        headers?: { name: string; value: string }[];
        postData?: { mimeType?: string; text?: string };
    };
    response?: {
        status?: number;
        headers?: { name: string; value: string }[];
        content?: { mimeType?: string; text?: string; encoding?: string };
    };
};

const toRecord = (
    list: { name: string; value: string }[] | undefined,
): Record<string, string> =>
    Object.fromEntries(
        (list ?? []).map((h) => [h.name.toLowerCase(), h.value]),
    );

/** HAR-Datei (DevTools → Netzwerk → «Alle als HAR speichern») in Aufzeichnungen umwandeln. */
export function parseHar(text: string): Capture[] {
    const json = JSON.parse(text) as { log?: { entries?: HarEntry[] } };
    const entries = json.log?.entries;
    if (!Array.isArray(entries)) {
        throw new Error('Keine gültige HAR-Datei (log.entries fehlt).');
    }

    return entries
        .filter((e) => e.request?.url && e.request.method)
        .map((e, i) => {
            const content = e.response?.content;
            let body = content?.text ?? null;
            if (body && content?.encoding === 'base64') {
                try {
                    body = atob(body);
                } catch {
                    body = null;
                }
            }
            const responseHeaders = toRecord(e.response?.headers);
            if (content?.mimeType && !responseHeaders['content-type']) {
                responseHeaders['content-type'] = content.mimeType;
            }
            const requestHeaders = toRecord(e.request?.headers);
            if (
                e.request?.postData?.mimeType &&
                !requestHeaders['content-type']
            ) {
                requestHeaders['content-type'] = e.request.postData.mimeType;
            }

            return {
                id: `har-${i}`,
                at: e.startedDateTime ? Date.parse(e.startedDateTime) : i,
                method: (e.request?.method ?? 'GET').toUpperCase(),
                url: e.request?.url ?? '',
                status: e.response?.status ?? 0,
                duration: Math.round(e.time ?? 0),
                requestHeaders,
                requestBody:
                    e.request?.postData?.text?.slice(0, MAX_BODY) ?? null,
                responseHeaders,
                responseBody: body ? body.slice(0, MAX_BODY) : null,
                kind: e._resourceType ?? 'har',
            };
        });
}

/** Meldung aus dem Lesezeichen-Skript prüfen und in eine Aufzeichnung umwandeln. */
export function captureFromMessage(data: unknown): Capture | null {
    if (
        !data ||
        typeof data !== 'object' ||
        (data as { type?: unknown }).type !== MESSAGE_TYPE
    ) {
        return null;
    }
    const e = (data as { entry?: Record<string, unknown> }).entry;
    if (!e || typeof e.url !== 'string' || typeof e.method !== 'string') {
        return null;
    }
    const str = (v: unknown) => (typeof v === 'string' ? v : null);
    const rec = (v: unknown): Record<string, string> =>
        v && typeof v === 'object'
            ? Object.fromEntries(
                  Object.entries(v as Record<string, unknown>).map(([k, x]) => [
                      k.toLowerCase(),
                      String(x),
                  ]),
              )
            : {};

    return {
        id: String(e.id ?? Math.random()),
        at: typeof e.at === 'number' ? e.at : Date.now(),
        method: e.method.toUpperCase(),
        url: e.url,
        status: typeof e.status === 'number' ? e.status : 0,
        duration: typeof e.duration === 'number' ? e.duration : 0,
        requestHeaders: rec(e.requestHeaders),
        requestBody: str(e.requestBody),
        responseHeaders: rec(e.responseHeaders),
        responseBody: str(e.responseBody),
        kind: str(e.kind) ?? 'fetch',
    };
}
