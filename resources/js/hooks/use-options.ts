import { usePage } from '@inertiajs/react';
import { colorConfigKey, isColorName, palette } from '@/lib/colors';
import type { ColorClasses, ColorName, ColoredList } from '@/lib/colors';
import type { Options } from '@/types';

/** Auswahllisten aus config/intranet.php (Rollen, Schulhäuser, Kategorien …). */
export function useOptions(): Options {
    return usePage().props.options;
}

export function label(
    list: Record<string, string>,
    key: string | null | undefined,
): string {
    return key ? (list[key] ?? key) : '';
}

/** Farbe eines Auswahlwerts gemäss config/intranet.php → colors. */
export function useColor(): (
    list: ColoredList,
    key: string | null | undefined,
) => { name: ColorName; classes: ColorClasses } {
    const { colors } = useOptions();

    return (list, key) => {
        const value = key ? colors[colorConfigKey[list]]?.[key] : undefined;
        const name: ColorName = isColorName(value) ? value : 'slate';

        return { name, classes: palette[name] };
    };
}
