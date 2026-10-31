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
            });
        }
        if (data.action === 'recorder') {
            chrome.runtime.sendMessage({
                kind: 'recorder',
                active: data.active === true,
            });
        }
    });

    chrome.runtime.onMessage.addListener((message) => {
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
