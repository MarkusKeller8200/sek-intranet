import { Link, router } from '@inertiajs/react';
import { Check, Copy, Eye, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import SelectField from '@/components/select-field';
import { ThemeSample, ThemeScope } from '@/components/theme-scope';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { t } from '@/lib/i18n';
import { resolveColors } from '@/lib/themes';
import type { ThemeData, ThemeDefaults } from '@/lib/themes';
import { cn } from '@/lib/utils';

const SWATCHES = [
    'brand',
    'primary',
    'accent',
    'background',
    'foreground',
    'sidebar',
];

/** Farbmuster eines Themes (hell und dunkel). */
function Swatches({
    theme,
    defaults,
}: {
    theme: ThemeData;
    defaults: ThemeDefaults;
}) {
    return (
        <div className="grid gap-1">
            {(['light', 'dark'] as const).map((mode) => {
                const colors = resolveColors(theme, defaults, mode);

                return (
                    <div
                        key={mode}
                        className="flex overflow-hidden rounded border"
                        aria-hidden
                    >
                        {SWATCHES.map((token) => (
                            <span
                                key={token}
                                className="h-4 flex-1"
                                style={{ backgroundColor: colors[token] }}
                            />
                        ))}
                    </div>
                );
            })}
        </div>
    );
}

/**
 * Theme-Auswahl im Farbkonzept: gespeicherte Farbkonzepte ansehen, als Vorschau auf dieser Seite
 * anwenden, für alle aktivieren, bearbeiten, kopieren, löschen und neue (auch aus Vorlage) anlegen.
 */
export default function ThemeSelector({
    themes,
    defaults,
    presets,
}: {
    themes: ThemeData[];
    defaults: ThemeDefaults;
    presets: Record<string, { name: string; description: string }>;
}) {
    const active = themes.find((th) => th.active) ?? themes[0];
    const [preview, setPreview] = useState<string>(active.id);
    const [preset, setPreset] = useState('');
    const shown = themes.find((th) => th.id === preview) ?? active;

    // Vorschau auf der ganzen Seite: Farben am <html> setzen, beim Verlassen wieder entfernen
    useEffect(() => {
        const root = document.documentElement;
        const mode = root.classList.contains('dark') ? 'dark' : 'light';
        const colors =
            shown.id === active.id ? {} : resolveColors(shown, defaults, mode);
        Object.entries(colors).forEach(([k, v]) =>
            root.style.setProperty(`--${k}`, v),
        );

        return () =>
            Object.keys(colors).forEach((k) =>
                root.style.removeProperty(`--${k}`),
            );
    }, [shown, active.id, defaults]);

    const activate = (id: string) =>
        router.post(
            `/farbkonzept/themes/${id}/aktivieren`,
            {},
            { preserveScroll: true, onSuccess: () => setPreview(id) },
        );
    const remove = (theme: ThemeData) => {
        if (
            window.confirm(
                t('Theme löschen? Das lässt sich nicht rückgängig machen.'),
            )
        ) {
            router.delete(`/farbkonzept/themes/${theme.id}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <Card data-testid="theme-selector">
            <CardHeader>
                <CardTitle>Themes</CardTitle>
                <CardDescription>
                    Gespeicherte Farbkonzepte. «Vorschau» färbt nur diese Seite
                    und nur für dich; «Für alle aktivieren» gilt im ganzen
                    Intranet.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <ul className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    {themes.map((theme) => (
                        <li
                            key={theme.id}
                            className={cn(
                                'grid gap-2 rounded-lg border p-3',
                                preview === theme.id && 'ring-2 ring-ring',
                            )}
                            data-testid={`theme-${theme.id}`}
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {theme.name}
                                </span>
                                {theme.active && (
                                    <Badge>
                                        <Check /> aktiv
                                    </Badge>
                                )}
                                {theme.builtin && (
                                    <Badge variant="outline">Vorgabe</Badge>
                                )}
                            </div>
                            {theme.description && (
                                <p className="text-xs text-muted-foreground">
                                    {theme.description}
                                </p>
                            )}
                            <Swatches theme={theme} defaults={defaults} />
                            <div className="flex flex-wrap gap-1">
                                <Button
                                    size="sm"
                                    variant={
                                        preview === theme.id
                                            ? 'secondary'
                                            : 'ghost'
                                    }
                                    onClick={() => setPreview(theme.id)}
                                    aria-pressed={preview === theme.id}
                                >
                                    <Eye /> Vorschau
                                </Button>
                                {!theme.active && (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => activate(theme.id)}
                                    >
                                        <Check /> Für alle aktivieren
                                    </Button>
                                )}
                                {!theme.builtin && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        asChild
                                        title="Bearbeiten"
                                    >
                                        <Link
                                            href={`/farbkonzept/themes/${theme.id}/bearbeiten`}
                                        >
                                            <Pencil />
                                            <span className="sr-only">
                                                Bearbeiten
                                            </span>
                                        </Link>
                                    </Button>
                                )}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    title="Kopieren"
                                >
                                    <Link
                                        href={`/farbkonzept/themes/neu?vorlage=${theme.id}`}
                                    >
                                        <Copy />
                                        <span className="sr-only">
                                            Kopieren
                                        </span>
                                    </Link>
                                </Button>
                                {!theme.builtin && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => remove(theme)}
                                        title="Löschen"
                                    >
                                        <Trash2 />
                                        <span className="sr-only">Löschen</span>
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
                <div className="flex flex-wrap items-end gap-2">
                    <div className="grid w-64 gap-1.5">
                        <label
                            htmlFor="theme-preset"
                            className="text-sm font-medium"
                        >
                            Neues Theme
                        </label>
                        <SelectField
                            id="theme-preset"
                            value={preset}
                            placeholder="Leer (nur Vorgabe)"
                            options={Object.fromEntries(
                                Object.entries(presets).map(([k, p]) => [
                                    k,
                                    `Vorlage: ${p.name}`,
                                ]),
                            )}
                            onChange={setPreset}
                        />
                    </div>
                    <Button asChild>
                        <Link
                            href={
                                preset
                                    ? `/farbkonzept/themes/neu?vorlage=${preset}`
                                    : '/farbkonzept/themes/neu'
                            }
                        >
                            <Plus /> Theme anlegen
                        </Link>
                    </Button>
                </div>
                <div className="grid gap-3 md:grid-cols-2">
                    {(['light', 'dark'] as const).map((mode) => (
                        <div key={mode} className="grid gap-1">
                            <span className="text-xs text-muted-foreground">
                                {mode === 'light'
                                    ? `Hell – ${shown.name}`
                                    : `Dunkel – ${shown.name}`}
                            </span>
                            <ThemeScope
                                colors={resolveColors(shown, defaults, mode)}
                                mode={mode}
                                className="rounded-lg"
                            >
                                <ThemeSample />
                            </ThemeScope>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
