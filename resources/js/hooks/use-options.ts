import { usePage } from '@inertiajs/react';
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
