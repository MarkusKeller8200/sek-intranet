import { useId } from 'react';
import { cn } from '@/lib/utils';

export type RadioOption = { value: string; label: string; hint?: string; disabled?: boolean };

/**
 * Optionsfelder: genau eine Wahl aus wenigen (2–5) sichtbaren Möglichkeiten. Pfeiltasten wechseln
 * (Verhalten des Browsers). GUI-Prototyp «Optionsfelder».
 */
function RadioGroup({
    label,
    value,
    onChange,
    options,
    orientation = 'vertical',
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: RadioOption[];
    orientation?: 'vertical' | 'horizontal';
    error?: string;
}) {
    const name = useId();

    return (
        <fieldset className="grid gap-2" aria-invalid={error ? true : undefined}>
            <legend className="mb-1 text-sm font-medium">{label}</legend>
            <div className={cn('grid gap-2', orientation === 'horizontal' && 'sm:flex sm:flex-wrap sm:gap-5')}>
                {options.map((o) => (
                    <label
                        key={o.value}
                        className={cn('flex cursor-pointer items-start gap-2 text-sm', o.disabled && 'cursor-not-allowed opacity-50')}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={o.value}
                            checked={value === o.value}
                            disabled={o.disabled}
                            onChange={() => onChange(o.value)}
                            className="mt-0.5 size-4 shrink-0 accent-primary"
                        />
                        <span className="grid gap-0.5">
                            <span>{o.label}</span>
                            {o.hint && <span className="text-xs text-muted-foreground">{o.hint}</span>}
                        </span>
                    </label>
                ))}
            </div>
            {error && <p className="text-sm text-destructive-foreground">{error}</p>}
        </fieldset>
    );
}

export { RadioGroup };
