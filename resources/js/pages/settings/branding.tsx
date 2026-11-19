import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Palette, Save, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import AppWordmark from '@/components/app-wordmark';
import BrandWordmark from '@/components/brand-wordmark';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FileDrop } from '@/components/ui/file-drop';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Einstellung = {
    marke: 'eduksx' | 'markussx' | 'eigene';
    name: string;
    titel: string;
    symbole: 'eduksx' | 'standard';
    signatur: boolean;
    logo_hell: string | null;
    logo_dunkel: string | null;
    signet: string | null;
};

/**
 * Einstellungen → Erscheinungsbild (#216, White-Labeling): Marke links oben und auf der Anmeldeseite, eigenes Logo
 * und Signet, Name der Instanz. Das Farbkonzept (Theme) wird unter Farbkonzept gewählt.
 */
export default function BrandingSettings({
    einstellung,
    marken,
    symbole,
    slots,
    theme,
}: {
    einstellung: Einstellung;
    marken: Record<string, string>;
    symbole: Record<string, string>;
    slots: Record<string, string>;
    theme: { id: string; name: string };
}) {
    const { branding } = usePage().props;
    const form = useForm({
        marke: einstellung.marke,
        name: einstellung.name,
        titel: einstellung.titel,
        symbole: einstellung.symbole,
        signatur: einstellung.signatur,
    });
    const [dateien, setDateien] = useState<Record<string, File[]>>({});
    const [fehler, setFehler] = useState<Record<string, string>>({});

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/einstellungen/erscheinungsbild', { preserveScroll: true });
    };
    const hochladen = (slot: string) => {
        const datei = dateien[slot]?.[0];
        if (!datei) {
            return;
        }
        router.post(
            `/einstellungen/erscheinungsbild/${slot}`,
            { datei },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setDateien({ ...dateien, [slot]: [] });
                    setFehler({ ...fehler, [slot]: '' });
                },
                onError: (errors) =>
                    setFehler({ ...fehler, [slot]: errors.datei ?? '' }),
            },
        );
    };
    const entfernen = (slot: string) =>
        router.delete(`/einstellungen/erscheinungsbild/${slot}`, {
            preserveScroll: true,
        });
    const vorhanden = (slot: string) =>
        einstellung[slot as keyof Einstellung] !== null;

    return (
        <>
            <Head title="Erscheinungsbild" />
            <form
                onSubmit={submit}
                className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title="Erscheinungsbild"
                    description="Logo links oben und auf der Anmeldeseite. Ohne eigenes Logo zeigt das Intranet die Marke EduKSX."
                    actions={
                        <Button type="submit" disabled={form.processing}>
                            <Save /> Speichern
                        </Button>
                    }
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Marke</CardTitle>
                        <CardDescription>Aktuell angezeigt:</CardDescription>
                        <div className="w-64 max-w-full rounded-md border p-3">
                            <AppWordmark className="w-full" />
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <fieldset className="grid gap-2 sm:grid-cols-3">
                            <legend className="sr-only">Marke</legend>
                            {Object.entries(marken).map(([key, label]) => (
                                <label
                                    key={key}
                                    className={cn(
                                        'grid cursor-pointer gap-2 rounded-lg border p-3',
                                        form.data.marke === key &&
                                            'ring-2 ring-ring',
                                    )}
                                >
                                    <span className="flex items-center gap-2 text-sm font-medium">
                                        <input
                                            type="radio"
                                            name="marke"
                                            value={key}
                                            checked={form.data.marke === key}
                                            onChange={() =>
                                                form.setData(
                                                    'marke',
                                                    key as Einstellung['marke'],
                                                )
                                            }
                                        />
                                        {label}
                                    </span>
                                    {key === 'eduksx' && (
                                        <BrandWordmark
                                            marke="education"
                                            zusatz="Intranet"
                                            className="text-lg"
                                        />
                                    )}
                                    {key === 'markussx' && (
                                        <BrandWordmark
                                            marke="developer"
                                            className="text-lg"
                                        />
                                    )}
                                    {key === 'eigene' && (
                                        <span className="text-xs text-muted-foreground">
                                            {vorhanden('logo_hell')
                                                ? 'Hochgeladenes Logo'
                                                : 'Zuerst ein Logo hochladen'}
                                        </span>
                                    )}
                                </label>
                            ))}
                        </fieldset>
                        <InputError message={form.errors.marke} />
                        <div className="grid max-w-md gap-1.5">
                            <Label htmlFor="b-name">Name der Instanz</Label>
                            <Input
                                id="b-name"
                                value={form.data.name}
                                maxLength={80}
                                placeholder="z. B. Sekundarschule Musterhausen"
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Erscheint unter der Wortmarke und als
                                Bildbeschreibung des eigenen Logos.
                            </p>
                            <InputError message={form.errors.name} />
                        </div>
                        <div className="grid max-w-md gap-1.5">
                            <Label htmlFor="b-titel">App-Name</Label>
                            <Input
                                id="b-titel"
                                value={form.data.titel}
                                maxLength={60}
                                placeholder="leer = EduKSX-Intranet bzw. Name der Instanz"
                                onChange={(e) =>
                                    form.setData('titel', e.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                Im Browser-Tab und als Name der installierten
                                App.
                            </p>
                            <InputError message={form.errors.titel} />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Symbole und Signatur</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Symbole im Menü
                            </legend>
                            {Object.entries(symbole).map(([key, label]) => (
                                <label
                                    key={key}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="symbole"
                                        value={key}
                                        checked={form.data.symbole === key}
                                        onChange={() =>
                                            form.setData(
                                                'symbole',
                                                key as Einstellung['symbole'],
                                            )
                                        }
                                    />
                                    {label}
                                </label>
                            ))}
                        </fieldset>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.signatur}
                                onCheckedChange={(c) =>
                                    form.setData('signatur', c === true)
                                }
                            />
                            Entwicklersignatur «Developed by MarKusSXCH» unter
                            dem Logo anzeigen
                        </label>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Eigenes Logo</CardTitle>
                        <CardDescription>
                            SVG, PNG, JPG oder WebP, höchstens 1 MB. SVG ohne
                            Skripte und externe Verweise. Logo für dunkle
                            Flächen und Signet sind freiwillig.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        {Object.entries(slots).map(([slot, label]) => (
                            <div
                                key={slot}
                                className="grid content-start gap-2"
                            >
                                <span className="text-sm font-medium">
                                    {label}
                                </span>
                                {vorhanden(slot) && branding && (
                                    <div
                                        className={cn(
                                            'flex h-20 items-center justify-center rounded-md border p-2',
                                            slot === 'logo_dunkel' &&
                                                'dark bg-background',
                                        )}
                                    >
                                        <img
                                            src={`/branding/${slot}?v=${branding.version}`}
                                            alt={label}
                                            className="max-h-16 max-w-full"
                                        />
                                    </div>
                                )}
                                <FileDrop
                                    accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp"
                                    maxBytes={1024 * 1024}
                                    files={dateien[slot] ?? []}
                                    onFiles={(f) =>
                                        setDateien({ ...dateien, [slot]: f })
                                    }
                                />
                                <InputError message={fehler[slot]} />
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        disabled={!dateien[slot]?.length}
                                        onClick={() => hochladen(slot)}
                                    >
                                        <Upload /> Hochladen
                                    </Button>
                                    {vorhanden(slot) && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => entfernen(slot)}
                                        >
                                            <Trash2 /> Entfernen
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Farbkonzept</CardTitle>
                        <CardDescription>
                            Aktiv: <span translate="no">{theme.name}</span>.
                            Eingebaut sind die Farbkonzepte EduKSX und
                            MarKusSXCH; eigene Themes lassen sich anlegen oder
                            kopieren.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button asChild variant="outline">
                            <Link href="/farbkonzept">
                                <Palette /> Farbkonzept wählen
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

BrandingSettings.layout = {
    breadcrumbs: [
        { title: 'Erscheinungsbild', href: '/einstellungen/erscheinungsbild' },
    ],
};
