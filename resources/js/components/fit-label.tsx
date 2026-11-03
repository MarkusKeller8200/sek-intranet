import { useLayoutEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Bezeichnung, die bei zu wenig Platz auf die Abkürzung umschaltet (#78, z. B. Sprachen: «Züritüütsch» → «ZH»).
 * Die volle Bezeichnung steht dann als Tooltip und für Screenreader zur Verfügung.
 */
export default function FitLabel({ label, short, className }: { label: string; short: string; className?: string }) {
    const box = useRef<HTMLSpanElement>(null);
    const probe = useRef<HTMLSpanElement>(null);
    const [fits, setFits] = useState(true);

    useLayoutEffect(() => {
        const el = box.current;
        if (!el) {
            return;
        }
        const check = () => setFits((probe.current?.offsetWidth ?? 0) <= el.clientWidth + 0.5);
        check();
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(check);
        observer.observe(el);

        return () => observer.disconnect();
    }, [label]);

    return (
        <span ref={box} className={cn('relative block min-w-0 overflow-hidden whitespace-nowrap', className)} title={fits ? undefined : label}>
            {/* unsichtbar: misst die volle Breite */}
            <span ref={probe} aria-hidden className="invisible absolute whitespace-nowrap">
                {label}
            </span>
            {fits ? label : <abbr title={label} className="no-underline">{short}</abbr>}
        </span>
    );
}
