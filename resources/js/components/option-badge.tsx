import { Badge } from '@/components/ui/badge';
import { label, useColor, useOptions } from '@/hooks/use-options';
import type { ColoredList } from '@/lib/colors';
import { cn } from '@/lib/utils';

/**
 * Badge für einen Auswahlwert (Kategorie, Schulhaus, Rolle, Zielgruppe)
 * in der Farbe aus dem Farbkonzept.
 */
export default function OptionBadge({
    list,
    value,
    dot = false,
    className,
}: {
    list: ColoredList;
    value: string | null | undefined;
    dot?: boolean;
    className?: string;
}) {
    const options = useOptions();
    const color = useColor()(list, value);

    if (!value) {
        return null;
    }

    return (
        <Badge
            variant="outline"
            className={cn(
                dot ? 'gap-1.5 bg-transparent' : color.classes.badge,
                className,
            )}
        >
            {dot && (
                <span
                    className={cn('size-2 rounded-full', color.classes.dot)}
                    aria-hidden
                />
            )}
            {label(options[list], value)}
        </Badge>
    );
}
