import { useState } from 'react';
import { Button } from '@/components/ui/button';

export type LineSeries = { label: string; values: number[] };

const COLORS = ['var(--color-chart-1)', 'var(--color-chart-2)', 'var(--color-chart-3)', 'var(--color-chart-4)', 'var(--color-chart-5)'];

/** Liniendiagramm (Verlauf über Zeit), Farben chart-1…5, mit «Als Tabelle». GUI-Prototyp «Diagramme». */
function LineChart({ title, labels, series, unit = '' }: { title: string; labels: string[]; series: LineSeries[]; unit?: string }) {
    const [asTable, setAsTable] = useState(false);
    const max = Math.max(1, ...series.flatMap((s) => s.values));
    const w = 600;
    const h = 200;
    const pad = 24;
    const x = (i: number) => pad + (i * (w - 2 * pad)) / Math.max(1, labels.length - 1);
    const y = (v: number) => h - pad - (v / max) * (h - 2 * pad);

    return (
        <figure className="grid gap-3">
            <figcaption className="flex items-center justify-between gap-2">
                <span className="text-sm font-semibold">{title}</span>
                <Button type="button" variant="ghost" size="sm" onClick={() => setAsTable((t) => !t)} aria-pressed={asTable}>
                    {asTable ? 'Als Diagramm' : 'Als Tabelle'}
                </Button>
            </figcaption>
            {asTable ? (
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-1.5 font-medium" />
                            {series.map((s) => (
                                <th key={s.label} className="py-1.5 text-right font-medium">
                                    {s.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {labels.map((l, i) => (
                            <tr key={l} className="border-b last:border-0">
                                <td className="py-1.5">{l}</td>
                                {series.map((s) => (
                                    <td key={s.label} className="py-1.5 text-right tabular-nums">{`${s.values[i] ?? 0}${unit ? ` ${unit}` : ''}`}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <>
                    <div className="flex flex-wrap gap-4 text-xs text-muted-foreground">
                        {series.map((s, si) => (
                            <span key={s.label} className="flex items-center gap-1.5">
                                <span className="h-0.5 w-4 rounded" style={{ backgroundColor: COLORS[si % COLORS.length] }} aria-hidden />
                                {s.label}
                            </span>
                        ))}
                    </div>
                    <svg viewBox={`0 0 ${w} ${h}`} className="h-auto w-full" role="img" aria-label={`${title} – Werte in der Tabelle`}>
                        {[0, 0.5, 1].map((f) => (
                            <line key={f} x1={pad} x2={w - pad} y1={y(max * f)} y2={y(max * f)} stroke="var(--color-border)" strokeDasharray="3 3" />
                        ))}
                        {series.map((s, si) => (
                            <g key={s.label}>
                                <polyline fill="none" stroke={COLORS[si % COLORS.length]} strokeWidth={2.5} points={s.values.map((v, i) => `${x(i)},${y(v)}`).join(' ')} />
                                {s.values.map((v, i) => (
                                    <circle key={i} cx={x(i)} cy={y(v)} r={3.5} fill={COLORS[si % COLORS.length]} />
                                ))}
                            </g>
                        ))}
                        {labels.map((l, i) => (
                            <text key={l} x={x(i)} y={h - 4} textAnchor="middle" fontSize={11} fill="var(--color-muted-foreground)">
                                {l}
                            </text>
                        ))}
                    </svg>
                </>
            )}
        </figure>
    );
}

export { LineChart };
