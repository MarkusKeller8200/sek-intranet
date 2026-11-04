import { useLayoutEffect, useRef, useState } from 'react';
import { useLanguage } from '@/components/language-picker';
import { shortOf } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * Bezeichnung, die bei zu wenig Platz auf die Kurzform umschaltet (#78, #81; z. B. «Züritüütsch» → «ZH»).
 * Ohne «short» kommt die Kurzform aus dem Katalog resources/js/i18n/short.json (passend zur Sprache);
 * gibt es keine, wird die Bezeichnung wie bisher mit «…» gekürzt. Die volle Bezeichnung steht als
 * Tooltip und für Screenreader zur Verfügung.
 */
export default function FitLabel({
    label,
    short,
    className,
}: {
    label: string;
    short?: string;
    className?: string;
}) {
    const language = useLanguage();
    const abbreviation = short ?? shortOf(label, language);
    const box = useRef<HTMLSpanElement>(null);
    const probe = useRef<HTMLSpanElement>(null);
    const [fits, setFits] = useState(true);

    useLayoutEffect(() => {
        const el = box.current;
        if (!el) {
            return;
        }
        const check = () =>
            setFits((probe.current?.offsetWidth ?? 0) <= el.clientWidth + 0.5);
        check();
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        // auch die Messprobe beobachten: die Übersetzung im DOM ändert ihre Breite
        const observer = new ResizeObserver(check);
        observer.observe(el);
        if (probe.current) {
            observer.observe(probe.current);
        }

        return () => observer.disconnect();
    }, [label, abbreviation]);

    if (!abbreviation) {
        return (
            <span className={cn('block min-w-0 truncate', className)}>
                {label}
            </span>
        );
    }

    return (
        <span
            ref={box}
            className={cn(
                'relative block min-w-0 overflow-hidden whitespace-nowrap',
                className,
            )}
            title={fits ? undefined : label}
        >
            {/* unsichtbar, aber im Fluss: hält die Breite der vollen Bezeichnung offen,
                damit die Spalte beim Breiterziehen wieder Platz dafür bekommt (#81) */}
            <span
                aria-hidden
                className="invisible block h-0 overflow-hidden"
            >
                <span ref={probe} className="inline-block whitespace-nowrap">
                    {label}
                </span>
            </span>
            {fits ? (
                label
            ) : (
                <abbr title={label} className="no-underline">
                    <span aria-hidden translate="no">
                        {abbreviation}
                    </span>
                    <span className="sr-only">{label}</span>
                </abbr>
            )}
        </span>
    );
}
