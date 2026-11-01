import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Knopfgruppe: zusammengehörende Knöpfe ohne Abstand, z. B. «CSV | Excel» oder Ansicht wählen.
 * GUI-Prototyp «Knopfgruppe».
 */
function ButtonGroup({
    children,
    label,
    orientation = 'horizontal',
    className,
}: {
    children: ReactNode;
    label: string;
    orientation?: 'horizontal' | 'vertical';
    className?: string;
}) {
    return (
        <div
            role="group"
            aria-label={label}
            className={cn(
                'inline-flex w-fit',
                orientation === 'horizontal'
                    ? '[&>*]:shadow-none [&>*:not(:first-child)]:-ml-px [&>*:not(:first-child)]:rounded-l-none [&>*:not(:last-child)]:rounded-r-none'
                    : 'flex-col [&>*]:shadow-none [&>*:not(:first-child)]:-mt-px [&>*:not(:first-child)]:rounded-t-none [&>*:not(:last-child)]:rounded-b-none',
                '[&>*:focus-visible]:z-10',
                className,
            )}
        >
            {children}
        </div>
    );
}

export { ButtonGroup };
