import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    RotateCcw,
    Save,
    TriangleAlert,
} from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { ThemeSample, ThemeScope } from '@/components/theme-scope';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    CONTRAST_PAIRS,
    contrast,
    resolveColors,
    TOKEN_LABELS,
} from '@/lib/themes';
import type { Colors, Mode, ThemeDefaults } from '@/lib/themes';
import { cn } from '@/lib/utils';

type Editable = {
    id: string | null;
    name: string;
    description: string;
    light: Colors;
    dark: Colors;
};

type Props = {
    theme: Editable;
    defaults: ThemeDefaults;
    groups: Record<string, string[]>;
};

const MODES: { mode: Mode; label: string }[] = [
    { mode: 'light', label: 'Hell' },
    { mode: 'dark', label: 'Dunkel' },
];

/** Eine Farbe: Farbwähler, Hex-Wert, zurück zur Vorgabe. */
function ColorInput({
    id,
    value,
    fallback,
    onChange,
}: {
    id: string;
    value: string | undefined;
    fallback: string;
    onChange: (value: string | undefined) => void;
}) {
    const shown = value ?? fallback;

    return (
        <div className="flex items-center gap-1.5">
            <input
                type="color"
                value={shown}
                onChange={(e) => onChange(e.target.value)}
                className="h-7 w-9 shrink-0 cursor-pointer rounded border bg-transparent p-0.5"
                aria-label={id}
            />
            <Input
                id={id}
                value={value ?? ''}
                placeholder={fallback}
                onChange={(e) =>
                    onChange(
                        e.target.value.trim() === ''
                            ? undefined
                            : e.target.value.trim(),
                    )
                }
                className={cn(
                    'h-7 w-24 font-mono text-xs',
                    value !== undefined && 'border-primary',
                )}
                translate="no"
            />
            {value !== undefined && (
                <button
                    type="button"
                    onClick={() => onChange(undefined)}
                    title="Vorgabe"
                    className="rounded p-1 text-muted-foreground hover:bg-accent"
                >
                    <RotateCcw className="size-3.5" />
                    <span className="sr-only">Vorgabe</span>
                </button>
            )}
        </div>
    );
}

/** Theme anlegen oder bearbeiten: Farben hell/dunkel, Kontrastprüfung und Vorschau. */
export default function ThemeEditor({ theme, defaults, groups }: Props) {
    const form = useForm<Editable>({
        ...theme,
        light: { ...theme.light },
        dark: { ...theme.dark },
    });
    const errors = form.errors as Record<string, string | undefined>;

    const setColor = (mode: Mode, token: string, value: string | undefined) => {
        const next = { ...form.data[mode] };
        if (value === undefined) {
            delete next[token];
        } else {
            next[token] = value.toLowerCase();
        }
        form.setData(mode, next);
    };

    const valid = (mode: Mode) =>
        Object.values(form.data[mode]).every((v) => /^#[0-9a-f]{6}$/i.test(v));

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (theme.id) {
            form.put(`/farbkonzept/themes/${theme.id}`, {
                preserveScroll: true,
            });
        } else {
            form.post('/farbkonzept/themes', { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={theme.id ? theme.name : 'Neues Theme'} />
            <form
                onSubmit={submit}
                className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title={theme.id ? `Theme «${theme.name}»` : 'Neues Theme'}
                    description="Nur abweichende Farben eintragen; leere Felder nehmen die Vorgabe aus dem Farbkonzept. Werte als #rrggbb."
                    actions={
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href="/farbkonzept">
                                    <ArrowLeft /> Farbkonzept
                                </Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    !valid('light') ||
                                    !valid('dark')
                                }
                            >
                                <Save /> Speichern
                            </Button>
                        </div>
                    }
                />

                <Card>
                    <CardContent className="grid gap-4 pt-6 md:grid-cols-2">
                        <FormField
                            id="theme-name"
                            label="Name *"
                            error={errors.name}
                        >
                            <Input
                                id="theme-name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField id="theme-description" label="Beschreibung">
                            <Textarea
                                id="theme-description"
                                rows={2}
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                        </FormField>
                        <InputError
                            message={errors.light ?? errors.dark}
                            className="md:col-span-2"
                        />
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    {MODES.map(({ mode, label }) => {
                        const colors = resolveColors(form.data, defaults, mode);

                        return (
                            <div
                                key={mode}
                                className="grid content-start gap-4"
                            >
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{`Vorschau ${label}`}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <ThemeScope
                                            colors={colors}
                                            mode={mode}
                                            className="rounded-lg"
                                        >
                                            <ThemeSample />
                                        </ThemeScope>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{`Kontrast ${label}`}</CardTitle>
                                        <CardDescription>
                                            Mindestens 4,5 : 1 (WCAG AA) für
                                            Text.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ul
                                            className="grid gap-1 text-sm"
                                            data-testid={`contrast-${mode}`}
                                        >
                                            {CONTRAST_PAIRS.map((pair) => {
                                                const ratio = contrast(
                                                    colors[pair.fg] ?? '',
                                                    colors[pair.bg] ?? '',
                                                );
                                                const ok = ratio >= (pair.min ?? 4.5);

                                                return (
                                                    <li
                                                        key={pair.label}
                                                        className="flex items-center justify-between gap-2"
                                                    >
                                                        <span className="flex items-center gap-2">
                                                            {ok ? (
                                                                <CheckCircle2
                                                                    className="size-4 text-success"
                                                                    aria-hidden
                                                                />
                                                            ) : (
                                                                <TriangleAlert
                                                                    className="size-4 text-warning"
                                                                    aria-hidden
                                                                />
                                                            )}
                                                            <span>
                                                                {pair.label}
                                                            </span>
                                                        </span>
                                                        <span
                                                            className={cn(
                                                                'tabular-nums',
                                                                !ok &&
                                                                    'font-semibold text-warning',
                                                            )}
                                                        >
                                                            {`${ratio.toFixed(1)} : 1${ok ? '' : ' – zu wenig'}`}
                                                        </span>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </CardContent>
                                </Card>
                            </div>
                        );
                    })}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Farben</CardTitle>
                        <CardDescription>
                            Blau umrandet = abweichend von der Vorgabe. Pfeil =
                            zurück zur Vorgabe.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        {Object.entries(groups).map(([group, tokens]) => (
                            <section key={group} className="grid gap-2">
                                <h3 className="text-sm font-semibold">
                                    {group}
                                </h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead>
                                            <tr className="text-left text-xs text-muted-foreground">
                                                <th className="py-1 font-medium">
                                                    Farbwert
                                                </th>
                                                <th className="py-1 font-medium">
                                                    Hell
                                                </th>
                                                <th className="py-1 font-medium">
                                                    Dunkel
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {tokens.map((token) => (
                                                <tr
                                                    key={token}
                                                    className="border-t"
                                                >
                                                    <td className="py-1.5 pr-3">
                                                        <span className="grid">
                                                            <span>
                                                                {TOKEN_LABELS[
                                                                    token
                                                                ] ?? token}
                                                            </span>
                                                            <span
                                                                className="font-mono text-xs text-muted-foreground"
                                                                translate="no"
                                                            >
                                                                {`--${token}`}
                                                            </span>
                                                        </span>
                                                    </td>
                                                    {MODES.map(({ mode }) => (
                                                        <td
                                                            key={mode}
                                                            className="py-1.5 pr-3"
                                                        >
                                                            <ColorInput
                                                                id={`${mode}-${token}`}
                                                                value={
                                                                    form.data[
                                                                        mode
                                                                    ][token]
                                                                }
                                                                fallback={
                                                                    defaults[
                                                                        mode
                                                                    ][token] ??
                                                                    '#000000'
                                                                }
                                                                onChange={(v) =>
                                                                    setColor(
                                                                        mode,
                                                                        token,
                                                                        v,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        ))}
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

ThemeEditor.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Farbkonzept', href: '/farbkonzept' },
        {
            title: props.theme.id ? props.theme.name : 'Neues Theme',
            href: props.theme.id
                ? `/farbkonzept/themes/${props.theme.id}/bearbeiten`
                : '/farbkonzept/themes/neu',
        },
    ],
});
