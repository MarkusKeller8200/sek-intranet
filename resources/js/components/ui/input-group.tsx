import type { ComponentProps, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Eingabegruppe: Feld mit Symbol, Text davor/danach oder Knopf in einem Rahmen
 * (Suche, «@sekbuelach.ch», «CHF», Kopieren). GUI-Prototyp «Eingabegruppe».
 */
function InputGroup({ children, className, invalid }: { children: ReactNode; className?: string; invalid?: boolean }) {
    return (
        <div
            data-invalid={invalid || undefined}
            className={cn(
                'flex h-9 w-full min-w-0 items-center rounded-md border border-input bg-transparent shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 data-[invalid]:border-destructive',
                className,
            )}
        >
            {children}
        </div>
    );
}

/** Text oder Symbol vor bzw. nach dem Feld. */
function InputGroupAddon({ children, className }: { children: ReactNode; className?: string }) {
    return <span className={cn('flex shrink-0 items-center gap-1 px-3 text-sm text-muted-foreground [&_svg]:size-4', className)}>{children}</span>;
}

/** Das Feld selbst (ohne eigenen Rahmen). */
function InputGroupInput({ className, ...props }: ComponentProps<'input'>) {
    return <input {...props} className={cn('h-full w-full min-w-0 flex-1 bg-transparent px-3 text-sm outline-none placeholder:text-muted-foreground first:pl-3 [&:not(:first-child)]:pl-0', className)} />;
}

/** Kleiner Knopf im Feld (z. B. Kopieren, Leeren). */
function InputGroupButton({ className, ...props }: ComponentProps<'button'>) {
    return (
        <button
            type="button"
            {...props}
            className={cn('mr-1 inline-flex h-7 shrink-0 items-center gap-1 rounded px-2 text-xs font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none [&_svg]:size-3.5', className)}
        />
    );
}

export { InputGroup, InputGroupAddon, InputGroupInput, InputGroupButton };
