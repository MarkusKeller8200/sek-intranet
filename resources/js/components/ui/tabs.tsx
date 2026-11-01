import { createContext, useContext, useId } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Reiter (Tabs) nach WAI-ARIA: Pfeiltasten wechseln den Reiter, Pos1/Ende springen an den Rand.
 * GUI-Prototyp «Reiter (Tabs)».
 */
type Ctx = { value: string; onValueChange: (v: string) => void; base: string };
const TabsContext = createContext<Ctx | null>(null);

function useTabs(): Ctx {
    const ctx = useContext(TabsContext);
    if (!ctx) {
        throw new Error('Tabs-Bestandteile nur innerhalb von <Tabs> verwenden.');
    }

    return ctx;
}

function Tabs({
    value,
    onValueChange,
    children,
    className,
}: {
    value: string;
    onValueChange: (value: string) => void;
    children: ReactNode;
    className?: string;
}) {
    const base = useId();

    return (
        <TabsContext.Provider value={{ value, onValueChange, base }}>
            <div className={cn('grid gap-4', className)}>{children}</div>
        </TabsContext.Provider>
    );
}

function TabsList({ children, className, label }: { children: ReactNode; className?: string; label?: string }) {
    const { onValueChange } = useTabs();

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        const tabs = Array.from(e.currentTarget.querySelectorAll<HTMLButtonElement>('[role="tab"]:not([disabled])'));
        const index = tabs.indexOf(document.activeElement as HTMLButtonElement);
        if (index < 0) {
            return;
        }
        const next =
            e.key === 'ArrowRight' ? (index + 1) % tabs.length
            : e.key === 'ArrowLeft' ? (index - 1 + tabs.length) % tabs.length
            : e.key === 'Home' ? 0
            : e.key === 'End' ? tabs.length - 1
            : null;
        if (next === null) {
            return;
        }
        e.preventDefault();
        tabs[next].focus();
        onValueChange(tabs[next].dataset.value ?? '');
    };

    return (
        <div
            role="tablist"
            aria-label={label}
            onKeyDown={onKeyDown}
            className={cn('inline-flex w-fit flex-wrap items-center gap-1 border-b', className)}
        >
            {children}
        </div>
    );
}

function TabsTrigger({ value, children, disabled }: { value: string; children: ReactNode; disabled?: boolean }) {
    const { value: current, onValueChange, base } = useTabs();
    const selected = current === value;

    return (
        <button
            type="button"
            role="tab"
            id={`${base}-tab-${value}`}
            aria-controls={`${base}-panel-${value}`}
            aria-selected={selected}
            tabIndex={selected ? 0 : -1}
            disabled={disabled}
            data-value={value}
            data-state={selected ? 'active' : 'inactive'}
            onClick={() => onValueChange(value)}
            className={cn(
                '-mb-px inline-flex items-center gap-1.5 border-b-2 border-transparent px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50 [&_svg]:size-4',
                selected && 'border-primary text-foreground',
            )}
        >
            {children}
        </button>
    );
}

function TabsContent({ value, children, className }: { value: string; children: ReactNode; className?: string }) {
    const { value: current, base } = useTabs();
    if (current !== value) {
        return null;
    }

    return (
        <div
            role="tabpanel"
            id={`${base}-panel-${value}`}
            aria-labelledby={`${base}-tab-${value}`}
            tabIndex={0}
            className={cn('focus-visible:outline-none', className)}
        >
            {children}
        </div>
    );
}

export { Tabs, TabsList, TabsTrigger, TabsContent };
