import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Eintrag: Zeile mit Bild/Symbol, Titel, Beschreibung und Aktionen – für Listen von Personen, Dokumenten,
 * Plugins. GUI-Prototyp «Eintrag».
 */
function Item({
    media,
    title,
    description,
    meta,
    actions,
    variant = 'outline',
    className,
}: {
    media?: ReactNode;
    title: ReactNode;
    description?: ReactNode;
    meta?: ReactNode;
    actions?: ReactNode;
    variant?: 'outline' | 'plain' | 'muted';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-lg p-3 text-sm',
                variant === 'outline' && 'border',
                variant === 'muted' && 'bg-muted/50',
                className,
            )}
        >
            {media && <div className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground [&_svg]:size-5">{media}</div>}
            <div className="grid min-w-0 flex-1 gap-0.5">
                <div className="flex flex-wrap items-center gap-2 font-medium">{title}</div>
                {description && <div className="truncate text-muted-foreground">{description}</div>}
            </div>
            {meta && <div className="hidden shrink-0 text-xs text-muted-foreground sm:block">{meta}</div>}
            {actions && <div className="flex shrink-0 items-center gap-1">{actions}</div>}
        </div>
    );
}

/** Liste von Einträgen mit Trennlinien (Einträge darin mit variant="plain"). */
function ItemList({ children, className }: { children: ReactNode; className?: string }) {
    return <div className={cn('divide-y rounded-lg border [&>*]:rounded-none [&>*]:border-0', className)}>{children}</div>;
}

export { Item, ItemList };
