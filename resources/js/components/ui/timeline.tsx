import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type TimelineEntry = { id: string; title: ReactNode; at: string; description?: ReactNode; tone?: 'default' | 'success' | 'warning' | 'info' };

const DOT: Record<NonNullable<TimelineEntry['tone']>, string> = {
    default: 'bg-muted-foreground/50',
    success: 'bg-success',
    warning: 'bg-warning',
    info: 'bg-info',
};

/** Zeitleiste / Verlauf (neueste oben). GUI-Prototyp «Zeitleiste». */
function Timeline({ entries }: { entries: TimelineEntry[] }) {
    return (
        <ol className="relative grid gap-4 border-l pl-5">
            {entries.map((e) => (
                <li key={e.id} className="relative grid gap-0.5">
                    <span className={cn('absolute top-1.5 -left-[25px] size-2.5 rounded-full ring-4 ring-background', DOT[e.tone ?? 'default'])} aria-hidden />
                    <span className="text-xs text-muted-foreground tabular-nums">{e.at}</span>
                    <span className="text-sm font-medium">{e.title}</span>
                    {e.description && <span className="text-sm text-muted-foreground">{e.description}</span>}
                </li>
            ))}
        </ol>
    );
}

export { Timeline };
