import { CalendarDays, ChevronDown, ChevronLeft, ChevronRight, ChevronUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Popover } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** Datum als «JJJJ-MM-TT» (ohne Zeitzone). */
export function isoDate(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function parseIso(value: string | null | undefined): Date | null {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');

    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null;
}

/** «TT.MM.JJJJ» → «JJJJ-MM-TT» (oder null). */
export function parseSwissDate(text: string): string | null {
    const m = /^\s*(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})\s*$/.exec(text);
    if (!m) {
        return null;
    }
    const year = m[3].length === 2 ? 2000 + Number(m[3]) : Number(m[3]);
    const d = new Date(year, Number(m[2]) - 1, Number(m[1]));

    return d.getMonth() === Number(m[2]) - 1 ? isoDate(d) : null;
}

function swiss(value: string): string {
    const d = parseIso(value);

    return d ? `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}.${d.getFullYear()}` : '';
}

const WEEKDAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

/** Jahres-Spinner (#77): senkrecht, Pfeile oder Mausrad wechseln das Jahr, Klick oder Enter übernimmt. */
function YearSpinner({ year, onPick }: { year: number; onPick: (year: number) => void }) {
    const [current, setCurrent] = useState(year);
    const box = useRef<HTMLDivElement>(null);
    useEffect(() => {
        box.current?.focus({ preventScroll: true });
    }, []);
    const years = Array.from({ length: 7 }, (_, i) => current - 3 + i);

    return (
        <div
            ref={box}
            role="listbox"
            aria-label="Jahr"
            tabIndex={0}
            onWheel={(e) => setCurrent((y) => y + (e.deltaY > 0 ? 1 : -1))}
            onKeyDown={(e) => {
                const step: Record<string, number> = { ArrowUp: -1, ArrowDown: 1, PageUp: -10, PageDown: 10 };
                if (e.key in step) {
                    e.preventDefault();
                    setCurrent((y) => y + step[e.key]);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    onPick(current);
                }
            }}
            className="mx-auto grid w-32 justify-items-stretch gap-0.5 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <Button type="button" variant="ghost" size="sm" className="h-7" onClick={() => setCurrent((y) => y - 1)} aria-label="Früheres Jahr">
                <ChevronUp />
            </Button>
            {years.map((y) => (
                <button
                    key={y}
                    type="button"
                    role="option"
                    aria-selected={y === current}
                    tabIndex={-1}
                    onClick={() => onPick(y)}
                    className={cn(
                        'rounded-md py-1 text-sm tabular-nums hover:bg-accent',
                        y === current ? 'bg-primary font-semibold text-primary-foreground hover:bg-primary' : Math.abs(y - current) > 1 && 'text-muted-foreground',
                    )}
                >
                    {y}
                </button>
            ))}
            <Button type="button" variant="ghost" size="sm" className="h-7" onClick={() => setCurrent((y) => y + 1)} aria-label="Späteres Jahr">
                <ChevronDown />
            </Button>
        </div>
    );
}

/**
 * Monatskalender: Woche beginnt am Montag, Pfeiltasten bewegen den Tag, Bild auf/ab den Monat,
 * Enter wählt. Klick auf den Monat öffnet die Monatsauswahl, Klick auf das Jahr den Jahres-Spinner (#77).
 * GUI-Prototyp «Datumsauswahl».
 */
function Calendar({
    value,
    onChange,
    min,
    max,
}: {
    value: string;
    onChange: (value: string) => void;
    min?: string;
    max?: string;
}) {
    const selected = parseIso(value);
    const [focus, setFocus] = useState<Date>(selected ?? new Date());
    const [view, setView] = useState<'days' | 'months' | 'years'>('days');
    const grid = useRef<HTMLDivElement>(null);
    const month = new Date(focus.getFullYear(), focus.getMonth(), 1);
    const offset = (month.getDay() + 6) % 7;
    const days = Array.from({ length: 42 }, (_, i) => new Date(month.getFullYear(), month.getMonth(), i - offset + 1));
    const today = isoDate(new Date());
    const title = new Intl.DateTimeFormat('de-CH', { month: 'long', year: 'numeric' }).format(month);
    const monthName = new Intl.DateTimeFormat('de-CH', { month: 'long' }).format(month);
    const monthShort = (m: number) => new Intl.DateTimeFormat('de-CH', { month: 'short' }).format(new Date(2000, m, 1));
    const allowed = (d: Date) => (!min || isoDate(d) >= min) && (!max || isoDate(d) <= max);
    // Tag behalten, aber nicht über das Monatsende hinaus
    const goTo = (year: number, m: number) =>
        setFocus((f) => new Date(year, m, Math.min(f.getDate(), new Date(year, m + 1, 0).getDate())));

    useEffect(() => {
        if (view === 'days') {
            grid.current?.querySelector<HTMLButtonElement>(`[data-day="${isoDate(focus)}"]`)?.focus({ preventScroll: true });
        }
    }, [focus, view]);

    const move = (days: number, months = 0) =>
        setFocus((f) => new Date(f.getFullYear(), f.getMonth() + months, f.getDate() + days));
    const onKeyDown = (e: KeyboardEvent) => {
        const map: Record<string, () => void> = {
            ArrowLeft: () => move(-1),
            ArrowRight: () => move(1),
            ArrowUp: () => move(-7),
            ArrowDown: () => move(7),
            PageUp: () => move(0, -1),
            PageDown: () => move(0, 1),
        };
        if (map[e.key]) {
            e.preventDefault();
            map[e.key]();
        }
    };
    const headerButton = 'rounded-md px-1.5 py-0.5 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                {view !== 'years' ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => (view === 'months' ? goTo(focus.getFullYear() - 1, focus.getMonth()) : move(0, -1))}
                        title={view === 'months' ? 'Vorheriges Jahr' : 'Vorheriger Monat'}
                    >
                        <ChevronLeft />
                        <span className="sr-only">{view === 'months' ? 'Vorheriges Jahr' : 'Vorheriger Monat'}</span>
                    </Button>
                ) : (
                    <span className="size-7" />
                )}
                <span className="flex items-center gap-0.5" aria-live="polite">
                    <button type="button" className={headerButton} aria-expanded={view === 'months'} onClick={() => setView(view === 'months' ? 'days' : 'months')} title="Monat wählen">
                        {monthName}
                    </button>
                    <button type="button" className={cn(headerButton, 'tabular-nums')} aria-expanded={view === 'years'} onClick={() => setView(view === 'years' ? 'days' : 'years')} title="Jahr wählen">
                        {focus.getFullYear()}
                    </button>
                </span>
                {view !== 'years' ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        onClick={() => (view === 'months' ? goTo(focus.getFullYear() + 1, focus.getMonth()) : move(0, 1))}
                        title={view === 'months' ? 'Nächstes Jahr' : 'Nächster Monat'}
                    >
                        <ChevronRight />
                        <span className="sr-only">{view === 'months' ? 'Nächstes Jahr' : 'Nächster Monat'}</span>
                    </Button>
                ) : (
                    <span className="size-7" />
                )}
            </div>
            {view === 'months' && (
                <div role="listbox" aria-label="Monat" className="grid grid-cols-3 gap-1">
                    {Array.from({ length: 12 }, (_, m) => (
                        <button
                            key={m}
                            type="button"
                            role="option"
                            aria-selected={m === focus.getMonth()}
                            autoFocus={m === focus.getMonth()}
                            onClick={() => {
                                goTo(focus.getFullYear(), m);
                                setView('days');
                            }}
                            className={cn(
                                'h-9 rounded-md text-sm hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                m === focus.getMonth() && 'bg-primary text-primary-foreground hover:bg-primary',
                            )}
                        >
                            {monthShort(m)}
                        </button>
                    ))}
                </div>
            )}
            {view === 'years' && (
                <YearSpinner
                    year={focus.getFullYear()}
                    onPick={(y) => {
                        goTo(y, focus.getMonth());
                        setView('days');
                    }}
                />
            )}
            {view === 'days' && (
                <div ref={grid} role="grid" aria-label={title} onKeyDown={onKeyDown} className="grid grid-cols-7 gap-0.5 text-center text-sm">
                    {WEEKDAYS.map((w) => (
                        <span key={w} role="columnheader" className="py-1 text-xs text-muted-foreground">
                            {w}
                        </span>
                    ))}
                    {days.map((d) => {
                        const key = isoDate(d);
                        const outside = d.getMonth() !== month.getMonth();
                        const isSelected = value === key;

                        return (
                            <button
                                key={key}
                                type="button"
                                role="gridcell"
                                data-day={key}
                                tabIndex={isoDate(focus) === key ? 0 : -1}
                                aria-selected={isSelected}
                                aria-current={key === today ? 'date' : undefined}
                                disabled={!allowed(d)}
                                onClick={() => onChange(key)}
                                className={cn(
                                    'size-8 rounded-md tabular-nums hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-30',
                                    outside && 'text-muted-foreground/60',
                                    key === today && 'font-semibold text-primary',
                                    isSelected && 'bg-primary text-primary-foreground hover:bg-primary',
                                )}
                            >
                                {d.getDate()}
                            </button>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

/** Datumsfeld: eintippen (TT.MM.JJJJ) oder im Kalender wählen. Wert immer «JJJJ-MM-TT». */
function DatePicker({
    id,
    value,
    onChange,
    min,
    max,
    invalid,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    min?: string;
    max?: string;
    invalid?: boolean;
}) {
    const anchor = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [text, setText] = useState(swiss(value));

    useEffect(() => setText(swiss(value)), [value]);

    return (
        <>
            <div ref={anchor} className="flex w-full max-w-56 items-center gap-1">
                <input
                    id={id}
                    value={text}
                    inputMode="numeric"
                    placeholder="TT.MM.JJJJ"
                    aria-invalid={invalid || undefined}
                    onChange={(e) => setText(e.target.value)}
                    onBlur={() => {
                        const parsed = text.trim() === '' ? '' : parseSwissDate(text);
                        if (parsed !== null) {
                            onChange(parsed);
                        } else {
                            setText(swiss(value));
                        }
                    }}
                    className="h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs tabular-nums outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
                />
                <Button type="button" variant="outline" size="icon" aria-expanded={open} onClick={() => setOpen((o) => !o)} title="Kalender öffnen">
                    <CalendarDays />
                    <span className="sr-only">Kalender öffnen</span>
                </Button>
            </div>
            <Popover anchor={anchor} open={open} onClose={() => setOpen(false)} width={276}>
                <Calendar
                    value={value}
                    min={min}
                    max={max}
                    onChange={(v) => {
                        onChange(v);
                        setOpen(false);
                    }}
                />
            </Popover>
        </>
    );
}

export { Calendar, DatePicker };
