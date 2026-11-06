// SBI-Connector – Hintergrund (Service Worker). Prototyp, siehe docs/KONZEPT-BROWSER-CONNECTOR.md.
//
// 1. Fachanwendungen im eigenen Tab öffnen, gruppiert mit dem Intranet-Tab («Intranet»).
// 2. Aufzeichnung für den API-Recorder: Im Tab einer Website startet das Symbol der Erweiterung die Aufnahme
//    (nur nach Freigabe der Website durch die Person). Die Anfragen gehen an offene API-Recorder-Seiten im Intranet.
//    Funktioniert auch auf Seiten, die Lesezeichen-Skripte blockieren (Content-Security-Policy).
// 3. Verbindung Intranet ⇄ Tab einer Fachanwendung: Kontext abfragen, Seitenwechsel melden, Daten über die
//    bestehende Anmeldung im Tab abrufen (nur lesend, nur gleiche Adresse), Tab steuern (Seite, Vordergrund,
//    Feld ausfüllen – nie absenden), Tabellen auslesen.
// Keine Zugangsdaten werden gespeichert; der Zustand liegt nur in chrome.storage.session (bis der Browser schliesst).

const RECORDER_TYPE = 'sbi-api-recorder';
const MAX_BODY = 60000;

async function load() {
    const s = await chrome.storage.session.get([
        'recorders',
        'recording',
        'apps',
    ]);

    return {
        recorders: s.recorders || [],
        recording: s.recording || {},
        // Fachanwendungs-Tabs: Schlüssel → { tabId, origin, intranetTab }
        apps: s.apps || {},
    };
}

const INTRANET_ORIGIN = chrome.runtime
    .getManifest()
    .host_permissions[0].replace('/*', '');

// Seiten-Hook (merkt die Anmeldung der Seite, beantwortet Abrufe) auf allen freigegebenen Websites ausser dem Intranet
async function registerHooks() {
    const { origins = [] } = await chrome.permissions.getAll();
    const matches = origins.filter(
        (o) =>
            o.startsWith('https://') &&
            !o.startsWith(INTRANET_ORIGIN) &&
            o !== 'https://*/*',
    );
    try {
        await chrome.scripting.unregisterContentScripts({
            ids: ['sbi-page-hook', 'sbi-page-relay'],
        });
    } catch {
        // noch nicht registriert
    }
    if (matches.length === 0) {
        return;
    }
    await chrome.scripting.registerContentScripts([
        {
            id: 'sbi-page-hook',
            matches,
            js: ['page-hook.js'],
            world: 'MAIN',
            runAt: 'document_start',
            persistAcrossSessions: true,
        },
        {
            id: 'sbi-page-relay',
            matches,
            js: ['page-relay.js'],
            runAt: 'document_start',
            persistAcrossSessions: true,
        },
    ]);
}
chrome.runtime.onInstalled.addListener(() => void registerHooks());
chrome.runtime.onStartup.addListener(() => void registerHooks());
chrome.permissions.onAdded.addListener(() => void registerHooks());
chrome.permissions.onRemoved.addListener(() => void registerHooks());

/** Offener Tab einer Fachanwendung oder null. */
async function appTab(state, key) {
    const app = state.apps[key];
    if (!app) {
        return null;
    }
    try {
        return await chrome.tabs.get(app.tabId);
    } catch {
        delete state.apps[key];
        await save(state);

        return null;
    }
}

/**
 * Intranet-Tab und Fachanwendung in der geteilten Ansicht des Browsers (#146). Null, wenn der Browser die API nicht
 * kennt oder sie scheitert (angeheftet, Gruppe, anderes Fenster) – dann zwei Fenster wie bisher.
 */
async function splitView(state, intranetTab, message) {
    if (typeof chrome.tabs.createSplit !== 'function' || intranetTab.pinned) {
        return null;
    }
    try {
        // eine bestehende geteilte Ansicht des Intranet-Tabs zuerst auflösen
        if (
            typeof intranetTab.splitViewId === 'number' &&
            intranetTab.splitViewId > -1 &&
            typeof chrome.tabs.unsplit === 'function'
        ) {
            await chrome.tabs.unsplit(intranetTab.splitViewId);
        }
        const existing = message.key ? await appTab(state, message.key) : null;
        let tabId;
        if (existing && existing.id !== intranetTab.id) {
            if (existing.windowId !== intranetTab.windowId) {
                await chrome.tabs.move(existing.id, {
                    windowId: intranetTab.windowId,
                    index: intranetTab.index + 1,
                });
            } else {
                await chrome.tabs.move(existing.id, {
                    index: intranetTab.index + 1,
                });
            }
            if (
                typeof existing.splitViewId === 'number' &&
                existing.splitViewId > -1
            ) {
                await chrome.tabs.unsplit(existing.splitViewId);
            }
            await chrome.tabs.createSplit([intranetTab.id, existing.id]);
            tabId = existing.id;
        } else {
            const tab = await chrome.tabs.create({
                url: message.url,
                splitWithTabId: intranetTab.id,
            });
            tabId = tab.id;
        }
        if (message.key) {
            state.apps[message.key] = {
                tabId,
                origin: new URL(message.url).origin,
                intranetTab: intranetTab.id,
            };
            await save(state);
        }

        return tabId;
    } catch {
        return null;
    }
}

async function tabCommand(state, message) {
    const tab = await appTab(state, message.key);
    if (!tab) {
        return {
            ok: false,
            error: 'Der Tab der Anwendung ist nicht offen – zuerst «Öffnen».',
        };
    }
    const origin = new URL(tab.url || state.apps[message.key].origin).origin;
    const args = message.args || {};
    const sameOrigin = (path) => {
        const url = new URL(String(path || '/'), origin);

        return url.origin === origin ? url.href : null;
    };
    switch (message.command) {
        case 'context':
            return { ok: true, url: tab.url, title: tab.title, origin };
        case 'focus':
            await chrome.tabs.update(tab.id, { active: true });
            await chrome.windows.update(tab.windowId, { focused: true });

            return { ok: true };
        case 'navigate': {
            const url = sameOrigin(args.path);
            if (!url) {
                return { ok: false, error: 'Nur Seiten derselben Anwendung.' };
            }
            await chrome.tabs.update(tab.id, { url, active: true });

            return { ok: true, url };
        }
        case 'fill': {
            const [result] = await chrome.scripting.executeScript({
                target: { tabId: tab.id },
                func: fillField,
                args: [String(args.selector || ''), String(args.value ?? '')],
            });

            return result.result;
        }
        case 'table': {
            const [result] = await chrome.scripting.executeScript({
                target: { tabId: tab.id },
                func: readTable,
                args: [String(args.selector || 'table')],
            });

            return result.result;
        }
        case 'fetch': {
            // Abruf über die Anmeldung im Tab: nur lesend (GET oder als lesend markierte Listen per POST)
            if (!sameOrigin(args.path)) {
                return {
                    ok: false,
                    error: 'Nur Adressen derselben Anwendung.',
                };
            }
            const method = String(args.method || 'GET').toUpperCase();
            if (method !== 'GET' && args.read !== true) {
                return { ok: false, error: 'Nur lesende Abrufe.' };
            }
            try {
                return await chrome.tabs.sendMessage(tab.id, {
                    kind: 'page-fetch',
                    path: args.path,
                    method,
                    body: args.body ?? null,
                });
            } catch {
                return {
                    ok: false,
                    error: 'Website noch nicht freigegeben oder Seite neu laden (Symbol der Erweiterung → «Website verbinden»).',
                };
            }
        }
        default:
            return { ok: false, error: 'Unbekannter Befehl.' };
    }
}

// Läuft im Tab der Anwendung: Feld ausfüllen (ohne Absenden)
function fillField(selector, value) {
    const el = selector ? document.querySelector(selector) : null;
    if (!el || !('value' in el)) {
        return { ok: false, error: 'Feld nicht gefunden: ' + selector };
    }
    el.focus();
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));

    return { ok: true };
}

// Läuft im Tab der Anwendung: erste passende Tabelle lesen (höchstens 500 Zeilen, 30 Spalten)
function readTable(selector) {
    const table = document.querySelector(selector || 'table');
    if (!table) {
        return {
            ok: false,
            error: 'Keine Tabelle gefunden (' + selector + ').',
        };
    }
    const text = (c) =>
        (c.innerText || c.textContent || '').trim().replace(/\s+/g, ' ');
    const rows = Array.from(table.querySelectorAll('tr'));
    const headRow = table.querySelector('thead tr') || rows[0];
    const columns = headRow
        ? Array.from(headRow.children).slice(0, 30).map(text)
        : [];
    const body = rows
        .filter((r) => r !== headRow)
        .slice(0, 500)
        .map((r) => Array.from(r.children).slice(0, 30).map(text));

    return {
        ok: true,
        columns,
        rows: body.filter((r) => r.some((c) => c !== '')),
        total: rows.length - 1,
    };
}

// Seitenwechsel in Fachanwendungs-Tabs an das Intranet melden
chrome.tabs.onUpdated.addListener(async (tabId, info, tab) => {
    if (!info.url && info.status !== 'complete') {
        return;
    }
    const state = await load();
    for (const [key, app] of Object.entries(state.apps)) {
        if (app.tabId === tabId) {
            try {
                await chrome.tabs.sendMessage(app.intranetTab, {
                    kind: 'tab-context',
                    key,
                    url: tab.url,
                    title: tab.title,
                });
            } catch {
                // Intranet-Tab geschlossen
            }
        }
    }
});

async function save(state) {
    await chrome.storage.session.set(state);
}

chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    handle(message, sender).then(sendResponse, (error) =>
        sendResponse({ ok: false, error: String(error) }),
    );

    return true;
});

async function handle(message, sender) {
    const state = await load();
    switch (message && message.kind) {
        case 'open': {
            if (!sender.tab || !/^https:\/\//.test(message.url)) {
                return { ok: false };
            }
            // Bereits offen: nach vorne holen statt neuen Tab
            const existing = message.key
                ? await appTab(state, message.key)
                : null;
            if (existing) {
                await chrome.tabs.update(existing.id, { active: true });
                state.apps[message.key].intranetTab = sender.tab.id;
                await save(state);

                return { ok: true, reused: true };
            }
            const tab = await chrome.tabs.create({
                url: message.url,
                openerTabId: sender.tab.id,
                index: sender.tab.index + 1,
            });
            try {
                const groupId =
                    sender.tab.groupId > -1
                        ? sender.tab.groupId
                        : await chrome.tabs.group({ tabIds: [sender.tab.id] });
                await chrome.tabs.group({ groupId, tabIds: [tab.id] });
                await chrome.tabGroups.update(groupId, {
                    title: 'Intranet',
                    color: 'blue',
                });
            } catch {
                // Gruppen nicht verfügbar (z. B. anderer Browser): Tab bleibt ungruppiert
            }
            if (message.key) {
                state.apps[message.key] = {
                    tabId: tab.id,
                    origin: new URL(message.url).origin,
                    intranetTab: sender.tab.id,
                };
                await save(state);
            }

            return { ok: true };
        }
        case 'split': {
            // Intranet links, Fachanwendung rechts – je eine Bildschirmhälfte
            if (!sender.tab || !/^https:\/\//.test(message.url)) {
                return { ok: false };
            }
            // Geteilte Ansicht des Browsers (Tabs Split View API, Chrome ab 155): ein Fenster, verschiebbare Trennlinie
            const shared = await splitView(state, sender.tab, message);
            if (shared) {
                return { ok: true, mode: 'split-view' };
            }
            const area = message.screen || {};
            const left = Number(area.left) || 0;
            const top = Number(area.top) || 0;
            const width = Math.max(800, Number(area.width) || 1600);
            const height = Math.max(500, Number(area.height) || 900);
            // Trennlinie in % von links (Breite des Intranet-Fensters, #151)
            const ratio = Math.min(
                80,
                Math.max(20, Number(message.split) || 50),
            );
            const half = Math.floor((width * ratio) / 100);
            await chrome.windows.update(sender.tab.windowId, {
                state: 'normal',
            });
            await chrome.windows.update(sender.tab.windowId, {
                left,
                top,
                width: half,
                height,
                focused: true,
            });
            const existing = message.key
                ? await appTab(state, message.key)
                : null;
            let tabId;
            if (existing) {
                const win = await chrome.windows.create({
                    tabId: existing.id,
                    left: left + half,
                    top,
                    width: width - half,
                    height,
                    focused: false,
                });
                tabId = win.tabs ? win.tabs[0].id : existing.id;
            } else {
                const win = await chrome.windows.create({
                    url: message.url,
                    left: left + half,
                    top,
                    width: width - half,
                    height,
                    focused: false,
                });
                tabId = win.tabs[0].id;
            }
            if (message.key) {
                state.apps[message.key] = {
                    tabId,
                    origin: new URL(message.url).origin,
                    intranetTab: sender.tab.id,
                };
                await save(state);
            }

            return { ok: true, mode: 'windows' };
        }
        case 'tab':
            if (
                !sender.tab ||
                !sender.tab.url ||
                !sender.tab.url.startsWith(INTRANET_ORIGIN)
            ) {
                return { ok: false, error: 'Nur aus dem Intranet.' };
            }

            return tabCommand(state, message);
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
            return {
                ok: true,
                recorders: state.recorders.length,
                recording: Boolean(state.recording[message.tabId]),
            };
        case 'start': {
            state.recording[message.tabId] = message.origin;
            await save(state);
            await inject(message.tabId);

            return { ok: true };
        }
        case 'connect': {
            // Aus dem Fenster der Erweiterung nach Freigabe: Hook sofort einschalten (künftig ab Seitenbeginn)
            await registerHooks();
            await chrome.scripting.executeScript({
                target: { tabId: message.tabId },
                world: 'MAIN',
                files: ['page-hook.js'],
            });
            await chrome.scripting.executeScript({
                target: { tabId: message.tabId },
                files: ['page-relay.js'],
            });

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
    for (const key of Object.keys(state.apps)) {
        if (state.apps[key].tabId === tabId) {
            delete state.apps[key];
        }
    }
    await save(state);
});

async function inject(tabId) {
    await chrome.scripting.executeScript({
        target: { tabId },
        world: 'ISOLATED',
        func: relay,
    });
    await chrome.scripting.executeScript({
        target: { tabId },
        world: 'MAIN',
        func: capture,
        args: [MAX_BODY],
    });
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
    const cut = (s) =>
        typeof s === 'string'
            ? s.length > MAX
                ? s.slice(0, MAX)
                : s
            : s == null
              ? null
              : '[binär]';
    const badge = document.createElement('div');
    badge.textContent = '● SBI-Connector · 0';
    badge.title =
        'Aufnahme läuft – Anfragen gehen an den API-Recorder im Intranet';
    badge.setAttribute(
        'style',
        'position:fixed;z-index:2147483647;right:12px;bottom:12px;padding:8px 12px;border-radius:999px;background:#c81e1e;color:#fff;font:600 13px system-ui,sans-serif;box-shadow:0 2px 10px rgba(0,0,0,.25);pointer-events:none',
    );
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
    const reqHeaders = (h) =>
        !h
            ? {}
            : h instanceof Headers
              ? headersOf(h)
              : Array.isArray(h)
                ? Object.fromEntries(h)
                : { ...h };

    const origFetch = window.fetch;
    window.fetch = async function (input, init) {
        const started = performance.now();
        const req = input instanceof Request ? input : null;
        const raw = req
            ? req.url
            : typeof input === 'string'
              ? input
              : input instanceof URL
                ? input.href
                : '';
        const url = new URL(raw, location.href).href;
        const method = (
            (init && init.method) ||
            (req && req.method) ||
            'GET'
        ).toUpperCase();
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
                    requestHeaders: {
                        ...(req ? headersOf(req.headers) : {}),
                        ...reqHeaders(init && init.headers),
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

    const proto = XMLHttpRequest.prototype;
    const open = proto.open;
    const sendXhr = proto.send;
    const setHeader = proto.setRequestHeader;
    const meta = new WeakMap();
    proto.open = function (method, url, ...rest) {
        meta.set(this, {
            m: String(method).toUpperCase(),
            u: new URL(String(url), location.href).href,
            h: {},
            t: 0,
        });

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
                            headers[line.slice(0, i).trim().toLowerCase()] =
                                line.slice(i + 1).trim();
                        }
                    });
                let text = null;
                try {
                    text =
                        this.responseType === '' || this.responseType === 'text'
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
}
