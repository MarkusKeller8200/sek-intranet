import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/** Seitennummern mit «…» (z. B. 1 … 4 5 6 … 20). */
export function pageList(page: number, pages: number): (number | 'gap')[] {
    const wanted = new Set([1, pages, page - 1, page, page + 1].filter((p) => p >= 1 && p <= pages));
    const sorted = [...wanted].sort((a, b) => a - b);
    const list: (number | 'gap')[] = [];
    sorted.forEach((p, i) => {
        if (i > 0 && p - sorted[i - 1] > 1) {
            list.push('gap');
        }
        list.push(p);
    });

    return list;
}

/** Seitenweise Anzeige. GUI-Prototyp «Seitenweise Anzeige». */
function Pagination({
    page,
    pages,
    onChange,
    className,
}: {
    page: number;
    pages: number;
    onChange: (page: number) => void;
    className?: string;
}) {
    if (pages <= 1) {
        return null;
    }

    return (
        <nav aria-label="Seiten" className={cn('flex flex-wrap items-center gap-1', className)}>
            <Button variant="ghost" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>
                <ChevronLeft /> Zurück
            </Button>
            {pageList(page, pages).map((p, i) =>
                p === 'gap' ? (
                    <span key={`gap-${i}`} className="px-2 text-muted-foreground" aria-hidden>
                        …
                    </span>
                ) : (
                    <Button
                        key={p}
                        variant={p === page ? 'outline' : 'ghost'}
                        size="sm"
                        className="min-w-8 tabular-nums"
                        aria-current={p === page ? 'page' : undefined}
                        onClick={() => onChange(p)}
                    >
                        {p}
                    </Button>
                ),
            )}
            <Button variant="ghost" size="sm" disabled={page >= pages} onClick={() => onChange(page + 1)}>
                Weiter <ChevronRight />
            </Button>
        </nav>
    );
}

export { Pagination };
