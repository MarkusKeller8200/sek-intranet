import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
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

/**
 * Monatskalender: Woche beginnt am Montag, Pfeiltasten bewegen den Tag, Bild auf/ab den Monat,
 * Enter wählt. GUI-Prototyp «Datumsauswahl».
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
    const grid = useRef<HTMLDivElement>(null);
    const month = new Date(focus.getFullYear(), focus.getMonth(), 1);
    const offset = (month.getDay() + 6) % 7;
    const days = Array.from({ length: 42 }, (_, i) => new Date(month.getFullYear(), month.getMonth(), i - offset + 1));
    const today = isoDate(new Date());
    const title = new Intl.DateTimeFormat('de-CH', { month: 'long', year: 'numeric' }).format(month);
    const allowed = (d: Date) => (!min || isoDate(d) >= min) && (!max || isoDate(d) <= max);

    useEffect(() => {
        grid.current?.querySelector<HTMLButtonElement>(`[data-day="${isoDate(focus)}"]`)?.focus({ preventScroll: true });
    }, [focus]);

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

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                <Button type="button" variant="ghost" size="icon" className="size-7" onClick={() => move(0, -1)} title="Vorheriger Monat">
                    <ChevronLeft />
                    <span className="sr-only">Vorheriger Monat</span>
                </Button>
                <span className="text-sm font-medium" aria-live="polite">
                    {title}
                </span>
                <Button type="button" variant="ghost" size="icon" className="size-7" onClick={() => move(0, 1)} title="Nächster Monat">
                    <ChevronRight />
                    <span className="sr-only">Nächster Monat</span>
                </Button>
            </div>
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
