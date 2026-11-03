import { Badge } from '@/components/ui/badge';
import type { ColorName } from '@/lib/colors';
import { isColorName, palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

/** Ein farbiges Label einer Glossar-Liste (Kategorie, Herkunft) in der Farbe aus dem Farbkonzept (#74). */
export function GlossaryTag({
    label,
    color,
    title,
}: {
    label: string;
    color: string;
    title?: string;
}) {
    const name: ColorName = isColorName(color) ? color : 'slate';

    return (
        <Badge
            variant="outline"
            className={cn('shrink-0', palette[name].badge)}
            title={title}
            translate="no"
        >
            {label}
        </Badge>
    );
}

/**
 * Zell-Renderer: Mehrfachwerte («a; b») als farbige Labels.
 * Angezeigt werden nur Werte, die es in der Liste gibt (Label → Farbe).
 */
export default function GlossaryTags({
    value,
    colors,
}: {
    value: string;
    colors: Record<string, string>;
}) {
    const items = value
        .split(';')
        .map((s) => s.trim())
        .filter((s) => s !== '' && s in colors);

    return (
        <span className="flex min-w-0 gap-1 overflow-hidden">
            {items.map((t) => (
                <GlossaryTag key={t} label={t} color={colors[t]} />
            ))}
        </span>
    );
}
