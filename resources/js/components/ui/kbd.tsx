import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Taste oder Tastenkürzel, z. B. <Kbd>Ctrl</Kbd>+<Kbd>K</Kbd>. GUI-Prototyp «Typografie». */
function Kbd({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <kbd className={cn('inline-flex h-5 min-w-5 items-center justify-center rounded border border-b-2 bg-muted px-1 font-mono text-[11px] font-medium text-muted-foreground', className)}>
            {children}
        </kbd>
    );
}

/** Mehrere Tasten nacheinander («Ctrl + K»). */
function KbdGroup({ keys, className }: { keys: string[]; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1', className)}>
            {keys.map((k, i) => (
                <span key={`${k}-${i}`} className="inline-flex items-center gap-1">
                    {i > 0 && <span className="text-xs text-muted-foreground">+</span>}
                    <Kbd>{k}</Kbd>
                </span>
            ))}
        </span>
    );
}

export { Kbd, KbdGroup };
