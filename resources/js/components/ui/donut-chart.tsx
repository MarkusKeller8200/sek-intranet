const COLORS = ['var(--color-chart-1)', 'var(--color-chart-2)', 'var(--color-chart-3)', 'var(--color-chart-4)', 'var(--color-chart-5)'];

/**
 * Ringdiagramm für Anteile (höchstens 5 Teile); Legende mit Wert und Prozent ist zugleich die Tabelle.
 * GUI-Prototyp «Diagramme».
 */
function DonutChart({ title, data, unit = '' }: { title: string; data: { label: string; value: number }[]; unit?: string }) {
    const total = Math.max(1, data.reduce((s, d) => s + d.value, 0));
    const r = 15.915;
    let offset = 25;

    return (
        <figure className="grid gap-3">
            <figcaption className="text-sm font-semibold">{title}</figcaption>
            <div className="flex flex-wrap items-center gap-6">
                <svg viewBox="0 0 42 42" className="size-36 shrink-0" aria-hidden>
                    <circle cx="21" cy="21" r={r} fill="none" stroke="var(--color-muted)" strokeWidth="6" />
                    {data.map((d, i) => {
                        const pct = (d.value / total) * 100;
                        const el = (
                            <circle
                                key={d.label}
                                cx="21"
                                cy="21"
                                r={r}
                                fill="none"
                                stroke={COLORS[i % COLORS.length]}
                                strokeWidth="6"
                                strokeDasharray={`${pct} ${100 - pct}`}
                                strokeDashoffset={offset}
                            />
                        );
                        offset -= pct;

                        return el;
                    })}
                    <text x="21" y="22.5" textAnchor="middle" fontSize="5" fontWeight="600" fill="var(--color-foreground)">
                        {total.toLocaleString('de-CH')}
                    </text>
                </svg>
                <table className="text-sm">
                    <tbody>
                        {data.map((d, i) => (
                            <tr key={d.label}>
                                <td className="py-0.5 pr-2">
                                    <span className="inline-block size-2.5 rounded-sm" style={{ backgroundColor: COLORS[i % COLORS.length] }} aria-hidden />
                                </td>
                                <td className="py-0.5 pr-4">{d.label}</td>
                                <td className="py-0.5 pr-3 text-right tabular-nums">{`${d.value.toLocaleString('de-CH')}${unit ? ` ${unit}` : ''}`}</td>
                                <td className="py-0.5 text-right text-muted-foreground tabular-nums">{`${Math.round((d.value / total) * 100)} %`}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

export { DonutChart };
