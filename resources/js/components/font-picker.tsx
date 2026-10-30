import { Check } from 'lucide-react';
import { FONTS, useFont } from '@/hooks/use-font';
import type { FontKey } from '@/hooks/use-font';
import { cn } from '@/lib/utils';

/** Auswahl der Schriftart mit Vorschau (gilt für diesen Browser). */
export default function FontPicker() {
    const { font, updateFont } = useFont();

    return (
        <div
            className="grid gap-2 sm:grid-cols-2"
            role="radiogroup"
            aria-label="Schriftart"
        >
            {(Object.keys(FONTS) as FontKey[]).map((key) => {
                const item = FONTS[key];
                const selected = font === key;

                return (
                    <button
                        key={key}
                        type="button"
                        role="radio"
                        aria-checked={selected}
                        onClick={() => updateFont(key)}
                        className={cn(
                            'flex items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-accent',
                            selected && 'border-primary ring-1 ring-primary',
                        )}
                    >
                        <span
                            className={cn(
                                'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border',
                                selected &&
                                    'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {selected && <Check className="size-3" />}
                        </span>
                        <span className="grid gap-0.5">
                            <span
                                className="font-medium"
                                style={{ fontFamily: item.stack }}
                            >
                                {item.label}
                            </span>
                            <span
                                className="text-sm"
                                style={{ fontFamily: item.stack }}
                            >
                                Sekundarschule Bülach – Ää Öö Üü 0123456789
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {item.hint}
                            </span>
                        </span>
                    </button>
                );
            })}
        </div>
    );
}
