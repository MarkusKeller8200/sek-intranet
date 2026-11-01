import { ChevronsUpDown, Search, X } from 'lucide-react';
import { useId, useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import type { ComboOption } from '@/components/ui/combobox';
import { searchText } from '@/components/ui/combobox';
import { Popover } from '@/components/ui/popover';

/**
 * Mehrfachauswahl mit Suche, «Alle» / «Keine» und entfernbaren Kennzeichen.
 * GUI-Prototyp «Mehrfachauswahl».
 */
function MultiSelect({
    id,
    values,
    onChange,
    options,
    placeholder = 'Bitte wählen',
    maxBadges = 3,
}: {
    id: string;
    values: string[];
    onChange: (values: string[]) => void;
    options: ComboOption[];
    placeholder?: string;
    maxBadges?: number;
}) {
    const anchor = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const base = useId();
    const chosen = options.filter((o) => values.includes(o.value));
    const filtered = useMemo(() => {
        const q = searchText(query.trim());

        return q === '' ? options : options.filter((o) => searchText(o.label).includes(q));
    }, [options, query]);
    const toggle = (value: string, on: boolean) =>
        onChange(on ? [...values, value] : values.filter((v) => v !== value));

    return (
        <div className="grid gap-2">
            <button
                ref={anchor}
                id={id}
                type="button"
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((o) => !o)}
                className="flex h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 text-left text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <span className={chosen.length ? '' : 'text-muted-foreground'}>
                    {chosen.length === 0 ? placeholder : chosen.length === 1 ? chosen[0].label : `${chosen.length} gewählt`}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
            </button>
            {chosen.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {chosen.slice(0, maxBadges).map((o) => (
                        <Badge key={o.value} variant="secondary" className="gap-1 pr-1">
                            <span translate="no">{o.label}</span>
                            <button
                                type="button"
                                className="rounded hover:bg-background/60"
                                onClick={() => toggle(o.value, false)}
                                title="Entfernen"
                            >
                                <X className="size-3" />
                                <span className="sr-only">Entfernen</span>
                            </button>
                        </Badge>
                    ))}
                    {chosen.length > maxBadges && <Badge variant="outline">{`+${chosen.length - maxBadges}`}</Badge>}
                </div>
            )}
            <Popover anchor={anchor} open={open} onClose={() => setOpen(false)} width={300}>
                <div className="-m-1 grid gap-2">
                    <div className="flex items-center gap-2 border-b px-1 pb-2">
                        <Search className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Suchen …"
                            className="h-8 w-full bg-transparent text-sm outline-none"
                        />
                    </div>
                    <div className="flex gap-3 px-1 text-xs">
                        <button type="button" className="text-primary hover:underline" onClick={() => onChange([...new Set([...values, ...filtered.map((o) => o.value)])])}>
                            Alle
                        </button>
                        <button type="button" className="text-primary hover:underline" onClick={() => onChange([])}>
                            Keine
                        </button>
                    </div>
                    <ul className="max-h-64 overflow-auto">
                        {filtered.map((o) => (
                            <li key={o.value} className="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent">
                                <Checkbox
                                    id={`${base}-${o.value}`}
                                    checked={values.includes(o.value)}
                                    onCheckedChange={(c: boolean | 'indeterminate') => toggle(o.value, c === true)}
                                />
                                <label htmlFor={`${base}-${o.value}`} className="flex-1 cursor-pointer" translate="no">
                                    {o.label}
                                </label>
                            </li>
                        ))}
                    </ul>
                </div>
            </Popover>
        </div>
    );
}

export { MultiSelect };
