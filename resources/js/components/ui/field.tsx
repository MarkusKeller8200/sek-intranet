import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Feldgruppen: zusammengehörende Felder mit Überschrift (fieldset/legend), Beschreibung und Raster.
 * Einzelne Felder weiterhin mit FormField. GUI-Prototyp «Formularfelder».
 */
function FieldSet({ legend, description, children, className }: { legend: string; description?: string; children: ReactNode; className?: string }) {
    return (
        <fieldset className={cn('grid gap-4 rounded-lg border p-4', className)}>
            <legend className="px-1 text-sm font-semibold">{legend}</legend>
            {description && <p className="-mt-2 text-sm text-muted-foreground">{description}</p>}
            {children}
        </fieldset>
    );
}

function FieldGroup({ children, columns = 2, className }: { children: ReactNode; columns?: 1 | 2 | 3; className?: string }) {
    return <div className={cn('grid gap-4', columns === 2 && 'md:grid-cols-2', columns === 3 && 'md:grid-cols-3', className)}>{children}</div>;
}

/** Feld nebeneinander: Beschriftung links, Steuerelement rechts (z. B. Schalter in Einstellungen). */
function FieldRow({ label, description, htmlFor, children }: { label: string; description?: string; htmlFor: string; children: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-4">
            <div className="grid gap-0.5">
                <label htmlFor={htmlFor} className="text-sm font-medium">
                    {label}
                </label>
                {description && <p className="text-xs text-muted-foreground">{description}</p>}
            </div>
            {children}
        </div>
    );
}

export { FieldSet, FieldGroup, FieldRow };
