import { cn } from '@/lib/utils';

/**
 * Schieberegler für einen Wert in einem Bereich (z. B. Anzahl, Prozent). Pfeiltasten verändern den Wert;
 * der Wert steht immer daneben. GUI-Prototyp «Schieberegler».
 */
function Slider({
    id,
    value,
    onChange,
    min = 0,
    max = 100,
    step = 1,
    unit = '',
    className,
}: {
    id: string;
    value: number;
    onChange: (value: number) => void;
    min?: number;
    max?: number;
    step?: number;
    unit?: string;
    className?: string;
}) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            <input
                id={id}
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                aria-valuetext={`${value}${unit ? ` ${unit}` : ''}`}
                className="h-2 w-full cursor-pointer accent-primary"
            />
            <output htmlFor={id} className="w-16 shrink-0 text-right text-sm tabular-nums">
                {`${value}${unit ? ` ${unit}` : ''}`}
            </output>
        </div>
    );
}

export { Slider };
