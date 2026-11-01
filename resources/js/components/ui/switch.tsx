import { cn } from '@/lib/utils';

/**
 * Schalter (Switch) für Einstellungen, die sofort gelten (ein/aus). Für Angaben in einem Formular,
 * die erst mit «Speichern» gelten, ein Kontrollkästchen verwenden. GUI-Prototyp «Schalter».
 */
function Switch({
    id,
    checked,
    onCheckedChange,
    disabled,
    'aria-label': ariaLabel,
    'aria-describedby': describedBy,
}: {
    id?: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    'aria-label'?: string;
    'aria-describedby'?: string;
}) {
    return (
        <button
            id={id}
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={ariaLabel}
            aria-describedby={describedBy}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'inline-flex h-5 w-9 shrink-0 items-center rounded-full border-2 border-transparent transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-input',
            )}
        >
            <span
                className={cn(
                    'pointer-events-none block size-4 rounded-full bg-background shadow-sm transition-transform',
                    checked ? 'translate-x-4' : 'translate-x-0',
                )}
            />
        </button>
    );
}

export { Switch };
