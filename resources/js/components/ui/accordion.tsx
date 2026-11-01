import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type AccordionItem = { id: string; title: string; content: ReactNode };

/**
 * Akkordeon: mehrere aufklappbare Abschnitte, z. B. Fragen & Antworten oder selten gebrauchte Angaben.
 * `multiple` erlaubt mehrere offene Abschnitte. GUI-Prototyp «Akkordeon».
 */
function Accordion({
    items,
    multiple = false,
    defaultOpen = [],
}: {
    items: AccordionItem[];
    multiple?: boolean;
    defaultOpen?: string[];
}) {
    const [open, setOpen] = useState<string[]>(defaultOpen);
    const base = useId();
    const toggle = (id: string) =>
        setOpen((o) => (o.includes(id) ? o.filter((x) => x !== id) : multiple ? [...o, id] : [id]));

    return (
        <div className="divide-y rounded-lg border">
            {items.map((item) => {
                const isOpen = open.includes(item.id);

                return (
                    <div key={item.id}>
                        <h3>
                            <button
                                type="button"
                                id={`${base}-h-${item.id}`}
                                aria-expanded={isOpen}
                                aria-controls={`${base}-p-${item.id}`}
                                onClick={() => toggle(item.id)}
                                className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm font-medium hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span>{item.title}</span>
                                <ChevronDown className={cn('size-4 shrink-0 transition-transform', isOpen && 'rotate-180')} aria-hidden />
                            </button>
                        </h3>
                        {isOpen && (
                            <div id={`${base}-p-${item.id}`} role="region" aria-labelledby={`${base}-h-${item.id}`} className="px-4 pb-4 text-sm text-muted-foreground">
                                {item.content}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export { Accordion };
