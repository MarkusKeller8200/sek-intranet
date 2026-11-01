import { CornerDownLeft, Search } from 'lucide-react';
import { useEffect, useId, useMemo, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { searchText } from '@/components/ui/combobox';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

export type Command = { id: string; label: string; group: string; hint?: string; onSelect: () => void };

/** Ctrl+K / Cmd+K öffnet die Befehlspalette. */
export function useCommandShortcut(open: (value: boolean) => void): void {
    useEffect(() => {
        const onKey = (e: globalThis.KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                open(true);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open]);
}

/**
 * Befehlspalette: schnell zu Seiten, Personen und Aktionen springen. Tippen filtert, Pfeiltasten wählen,
 * Enter führt aus. GUI-Prototyp «Befehlspalette».
 */
function CommandPalette({
    open,
    onOpenChange,
    commands,
    placeholder = 'Seite, Person oder Aktion suchen …',
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    commands: Command[];
    placeholder?: string;
}) {
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const listId = useId();
    const filtered = useMemo(() => {
        const q = searchText(query.trim());

        return q === '' ? commands : commands.filter((c) => searchText(`${c.label} ${c.group} ${c.hint ?? ''}`).includes(q));
    }, [commands, query]);
    const groups = [...new Set(filtered.map((c) => c.group))];

    const run = (command: Command | undefined) => {
        if (command) {
            onOpenChange(false);
            setQuery('');
            command.onSelect();
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
            run(filtered[active]);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogTitle className="sr-only">Befehlspalette</DialogTitle>
                <DialogDescription className="sr-only">Tippen filtert, Pfeiltasten wählen, Enter führt aus.</DialogDescription>
                <div className="flex items-center gap-2 border-b px-3">
                    <Search className="size-4 shrink-0 text-muted-foreground" aria-hidden />
                    <input
                        autoFocus
                        value={query}
                        onChange={(e) => {
                            setQuery(e.target.value);
                            setActive(0);
                        }}
                        onKeyDown={onKeyDown}
                        placeholder={placeholder}
                        role="combobox"
                        aria-expanded
                        aria-controls={listId}
                        aria-activedescendant={filtered[active] ? `${listId}-${filtered[active].id}` : undefined}
                        className="h-11 w-full bg-transparent text-sm outline-none"
                    />
                </div>
                <div id={listId} role="listbox" className="max-h-80 overflow-auto p-2">
                    {filtered.length === 0 && <p className="py-6 text-center text-sm text-muted-foreground">Keine Treffer.</p>}
                    {groups.map((g) => (
                        <div key={g} role="group" aria-label={g} className="mb-2">
                            <p className="px-2 py-1 text-xs font-semibold text-muted-foreground">{g}</p>
                            {filtered
                                .filter((c) => c.group === g)
                                .map((c) => {
                                    const index = filtered.indexOf(c);

                                    return (
                                        <div
                                            key={c.id}
                                            id={`${listId}-${c.id}`}
                                            role="option"
                                            aria-selected={index === active}
                                            onMouseEnter={() => setActive(index)}
                                            onClick={() => run(c)}
                                            className={cn(
                                                'flex cursor-pointer items-center justify-between gap-2 rounded px-2 py-1.5 text-sm',
                                                index === active && 'bg-accent text-accent-foreground',
                                            )}
                                        >
                                            <span>{c.label}</span>
                                            {index === active ? (
                                                <CornerDownLeft className="size-3.5 text-muted-foreground" aria-hidden />
                                            ) : (
                                                c.hint && <span className="text-xs text-muted-foreground">{c.hint}</span>
                                            )}
                                        </div>
                                    );
                                })}
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}

export { CommandPalette };
