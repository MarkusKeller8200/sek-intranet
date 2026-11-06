/**
 * Fachanwendung in einem neuen Browser-Fenster öffnen (Einstellung «Neues Browser-Fenster»), nicht als Tab.
 * Ohne Verweis auf das Intranet (noopener, noreferrer). Grösse: 80 % des Bildschirms, mittig.
 */
export function openInWindow(url: string): void {
    const width = Math.round(window.screen.availWidth * 0.8);
    const height = Math.round(window.screen.availHeight * 0.8);
    const left = Math.round((window.screen.availWidth - width) / 2);
    const top = Math.round((window.screen.availHeight - height) / 2);

    window.open(
        url,
        '_blank',
        `popup,noopener,noreferrer,width=${width},height=${height},left=${left},top=${top}`,
    );
}
