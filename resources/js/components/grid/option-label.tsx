import { isColorName, palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

/** Wert einer Auswahlliste: farbiges Label, wenn die Spalte Farben hat, sonst Text (#76). */
export default function OptionLabel({
    label,
    color,
}: {
    label: string;
    color?: string;
}) {
    if (!color) {
        return <span translate="no">{label}</span>;
    }

    return (
        <span
            translate="no"
            className={cn(
                'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
                palette[isColorName(color) ? color : 'slate'].badge,
            )}
        >
            {label}
        </span>
    );
}
