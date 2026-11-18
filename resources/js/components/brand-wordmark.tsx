import { cn } from '@/lib/utils';

/**
 * Wortmarken nach CI-Manual MarKusSXCH v1.0 (#216): Segmente in fester Reihenfolge und Farbe, als ein lesbarer Name
 * für Screenreader (aria-label), die Segment-Spans sind dekorativ. Farben nur über die festen Variablen --ci-*.
 */
const MARKEN = {
    // EduKSX: E violett, du neutral, K orange, SX blau
    education: {
        text: 'EduKSX',
        segmente: [
            ['E', 'text-ci-violet'],
            ['du', 'text-ci-neutral'],
            ['K', 'text-ci-orange'],
            ['SX', 'text-ci-blue'],
        ],
    },
    // MarKusSXCH: M violett, ar neutral, K orange, us neutral, SXCH blau
    developer: {
        text: 'MarKusSXCH',
        segmente: [
            ['M', 'text-ci-violet'],
            ['ar', 'text-ci-neutral'],
            ['K', 'text-ci-orange'],
            ['us', 'text-ci-neutral'],
            ['SXCH', 'text-ci-blue'],
        ],
    },
} as const;

export type BrandKey = keyof typeof MARKEN;

export default function BrandWordmark({
    marke,
    zusatz,
    className,
}: {
    marke: BrandKey;
    /** Produktzusatz nach Bindestrich, neutral (Markenfortschreibung v1.1), z. B. «Intranet» */
    zusatz?: string;
    className?: string;
}) {
    const m = MARKEN[marke];
    const name = zusatz ? `${m.text}-${zusatz}` : m.text;

    return (
        <span
            role="img"
            aria-label={name}
            translate="no"
            className={cn(
                'inline-block font-semibold tracking-tight whitespace-nowrap',
                className,
            )}
        >
            {m.segmente.map(([text, farbe]) => (
                <span key={text} aria-hidden className={farbe}>
                    {text}
                </span>
            ))}
            {zusatz && (
                <span aria-hidden className="text-ci-neutral">
                    -{zusatz}
                </span>
            )}
        </span>
    );
}
