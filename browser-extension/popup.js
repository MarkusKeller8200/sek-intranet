// SBI-Connector – Fenster der Erweiterung: Aufnahme im aktuellen Tab starten oder beenden.
(async () => {
    const site = document.getElementById('site');
    const recorder = document.getElementById('recorder');
    const toggle = document.getElementById('toggle');
    const connect = document.getElementById('connect');
    const [tab] = await chrome.tabs.query({
        active: true,
        currentWindow: true,
    });
    let origin = null;
    try {
        const url = new URL(tab.url || '');
        origin = url.protocol === 'https:' ? url.origin : null;
    } catch {
        origin = null;
    }
    const intranet = chrome.runtime
        .getManifest()
        .host_permissions[0].replace('/*', '');
    site.textContent = origin
        ? 'Website: ' + origin
        : 'Diese Seite kann nicht aufgezeichnet werden (nur https).';

    const status = await chrome.runtime.sendMessage({
        kind: 'status',
        tabId: tab.id,
    });
    recorder.textContent =
        status.recorders > 0
            ? '✓ API-Recorder im Intranet ist offen.'
            : 'Kein API-Recorder offen – im Intranet «Prototyp → API-Recorder» öffnen.';

    let recording = status.recording;
    const render = () => {
        toggle.textContent = recording
            ? 'Aufnahme beenden'
            : 'Aufnahme starten';
        toggle.className = recording ? 'stop' : '';
        toggle.disabled = !origin || origin === intranet;
    };
    render();
    connect.disabled = !origin || origin === intranet;
    connect.addEventListener('click', () => {
        chrome.permissions.request({ origins: [origin + '/*'] }).then((granted) => {
            if (!granted) {
                recorder.textContent = 'Ohne Freigabe kann das Intranet diese Website nicht erreichen.';

                return;
            }
            chrome.runtime.sendMessage({ kind: 'connect', tabId: tab.id }).then(() => {
                connect.textContent = '✓ Verbunden';
                connect.disabled = true;
            });
        });
    });

    toggle.addEventListener('click', () => {
        if (recording) {
            chrome.runtime
                .sendMessage({ kind: 'stop', tabId: tab.id })
                .then(() => {
                    recording = false;
                    render();
                    recorder.textContent =
                        'Aufnahme beendet – weitere Anfragen werden nicht mehr übernommen.';
                });

            return;
        }
        // Freigabe der Website direkt im Klick anfragen (Browser verlangt eine Benutzeraktion)
        chrome.permissions
            .request({ origins: [origin + '/*'] })
            .then((granted) => {
                if (!granted) {
                    recorder.textContent =
                        'Ohne Freigabe der Website ist keine Aufnahme möglich.';

                    return;
                }
                chrome.runtime
                    .sendMessage({ kind: 'start', tabId: tab.id, origin })
                    .then((result) => {
                        recording = result && result.ok;
                        render();
                    });
            });
    });
})();
