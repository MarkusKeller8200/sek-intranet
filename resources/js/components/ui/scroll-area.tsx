import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Scrollbereich: begrenzte Höhe/Breite mit schmaler, einheitlicher Bildlaufleiste. Mit Tab erreichbar
 * und mit Pfeiltasten scrollbar (Barrierefreiheit). GUI-Prototyp «Scrollbereich».
 */
function ScrollArea({
    children,
    label,
    className,
    orientation = 'vertical',
}: {
    children: ReactNode;
    label: string;
    className?: string;
    orientation?: 'vertical' | 'horizontal' | 'both';
}) {
    return (
        <div
            role="region"
            aria-label={label}
            tabIndex={0}
            className={cn(
                'rounded-md border [scrollbar-color:var(--color-border)_transparent] [scrollbar-width:thin] focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                orientation === 'vertical' && 'overflow-x-hidden overflow-y-auto',
                orientation === 'horizontal' && 'overflow-x-auto overflow-y-hidden',
                orientation === 'both' && 'overflow-auto',
                className,
            )}
        >
            {children}
        </div>
    );
}

export { ScrollArea };
