import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type BarDatum = { label: string; value: number; secondary?: number };

/**
 * Einfaches Balkendiagramm ohne Bibliothek, mit Umschalter «Als Tabelle» (Bildschirmleser, Export).
 * Farben aus den CSS-Tokens (primary, brand). GUI-Prototyp «Diagramme».
 */
function BarChart({
    title,
    data,
    unit = '',
    valueLabel = 'Wert',
    secondaryLabel,
}: {
    title: string;
    data: BarDatum[];
    unit?: string;
    valueLabel?: string;
    secondaryLabel?: string;
}) {
    const [asTable, setAsTable] = useState(false);
    const max = Math.max(1, ...data.map((d) => Math.max(d.value, d.secondary ?? 0)));
    const fmt = (n: number) => `${n.toLocaleString('de-CH')}${unit ? ` ${unit}` : ''}`;

    return (
        <figure className="grid gap-3">
            <figcaption className="flex items-center justify-between gap-2">
                <span className="text-sm font-semibold">{title}</span>
                <Button type="button" variant="ghost" size="sm" onClick={() => setAsTable((t) => !t)} aria-pressed={asTable}>
                    {asTable ? 'Als Diagramm' : 'Als Tabelle'}
                </Button>
            </figcaption>
            {secondaryLabel && !asTable && (
                <div className="flex gap-4 text-xs text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-primary" aria-hidden />
                        {valueLabel}
                    </span>
                    <span className="flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm bg-brand/40" aria-hidden />
                        {secondaryLabel}
                    </span>
                </div>
            )}
            {asTable ? (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-1.5 font-medium" />
                            <th className="py-1.5 text-right font-medium">{valueLabel}</th>
                            {secondaryLabel && <th className="py-1.5 text-right font-medium">{secondaryLabel}</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((d) => (
                            <tr key={d.label} className="border-b last:border-0">
                                <td className="py-1.5">{d.label}</td>
                                <td className="py-1.5 text-right tabular-nums">{fmt(d.value)}</td>
                                {secondaryLabel && <td className="py-1.5 text-right tabular-nums">{fmt(d.secondary ?? 0)}</td>}
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <ul className="grid gap-2" aria-hidden>
                    {data.map((d) => (
                        <li key={d.label} className="grid grid-cols-[8rem_1fr_auto] items-center gap-2 text-sm">
                            <span className="truncate text-muted-foreground">{d.label}</span>
                            <span className="relative h-4 rounded bg-muted">
                                {d.secondary !== undefined && (
                                    <span className="absolute inset-y-0 left-0 rounded bg-brand/40" style={{ width: `${(d.secondary / max) * 100}%` }} />
                                )}
                                <span className={cn('absolute left-0 rounded bg-primary', d.secondary !== undefined ? 'inset-y-1' : 'inset-y-0')} style={{ width: `${(d.value / max) * 100}%` }} />
                            </span>
                            <span className="w-20 text-right tabular-nums">{fmt(d.value)}</span>
                        </li>
                    ))}
                </ul>
            )}
            {!asTable && <p className="sr-only">Die Werte stehen auch in der Tabelle («Als Tabelle»).</p>}
        </figure>
    );
}

export { BarChart };
