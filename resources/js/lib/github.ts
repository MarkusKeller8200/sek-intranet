/**
 * «Als GitHub-Issue vorbereiten»: öffnet ein Issue-Formular (.github/ISSUE_TEMPLATE) vorausgefüllt.
 * Abgeschickt wird es von Hand auf GitHub; die Vorlage setzt das Label «triage» (= noch nicht freigegeben).
 * Felder über ihre id in der Vorlage; keine Namen von Personen übergeben.
 */
export function githubIssueUrl(
    repo: string,
    template: string,
    fields: Record<string, string> = {},
    title?: string,
): string {
    const params = new URLSearchParams({ template });
    if (title) {
        params.set('title', title);
    }
    for (const [key, value] of Object.entries(fields)) {
        if (value) {
            // Lange Texte kürzen, damit die Adresse gültig bleibt
            params.set(key, value.slice(0, 1500));
        }
    }

    return `https://github.com/${repo}/issues/new?${params.toString()}`;
}

/** Versionsangabe wie auf der Seite «Versionen», z. B. «0.31.0 · Developer Release (+5, Build abc1234)». */
export function versionLabel(info: {
    version: string;
    build: string | null;
    dev: { commits: number } | null;
}): string {
    if (!info.dev) {
        return info.version;
    }

    return `${info.version} · Developer Release (+${info.dev.commits}${info.build ? `, Build ${info.build}` : ''})`;
}
