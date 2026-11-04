import type { ReactNode } from 'react';

/**
 * Seitenkopf mit Titel, Beschreibung und Knöpfen. Standard: Knöpfe rechts unten neben dem Block aus Titel und
 * Beschreibung. `inline` (#92): Knöpfe in einer Zeile auf der Höhe des Titels, rechts beginnend; die Beschreibung
 * steht darunter über die ganze Breite.
 */
export default function PageHeader({
    title,
    description,
    actions,
    inline = false,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    inline?: boolean;
}) {
    const heading = (
        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
    );
    const text = description && (
        <p className="text-sm text-muted-foreground">{description}</p>
    );

    if (inline) {
        return (
            <div className="space-y-1">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    {heading}
                    {actions && (
                        <div className="flex flex-wrap gap-2 sm:justify-end">
                            {actions}
                        </div>
                    )}
                </div>
                {text}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div className="space-y-1">
                {heading}
                {text}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
