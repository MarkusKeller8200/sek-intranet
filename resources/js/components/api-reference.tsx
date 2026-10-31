// i18n: nicht übersetzen – Scalar rendert eigene Texte; die Übersetzungsschicht darf sie nicht anfassen.
import { useEffect, useRef, useState } from 'react';

/** Minimaler Ausschnitt der Scalar-Schnittstelle (Paket @scalar/api-reference). */
type ScalarApp = { destroy: () => void };
type ScalarModule = {
    createApiReference: (
        el: HTMLElement,
        config: Record<string, unknown>,
    ) => ScalarApp;
};

/**
 * Datenschutzfreundliche Grundeinstellung: keine Telemetrie, keine Schriften von fremden Servern,
 * kein Scalar-Proxy (Anfragen gehen nur direkt aus dem Browser), kein Agent/MCP von Scalar.
 */
export function scalarConfig(
    spec: object | string,
    dark: boolean,
): Record<string, unknown> {
    return {
        ...(typeof spec === 'string' ? { url: spec } : { content: spec }),
        telemetry: false,
        withDefaultFonts: false,
        showDeveloperTools: 'never',
        agent: { disabled: true },
        mcp: { disabled: true },
        persistAuth: false,
        hideDarkModeToggle: true,
        forceDarkModeState: dark ? 'dark' : 'light',
        darkMode: dark,
        documentDownloadType: 'both',
        layout: 'modern',
        theme: 'default',
    };
}

/**
 * API-Referenz mit Scalar (Open Source, MIT). Wird erst geladen, wenn die Ansicht geöffnet wird.
 * spec: OpenAPI-Objekt oder Adresse einer OpenAPI-Datei (gleicher Ursprung).
 */
export default function ApiReference({ spec }: { spec: object | string }) {
    const host = useRef<HTMLDivElement>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let app: ScalarApp | null = null;
        let cancelled = false;

        void Promise.all([
            import('@scalar/api-reference') as Promise<unknown>,
            import('@scalar/api-reference/style.css'),
        ])
            .then(([mod]) => {
                const scalar = mod as Partial<ScalarModule>;
                if (cancelled || !host.current) {
                    return;
                }
                if (typeof scalar.createApiReference !== 'function') {
                    throw new Error('Scalar nicht verfügbar');
                }
                const dark =
                    document.documentElement.classList.contains('dark');
                app = scalar.createApiReference(
                    host.current,
                    scalarConfig(spec, dark),
                );
            })
            .catch(() =>
                setError('Die API-Ansicht konnte nicht geladen werden.'),
            );

        return () => {
            cancelled = true;
            app?.destroy();
        };
    }, [spec]);

    return (
        <div
            className="scalar-host min-h-[70vh] overflow-hidden rounded-xl border bg-background"
            translate="no"
            data-testid="api-reference"
        >
            {error ? (
                <p className="p-6 text-sm text-destructive">{error}</p>
            ) : (
                <div ref={host} />
            )}
        </div>
    );
}
