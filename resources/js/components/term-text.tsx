import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Term } from '@/components/ui/term';
import { useGlossaryTerms, useTermHighlight } from '@/lib/glossary-terms';
import type { TermIndex } from '@/lib/glossary-terms';

/**
 * Begriffe im Freitext (#61): erkennt Abkürzungen und Aliase aus dem Glossar beim Anzeigen und zeichnet
 * sie wie der Baustein «Begriff» aus. Der gespeicherte Text bleibt unverändert. Nur für Anzeigen – nie in
 * Eingabefeldern, Code oder Links (die Markdown-Anzeige reicht nur reinen Text weiter).
 */
export function TermText({ text }: { text: string }) {
    const can = usePage<{ auth?: { can?: { viewGlossary?: boolean } } }>().props
        .auth?.can;
    const highlight = useTermHighlight();
    const terms = useGlossaryTerms(highlight && can?.viewGlossary === true);

    return <>{splitTerms(text, terms)}</>;
}

/** Text in Abschnitte und Begriffe zerlegen. */
export function splitTerms(text: string, terms: TermIndex): ReactNode[] {
    const pattern = terms.pattern;
    if (!pattern || text === '') {
        return [text];
    }
    const out: ReactNode[] = [];
    let last = 0;
    pattern.lastIndex = 0;
    let m: RegExpExecArray | null;
    while ((m = pattern.exec(text)) !== null) {
        const before = m[1] ?? '';
        const term = m[2] ?? '';
        const start = m.index + before.length;
        if (start > last) {
            out.push(text.slice(last, start));
        }
        out.push(
            <Term key={`${start}-${term}`} match={term}>
                {term}
            </Term>,
        );
        last = start + term.length;
        // Zeichen davor gehört nicht zum Begriff: ab dem Begriffsende weitersuchen
        pattern.lastIndex = last;
    }
    if (last < text.length) {
        out.push(text.slice(last));
    }

    return out;
}
