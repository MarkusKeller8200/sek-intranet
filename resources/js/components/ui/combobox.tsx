import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useId, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Popover } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type ComboOption = { value: string; label: string; hint?: string };

/** Text für die Suche: ohne Gross/Klein und Akzente. */
export function searchText(value: string): string {
    return value.toLocaleLowerCase('de-CH').normalize('NFD').replace(/[̀-ͯ]/g, '');
}

/**
 * Auswahl mit Suche (Combobox) für lange Listen, z. B. eine Person aus 500.
 * Tippen filtert, Pfeiltasten wählen, Enter übernimmt, Esc schliesst. GUI-Prototyp «Auswahl mit Suche».
 */
function Combobox({
    id,
    value,
    onChange,
    options,
    placeholder = 'Bitte wählen',
    searchPlaceholder = 'Suchen …',
    emptyText = 'Keine Treffer.',
    invalid,
    width = 320,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    options: ComboOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    invalid?: boolean;
    width?: number;
}) {
    const anchor = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listId = useId();
    const selected = options.find((o) => o.value === value);

    const filtered = useMemo(() => {
        const q = searchText(query.trim());

        return q === '' ? options : options.filter((o) => searchText(`${o.label} ${o.hint ?? ''}`).includes(q));
    }, [options, query]);

    const close = () => {
        setOpen(false);
        setQuery('');
        setActive(0);
    };
    const pick = (option: ComboOption | undefined) => {
        if (option) {
            onChange(option.value);
            close();
            anchor.current?.focus();
        }
    };
    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, filtered.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            pick(filtered[active]);
        }
    };

    return (
        <>
            <button
                ref={anchor}
                id={id}
                type="button"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-invalid={invalid || undefined}
                onClick={() => (open ? close() : setOpen(true))}
                className="flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive"
            >
                <span className={cn('truncate', !selected && 'text-muted-foreground')}>
                    {selected ? selected.label : placeholder}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
            </button>
            <Popover anchor={anchor} open={open} onClose={close} width={width}>
                <div className="-m-1 grid gap-2">
                    <div className="flex items-center gap-2 border-b px-1 pb-2">
                        <Search className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => {
                                setQuery(e.target.value);
                                setActive(0);
                            }}
                            onKeyDown={onKeyDown}
                            placeholder={searchPlaceholder}
                            aria-controls={listId}
                            aria-activedescendant={filtered[active] ? `${listId}-${filtered[active].value}` : undefined}
                            className="h-8 w-full bg-transparent text-sm outline-none"
                        />
                    </div>
                    <ul id={listId} role="listbox" className="max-h-64 overflow-auto">
                        {filtered.length === 0 && <li className="px-2 py-3 text-center text-muted-foreground">{emptyText}</li>}
                        {filtered.map((o, i) => (
                            <li
                                key={o.value}
                                id={`${listId}-${o.value}`}
                                role="option"
                                aria-selected={o.value === value}
                                onMouseEnter={() => setActive(i)}
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => pick(o)}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded px-2 py-1.5',
                                    i === active && 'bg-accent text-accent-foreground',
                                )}
                            >
                                <Check className={cn('size-4 shrink-0', o.value !== value && 'invisible')} aria-hidden />
                                <span className="grid min-w-0">
                                    <span className="truncate">{o.label}</span>
                                    {o.hint && <span className="truncate text-xs text-muted-foreground">{o.hint}</span>}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </Popover>
        </>
    );
}

export { Combobox };
