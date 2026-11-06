/**
 * Fachanwendung in einem neuen Browser-Fenster öffnen (Einstellung «Neues Browser-Fenster»), nicht als Tab.
 * Grösse in % des sichtbaren Anzeigebereichs (Einstellung, Vorgabe 80 %), mittig; ohne Verweis auf das Intranet.
 */
export function openInWindow(
    url: string,
    widthPercent = 80,
    heightPercent = 80,
): void {
    const share = (value: number) => Math.min(100, Math.max(20, value)) / 100;
    const width = Math.round(window.screen.availWidth * share(widthPercent));
    const height = Math.round(
        window.screen.availHeight * share(heightPercent),
    );
    const left = Math.round((window.screen.availWidth - width) / 2);
    const top = Math.round((window.screen.availHeight - height) / 2);

    window.open(
        url,
        '_blank',
        `popup,noopener,noreferrer,width=${width},height=${height},left=${left},top=${top}`,
    );
}
