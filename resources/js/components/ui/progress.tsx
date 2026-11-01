import { cn } from '@/lib/utils';

/**
 * Fortschrittsbalken. Ohne `value` läuft er unbestimmt (Dauer unbekannt). GUI-Prototyp «Fortschritt».
 */
function Progress({
    value,
    max = 100,
    label,
    showValue = true,
    className,
}: {
    value?: number | null;
    max?: number;
    label: string;
    showValue?: boolean;
    className?: string;
}) {
    const known = typeof value === 'number';
    const percent = known ? Math.round((Math.min(Math.max(value, 0), max) / max) * 100) : 0;

    return (
        <div className={cn('grid gap-1.5', className)}>
            <div className="flex items-center justify-between text-sm">
                <span>{label}</span>
                {showValue && known && <span className="text-muted-foreground tabular-nums">{`${percent} %`}</span>}
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-valuenow={known ? value : undefined}
                className="relative h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn('h-full rounded-full bg-primary transition-[width]', !known && 'w-1/3 animate-pulse')}
                    style={known ? { width: `${percent}%` } : undefined}
                />
            </div>
        </div>
    );
}

export { Progress };
