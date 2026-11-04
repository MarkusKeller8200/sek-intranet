import { Link, usePage } from '@inertiajs/react';
import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { KeyboardEvent, MouseEvent, PointerEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { useGlossaryTerms, useTermHighlight } from '@/lib/glossary-terms';
import type { TermEntry } from '@/lib/glossary-terms';
import { cn } from '@/lib/utils';

/**
 * Baustein «Begriff» (#60, GUI-Prototyp «Begriff»): zeichnet einen Begriff aus dem Glossar aus.
 *
 * - `<Term id="vsa" />` zeigt die eigene Abkürzung (sonst die offizielle, sonst die Bezeichnung).
 * - `<Term match="SL">SL</Term>` sucht über Abkürzung oder Alias; mehrdeutige zeigen alle Bedeutungen.
 *
 * Fein gepunktet unterstrichen (Farben `--term-underline` und `--term-underline-hover`, im Farbkonzept
 * änderbar). Beim Darüberfahren, beim Fokus (Tastatur) und beim ersten Antippen erscheint ein Tooltip mit
 * Abkürzung, Bezeichnung und Kurzbeschreibung; ein Klick (bzw. das zweite Antippen) öffnet den Eintrag im
 * Glossar. Ohne Recht «Glossar ansehen», bei ausgeschalteter Einstellung «Begriffe hervorheben» oder
 * unbekanntem Begriff erscheint normaler Text.
 */
export function Term({
    id,
    match,
    children,
    className,
}: {
    id?: string;
    match?: string;
    children?: ReactNode;
    className?: string;
}) {
    const can = usePage<{ auth?: { can?: { viewGlossary?: boolean } } }>()
        .props.auth?.can;
    const highlight = useTermHighlight();
    const active = highlight && can?.viewGlossary === true;
    const terms = useGlossaryTerms(active);

    let entries: TermEntry[] = [];
    if (id) {
        const e = terms.byId.get(id);
        entries = e ? [e] : [];
    } else {
        const key = match ?? (typeof children === 'string' ? children : '');
        entries = terms.byMatch.get(key) ?? [];
    }

    const label = children ?? (entries[0] ? entries[0].abk || entries[0].bez : (match ?? id));
    if (!active || entries.length === 0) {
        return <>{label}</>;
    }

    return (
        <TermTrigger entries={entries} className={className}>
            {label}
        </TermTrigger>
    );
}

const WIDTH = 300;

function TermTrigger({
    entries,
    children,
    className,
}: {
    entries: TermEntry[];
    children: ReactNode;
    className?: string;
}) {
    const anchor = useRef<HTMLSpanElement>(null);
    const card = useRef<HTMLDivElement>(null);
    const timer = useRef<number | null>(null);
    const pointer = useRef<string>('mouse');
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const cardId = useId();
    const single = entries.length === 1;

    const later = (fn: () => void, ms: number) => {
        if (timer.current) {
            window.clearTimeout(timer.current);
        }
        timer.current = window.setTimeout(fn, ms);
    };
    const show = () => later(() => setOpen(true), 300);
    const hide = () => later(() => setOpen(false), 150);
    const close = () => {
        if (timer.current) {
            window.clearTimeout(timer.current);
        }
        setOpen(false);
    };

    useLayoutEffect(() => {
        if (!open || !anchor.current) {
            return;
        }
        const r = anchor.current.getBoundingClientRect();
        const height = card.current?.offsetHeight ?? 120;
        const below = r.bottom + 6 + height <= window.innerHeight;
        setPos({
            top: below ? r.bottom + 6 : Math.max(8, r.top - 6 - height),
            left: Math.min(
                Math.max(8, r.left),
                window.innerWidth - WIDTH - 8,
            ),
        });
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onKey = (e: globalThis.KeyboardEvent) => {
            if (e.key === 'Escape') {
                close();
                anchor.current
                    ?.querySelector<HTMLElement>('a, button')
                    ?.focus();
            }
        };
        const onOutside = (e: globalThis.PointerEvent) => {
            const target = e.target as Node;
            if (
                !anchor.current?.contains(target) &&
                !card.current?.contains(target)
            ) {
                close();
            }
        };
        const onScroll = () => close();
        document.addEventListener('keydown', onKey);
        document.addEventListener('pointerdown', onOutside);
        window.addEventListener('scroll', onScroll, true);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('pointerdown', onOutside);
            window.removeEventListener('scroll', onScroll, true);
        };
    }, [open]);

    const onPointerDown = (e: PointerEvent) => {
        pointer.current = e.pointerType;
    };
    // Erstes Antippen zeigt den Tooltip, das zweite folgt dem Link
    const onClick = (e: MouseEvent) => {
        if (!single || (pointer.current === 'touch' && !open)) {
            e.preventDefault();
            if (timer.current) {
                window.clearTimeout(timer.current);
            }
            setOpen(single ? true : !open);
        }
    };
    const onKeyDown = (e: KeyboardEvent) => {
        if (!single && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            setOpen(true);
            window.setTimeout(() => card.current?.querySelector('a')?.focus(), 0);
        }
    };

    const classes = cn(
        'cursor-help rounded-sm text-inherit underline decoration-term-underline decoration-dotted decoration-[1.5px] underline-offset-[3px] hover:decoration-term-underline-hover focus-visible:decoration-term-underline-hover focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-ring',
        className,
    );
    const handlers = {
        onMouseEnter: show,
        onMouseLeave: hide,
        onFocus: show,
        onBlur: hide,
        onPointerDown,
        onClick,
    };

    return (
        <span ref={anchor}>
            {single ? (
                <Link
                    href={`/glossar/${entries[0].id}`}
                    className={classes}
                    aria-describedby={open ? cardId : undefined}
                    data-term={entries[0].id}
                    {...handlers}
                >
                    {children}
                </Link>
            ) : (
                <button
                    type="button"
                    className={cn(classes, 'inline p-0 font-[inherit]')}
                    aria-expanded={open}
                    aria-controls={open ? cardId : undefined}
                    data-term={entries.map((e) => e.id).join(' ')}
                    onKeyDown={onKeyDown}
                    {...handlers}
                >
                    {children}
                </button>
            )}
            {open &&
                createPortal(
                    <div
                        ref={card}
                        id={cardId}
                        role={single ? 'tooltip' : 'group'}
                        onMouseEnter={() => later(() => setOpen(true), 0)}
                        onMouseLeave={hide}
                        onFocus={() => later(() => setOpen(true), 0)}
                        onBlur={hide}
                        style={{ top: pos.top, left: pos.left, width: WIDTH }}
                        className="fixed z-50 grid gap-3 rounded-md border bg-popover p-3 text-sm text-popover-foreground shadow-md"
                        data-testid="term-card"
                    >
                        {!single && (
                            <p className="text-xs text-muted-foreground">
                                Mehrere Bedeutungen
                            </p>
                        )}
                        {entries.map((e) => (
                            <div key={e.id} className="grid gap-1">
                                <p>
                                    {e.abk && (
                                        <span
                                            className="font-semibold"
                                            translate="no"
                                        >
                                            {e.abk}
                                        </span>
                                    )}
                                    {e.abk && e.bez && ' – '}
                                    <span translate="no">{e.bez}</span>
                                    {e.historisch && (
                                        <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                            historisch
                                        </span>
                                    )}
                                </p>
                                {e.kurz && (
                                    <p
                                        className="text-muted-foreground"
                                        translate="no"
                                    >
                                        {e.kurz}
                                    </p>
                                )}
                                <Link
                                    href={`/glossar/${e.id}`}
                                    className="w-fit text-xs text-primary underline-offset-2 hover:underline focus-visible:underline"
                                >
                                    Im Glossar öffnen
                                </Link>
                            </div>
                        ))}
                    </div>,
                    document.body,
                )}
        </span>
    );
}
