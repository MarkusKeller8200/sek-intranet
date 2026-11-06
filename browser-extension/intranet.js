// SBI-Connector – Inhaltsskript im Intranet (läuft nur auf der Intranet-Adresse, siehe manifest.json)
// Meldet die Erweiterung (<html data-sbi-connector="…">) und vermittelt Nachrichten zwischen Seite und Erweiterung.
(() => {
    const PAGE = 'sbi-intranet';
    const CONNECTOR = 'sbi-connector';
    const RECORDER_TYPE = 'sbi-api-recorder';

    document.documentElement.dataset.sbiConnector =
        chrome.runtime.getManifest().version;

    window.addEventListener('message', (event) => {
        // Nur Nachrichten dieser Seite (gleiches Fenster, gleicher Ursprung)
        if (event.source !== window || event.origin !== location.origin) {
            return;
        }
        const data = event.data;
        if (!data || data.source !== PAGE || typeof data.action !== 'string') {
            return;
        }
        if (
            data.action === 'open' &&
            typeof data.url === 'string' &&
            /^https:\/\//.test(data.url)
        ) {
            chrome.runtime.sendMessage({
                kind: 'open',
                url: data.url,
                title: String(data.title || ''),
                key: typeof data.key === 'string' ? data.key : null,
            });
        }
        // Intranet und Fachanwendung nebeneinander (zwei Fenster, je halber Bildschirm)
        if (
            data.action === 'split' &&
            typeof data.url === 'string' &&
            /^https:\/\//.test(data.url)
        ) {
            chrome.runtime.sendMessage({
                kind: 'split',
                url: data.url,
                key: typeof data.key === 'string' ? data.key : null,
                // Trennlinie in % von links (Breite des Intranets, #151)
                split: Math.min(80, Math.max(20, Number(data.split) || 50)),
                screen: {
                    left: screen.availLeft || 0,
                    top: screen.availTop || 0,
                    width: screen.availWidth,
                    height: screen.availHeight,
                },
            });
        }
        // Befehl an den Tab einer Fachanwendung (Antwort mit derselben id)
        if (
            data.action === 'tab' &&
            typeof data.id === 'string' &&
            typeof data.key === 'string' &&
            typeof data.command === 'string'
        ) {
            chrome.runtime
                .sendMessage({
                    kind: 'tab',
                    key: data.key,
                    command: data.command,
                    args:
                        data.args && typeof data.args === 'object'
                            ? data.args
                            : {},
                })
                .then(
                    (result) => reply(data.id, result),
                    (error) =>
                        reply(data.id, { ok: false, error: String(error) }),
                );
        }
        if (data.action === 'recorder') {
            chrome.runtime.sendMessage({
                kind: 'recorder',
                active: data.active === true,
            });
        }
    });

    function reply(id, result) {
        window.postMessage(
            { source: CONNECTOR, type: 'reply', id, result },
            location.origin,
        );
    }

    chrome.runtime.onMessage.addListener((message) => {
        // Tab einer Fachanwendung meldet eine neue Seite (Adresse und Titel)
        if (message && message.kind === 'tab-context') {
            window.postMessage(
                {
                    source: CONNECTOR,
                    type: 'context',
                    key: message.key,
                    url: message.url,
                    title: message.title,
                },
                location.origin,
            );
        }
        if (message && message.kind === 'capture' && message.entry) {
            window.postMessage(
                {
                    source: CONNECTOR,
                    type: RECORDER_TYPE,
                    entry: message.entry,
                },
                location.origin,
            );
        }
        if (
            message &&
            message.kind === 'hello' &&
            typeof message.url === 'string'
        ) {
            window.postMessage(
                { source: CONNECTOR, type: RECORDER_TYPE, hello: message.url },
                location.origin,
            );
        }
    });
})();
