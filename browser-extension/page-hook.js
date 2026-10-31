// SBI-Connector – Seiten-Hook (Hauptwelt der Fachanwendung, ab Seitenbeginn).
// Merkt sich die Anmelde-Kopfzeile, mit der die Seite selbst ihre Schnittstelle aufruft, und beantwortet
// lesende Abrufe des Intranets damit. Die Kopfzeile bleibt in dieser Seite: Sie wird weder gespeichert
// noch an die Erweiterung oder das Intranet geschickt.
(() => {
    if (window.__sbiPageHook) {
        return;
    }
    window.__sbiPageHook = true;
    let auth = null;

    const remember = (headers) => {
        try {
            const value = new Headers(headers || {}).get('authorization');
            if (value) {
                auth = value;
            }
        } catch {
            // ungewöhnliche Kopfzeilen ignorieren
        }
    };
    const originalFetch = window.fetch;
    window.fetch = function (input, init) {
        remember(init && init.headers);
        if (input instanceof Request) {
            remember(input.headers);
        }

        return originalFetch.apply(this, arguments);
    };
    const setHeader = XMLHttpRequest.prototype.setRequestHeader;
    XMLHttpRequest.prototype.setRequestHeader = function (k, v) {
        if (String(k).toLowerCase() === 'authorization' && v) {
            auth = v;
        }

        return setHeader.call(this, k, v);
    };

    const reply = (id, result) =>
        window.postMessage({ __sbiReply: true, id, result }, location.origin);

    window.addEventListener('message', async (event) => {
        if (event.source !== window || event.origin !== location.origin) {
            return;
        }
        const data = event.data;
        if (
            !data ||
            data.__sbiCommand !== 'fetch' ||
            typeof data.id !== 'string'
        ) {
            return;
        }
        let url;
        try {
            url = new URL(String(data.path), location.origin);
        } catch {
            reply(data.id, { ok: false, error: 'Adresse ungültig.' });

            return;
        }
        if (url.origin !== location.origin) {
            reply(data.id, {
                ok: false,
                error: 'Nur Adressen derselben Anwendung.',
            });

            return;
        }
        const headers = { Accept: 'application/json' };
        if (auth) {
            headers.Authorization = auth;
        }
        if (data.body != null) {
            headers['Content-Type'] = 'application/json';
        }
        try {
            const response = await originalFetch.call(window, url.href, {
                method: data.method || 'GET',
                headers,
                body: data.body != null ? JSON.stringify(data.body) : undefined,
                credentials: 'include',
            });
            const text = await response.text();
            let json = null;
            try {
                json = JSON.parse(text);
            } catch {
                json = null;
            }
            reply(data.id, {
                ok: response.ok,
                status: response.status,
                json,
                text: json === null ? text.slice(0, 5000) : null,
                signedIn: auth !== null,
            });
        } catch (error) {
            reply(data.id, { ok: false, error: String(error) });
        }
    });
})();
