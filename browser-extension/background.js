// SBI-Connector – Hintergrund (Service Worker). Prototyp, siehe docs/KONZEPT-BROWSER-CONNECTOR.md.
//
// 1. Fachanwendungen im eigenen Tab öffnen, gruppiert mit dem Intranet-Tab («Intranet»).
// 2. Aufzeichnung für den API-Recorder: Im Tab einer Website startet das Symbol der Erweiterung die Aufnahme
//    (nur nach Freigabe der Website durch die Person). Die Anfragen gehen an offene API-Recorder-Seiten im Intranet.
//    Funktioniert auch auf Seiten, die Lesezeichen-Skripte blockieren (Content-Security-Policy).
// Keine Zugangsdaten werden gespeichert; der Zustand liegt nur in chrome.storage.session (bis der Browser schliesst).

const RECORDER_TYPE = 'sbi-api-recorder';
const MAX_BODY = 60000;

async function load() {
    const s = await chrome.storage.session.get(['recorders', 'recording']);

    return { recorders: s.recorders || [], recording: s.recording || {} };
}

async function save(state) {
    await chrome.storage.session.set(state);
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    handle(message, sender).then(sendResponse, (error) => sendResponse({ ok: false, error: String(error) }));

    return true;
});

async function handle(message, sender) {
    const state = await load();
    switch (message && message.kind) {
        case 'open': {
            if (!sender.tab || !/^https:\/\//.test(message.url)) {
                return { ok: false };
            }
            const tab = await chrome.tabs.create({ url: message.url, openerTabId: sender.tab.id, index: sender.tab.index + 1 });
            try {
                const groupId = sender.tab.groupId > -1 ? sender.tab.groupId : await chrome.tabs.group({ tabIds: [sender.tab.id] });
                await chrome.tabs.group({ groupId, tabIds: [tab.id] });
                await chrome.tabGroups.update(groupId, { title: 'Intranet', color: 'blue' });
            } catch {
                // Gruppen nicht verfügbar (z. B. anderer Browser): Tab bleibt ungruppiert
            }

            return { ok: true };
        }
        case 'recorder': {
            if (!sender.tab) {
                return { ok: false };
            }
            const id = sender.tab.id;
            state.recorders = state.recorders.filter((r) => r !== id);
            if (message.active) {
                state.recorders.push(id);
            }
            await save(state);

            return { ok: true };
        }
        case 'status':
            return { ok: true, recorders: state.recorders.length, recording: Boolean(state.recording[message.tabId]) };
        case 'start': {
            state.recording[message.tabId] = message.origin;
            await save(state);
            await inject(message.tabId);

            return { ok: true };
        }
        case 'stop': {
            delete state.recording[message.tabId];
            await save(state);

            return { ok: true };
        }
        case 'capture': {
            // Nur aus Tabs, deren Aufnahme die Person gestartet hat
            if (!sender.tab || !state.recording[sender.tab.id]) {
                return { ok: false };
            }
            await forward(state, { kind: 'capture', entry: message.entry });

            return { ok: true };
        }
        default:
            return { ok: false };
    }
}

async function forward(state, message) {
    for (const id of state.recorders) {
        try {
            await chrome.tabs.sendMessage(id, message);
        } catch {
            // Recorder-Tab geschlossen oder neu geladen
        }
    }
}

// Nach dem Neuladen einer aufgezeichneten Seite die Aufnahme wieder einschalten
chrome.tabs.onUpdated.addListener(async (tabId, info, tab) => {
    if (info.status !== 'complete') {
        return;
    }
    const state = await load();
    const origin = state.recording[tabId];
    if (origin && tab.url && new URL(tab.url).origin === origin) {
        await inject(tabId);
    }
});

chrome.tabs.onRemoved.addListener(async (tabId) => {
    const state = await load();
    state.recorders = state.recorders.filter((r) => r !== tabId);
    delete state.recording[tabId];
    await save(state);
});

async function inject(tabId) {
    await chrome.scripting.executeScript({ target: { tabId }, world: 'ISOLATED', func: relay });
    await chrome.scripting.executeScript({ target: { tabId }, world: 'MAIN', func: capture, args: [MAX_BODY] });
    const tab = await chrome.tabs.get(tabId);
    const state = await load();
    if (tab.url) {
        await forward(state, { kind: 'hello', url: tab.url });
    }
}

// Läuft in der aufgezeichneten Seite (isolierte Welt): leitet Meldungen der Seite an die Erweiterung weiter.
function relay() {
    if (window.__sbiRelay) {
        return;
    }
    window.__sbiRelay = true;
    window.addEventListener('message', (event) => {
        if (event.source !== window || event.origin !== location.origin) {
            return;
        }
        const data = event.data;
        if (data && data.__sbiCapture === true && data.entry) {
            chrome.runtime.sendMessage({ kind: 'capture', entry: data.entry });
        }
    });
}

// Läuft in der aufgezeichneten Seite (Hauptwelt): hängt sich an fetch und XMLHttpRequest.
// Gleiche Erfassung wie das Lesezeichen (resources/js/lib/api-recorder/capture.ts) – bei Änderungen beide nachführen.
function capture(MAX) {
    if (window.__sbiConnectorCapture) {
        return;
    }
    window.__sbiConnectorCapture = true;
    let n = 0;
    const cut = (s) => (typeof s === 'string' ? (s.length > MAX ? s.slice(0, MAX) : s) : s == null ? null : '[binär]');
    const badge = document.createElement('div');
    badge.textContent = '● SBI-Connector · 0';
    badge.title = 'Aufnahme läuft – Anfragen gehen an den API-Recorder im Intranet';
    badge.setAttribute('style', 'position:fixed;z-index:2147483647;right:12px;bottom:12px;padding:8px 12px;border-radius:999px;background:#c81e1e;color:#fff;font:600 13px system-ui,sans-serif;box-shadow:0 2px 10px rgba(0,0,0,.25);pointer-events:none');
    (document.body || document.documentElement).appendChild(badge);
    const send = (entry) => {
        n++;
        entry.id = Date.now() + '-' + n;
        entry.at = Date.now();
        badge.textContent = '● SBI-Connector · ' + n;
        window.postMessage({ __sbiCapture: true, entry }, location.origin);
    };
    const headersOf = (h) => {
        const out = {};
        h.forEach((v, k) => {
            out[k] = v;
        });

        return out;
    };
    const reqHeaders = (h) => (!h ? {} : h instanceof Headers ? headersOf(h) : Array.isArray(h) ? Object.fromEntries(h) : { ...h });

    const origFetch = window.fetch;
    window.fetch = async function (input, init) {
        const started = performance.now();
        const req = input instanceof Request ? input : null;
        const raw = req ? req.url : typeof input === 'string' ? input : input instanceof URL ? input.href : '';
        const url = new URL(raw, location.href).href;
        const method = ((init && init.method) || (req && req.method) || 'GET').toUpperCase();
        const bodyIn = (init && init.body) || null;
        const response = await origFetch.apply(this, [input, init]);
        response
            .clone()
            .text()
            .then((text) =>
                send({
                    method,
                    url,
                    status: response.status,
                    duration: Math.round(performance.now() - started),
                    requestHeaders: { ...(req ? headersOf(req.headers) : {}), ...reqHeaders(init && init.headers) },
                    requestBody: cut(bodyIn),
                    responseHeaders: headersOf(response.headers),
                    responseBody: cut(text),
                    kind: 'fetch',
                }),
            )
            .catch(() => undefined);

        return response;
    };

    const proto = XMLHttpRequest.prototype;
    const open = proto.open;
    const sendXhr = proto.send;
    const setHeader = proto.setRequestHeader;
    const meta = new WeakMap();
    proto.open = function (method, url, ...rest) {
        meta.set(this, { m: String(method).toUpperCase(), u: new URL(String(url), location.href).href, h: {}, t: 0 });

        return open.apply(this, [method, url, ...rest]);
    };
    proto.setRequestHeader = function (k, v) {
        const m = meta.get(this);
        if (m) {
            m.h[k] = v;
        }

        return setHeader.call(this, k, v);
    };
    proto.send = function (data) {
        const m = meta.get(this);
        if (m) {
            m.t = performance.now();
            this.addEventListener('loadend', () => {
                const headers = {};
                this.getAllResponseHeaders()
                    .trim()
                    .split(/[\r\n]+/)
                    .forEach((line) => {
                        const i = line.indexOf(':');
                        if (i > 0) {
                            headers[line.slice(0, i).trim().toLowerCase()] = line.slice(i + 1).trim();
                        }
                    });
                let text = null;
                try {
                    text = this.responseType === '' || this.responseType === 'text' ? this.responseText : this.responseType === 'json' ? JSON.stringify(this.response) : null;
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
}
