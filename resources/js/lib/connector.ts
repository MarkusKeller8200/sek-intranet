// i18n: nicht übersetzen (Nachrichtenformat zwischen Intranet und Browser-Erweiterung)
/**
 * Verbindung zur Browser-Erweiterung «SBI-Connector» (browser-extension/, docs/KONZEPT-BROWSER-CONNECTOR.md).
 *
 * Die Erweiterung setzt im Intranet `<html data-sbi-connector="<version>">` und hört auf
 * window.postMessage-Nachrichten mit `source: 'sbi-intranet'` (nur aus diesem Fenster, gleicher Ursprung).
 * Antworten kommen als `source: 'sbi-connector'` zurück.
 */
export const PAGE_SOURCE = 'sbi-intranet';
export const CONNECTOR_SOURCE = 'sbi-connector';

/** Version der installierten Erweiterung oder null. */
export function connectorVersion(): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    return document.documentElement.dataset.sbiConnector ?? null;
}

export function sendToConnector(
    action: string,
    payload: Record<string, unknown> = {},
): boolean {
    if (connectorVersion() === null) {
        return false;
    }
    window.postMessage(
        { source: PAGE_SOURCE, action, ...payload },
        window.location.origin,
    );

    return true;
}

/** Fachanwendung im eigenen Tab öffnen (gruppiert mit dem Intranet). */
export function openWithConnector(
    url: string,
    title: string,
    key?: string,
): boolean {
    return sendToConnector('open', { url, title, key });
}

/** Intranet und Fachanwendung nebeneinander anzeigen (zwei Fenster, je halber Bildschirm). */
export function splitWithConnector(url: string, key: string): boolean {
    return sendToConnector('split', { url, key });
}

/** Nachricht der Erweiterung (nur aus diesem Fenster). */
export function isConnectorMessage(
    event: MessageEvent,
): event is MessageEvent<Record<string, unknown>> {
    const data = event.data as { source?: unknown } | null;

    return (
        event.source === window &&
        event.origin === window.location.origin &&
        !!data &&
        typeof data === 'object' &&
        data.source === CONNECTOR_SOURCE
    );
}

export type TabResult = {
    ok: boolean;
    error?: string;
    url?: string;
    title?: string;
    status?: number;
    json?: unknown;
    text?: string | null;
    signedIn?: boolean;
    columns?: string[];
    rows?: string[][];
    total?: number;
};

/**
 * Befehl an den Tab einer Fachanwendung (über die Erweiterung) mit Antwort.
 * Befehle: context, focus, navigate {path}, fill {selector, value}, table {selector}, fetch {path, method, body, read}.
 */
export function requestTab(
    key: string,
    command: string,
    args: Record<string, unknown> = {},
    timeout = 25000,
): Promise<TabResult> {
    return new Promise((resolve) => {
        const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const done = (result: TabResult) => {
            window.removeEventListener('message', onMessage);
            clearTimeout(timer);
            resolve(result);
        };
        const onMessage = (event: MessageEvent) => {
            if (
                isConnectorMessage(event) &&
                event.data.type === 'reply' &&
                event.data.id === id
            ) {
                done(
                    (event.data.result ?? {
                        ok: false,
                        error: 'Keine Antwort.',
                    }) as TabResult,
                );
            }
        };
        const timer = setTimeout(
            () => done({ ok: false, error: 'Keine Antwort der Erweiterung.' }),
            timeout,
        );
        window.addEventListener('message', onMessage);
        if (!sendToConnector('tab', { id, key, command, args })) {
            done({
                ok: false,
                error: 'Browser-Erweiterung nicht installiert.',
            });
        }
    });
}

/** Meldungen der Erweiterung, wenn im Tab einer Fachanwendung eine neue Seite offen ist. */
export function onTabContext(
    key: string,
    callback: (url: string, title: string) => void,
): () => void {
    const onMessage = (event: MessageEvent) => {
        if (
            isConnectorMessage(event) &&
            event.data.type === 'context' &&
            event.data.key === key
        ) {
            callback(
                typeof event.data.url === 'string' ? event.data.url : '',
                typeof event.data.title === 'string' ? event.data.title : '',
            );
        }
    };
    window.addEventListener('message', onMessage);

    return () => window.removeEventListener('message', onMessage);
}
