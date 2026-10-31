// SBI-Connector – Vermittlung im Tab der Fachanwendung (isolierte Welt): Abrufe der Erweiterung an den
// Seiten-Hook weitergeben und die Antwort zurückschicken.
(() => {
    if (window.__sbiPageRelay) {
        return;
    }
    window.__sbiPageRelay = true;
    const pending = new Map();

    window.addEventListener('message', (event) => {
        if (event.source !== window || event.origin !== location.origin) {
            return;
        }
        const data = event.data;
        if (data && data.__sbiReply === true && pending.has(data.id)) {
            pending.get(data.id)(data.result);
            pending.delete(data.id);
        }
    });

    chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
        if (!message || message.kind !== 'page-fetch') {
            return false;
        }
        const id = Date.now() + '-' + Math.random().toString(36).slice(2);
        const timer = setTimeout(() => {
            pending.delete(id);
            sendResponse({
                ok: false,
                error: 'Keine Antwort der Seite (neu laden?).',
            });
        }, 20000);
        pending.set(id, (result) => {
            clearTimeout(timer);
            sendResponse(result);
        });
        window.postMessage(
            {
                __sbiCommand: 'fetch',
                id,
                path: message.path,
                method: message.method,
                body: message.body,
            },
            location.origin,
        );

        return true;
    });
})();
