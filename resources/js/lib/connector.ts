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
export function openWithConnector(url: string, title: string): boolean {
    return sendToConnector('open', { url, title });
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
