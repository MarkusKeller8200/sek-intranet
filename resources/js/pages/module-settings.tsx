import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ExternalLink,
    Puzzle,
    RotateCcw,
    Save,
    ScanSearch,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FrameDiagnosis from '@/components/frame-diagnosis';
import type { FrameResult } from '@/components/frame-diagnosis';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import SelectField from '@/components/select-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { moduleIcon } from '@/lib/module-icons';

type Mode = 'embed' | 'both' | 'tab' | 'extension';

type Item = {
    key: string;
    title: string;
    icon: string;
    group: string;
    group_title: string;
    default_url: string | null;
    default_open: Mode;
    url: string | null;
    open: Mode;
    custom: boolean;
    allowed: boolean;
    description: string;
};

export const MODE_LABELS: Record<Mode, string> = {
    embed: 'Im Intranet (iFrame)',
    both: 'Im Intranet (iFrame) mit Knopf «Neuer Tab»',
    tab: 'Eigenes Fenster (neuer Tab)',
    extension: 'Über die Browser-Erweiterung',
};

function ItemCard({ item }: { item: Item }) {
    const Icon = moduleIcon(item.icon);
    const form = useForm<{ url: string; open: Mode }>({
        url: item.custom ? (item.url ?? '') : '',
        open: item.open,
    });

    const [check, setCheck] = useState<FrameResult | null>(null);
    const [checking, setChecking] = useState(false);
    const runCheck = async () => {
        setChecking(true);
        try {
            const res = await fetch(
                `/einstellungen/menuepunkt/${item.key}/pruefen`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );
            setCheck((await res.json()) as FrameResult);
        } catch {
            setCheck({ embeddable: null, reason: t('Prüfung nicht möglich.') });
        }
        setChecking(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/einstellungen/menuepunkt/${item.key}`, {
            preserveScroll: true,
        });
    };

    const reset = () => {
        if (
            !window.confirm(
                t(
                    'Vorgabe wiederherstellen? Die gespeicherte Einstellung wird entfernt.',
                ),
            )
        ) {
            return;
        }
        router.delete(`/einstellungen/menuepunkt/${item.key}`, {
            preserveScroll: true,
            onSuccess: () => form.setData({ url: '', open: item.default_open }),
        });
    };

    return (
        <Card data-testid={`setting-${item.key}`}>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    <Icon className="size-5 text-muted-foreground" />
                    <span translate="no">{item.title}</span>
                    {item.custom ? (
                        <Badge variant="outline">angepasst</Badge>
                    ) : (
                        <Badge variant="secondary">Vorgabe</Badge>
                    )}
                </CardTitle>
                {item.description && (
                    <CardDescription>{item.description}</CardDescription>
                )}
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={submit}
                    className="grid gap-4 md:grid-cols-[2fr_1fr_auto] md:items-end"
                >
                    <div className="grid gap-1.5">
                        <Label htmlFor={`url-${item.key}`}>Adresse (URL)</Label>
                        <Input
                            id={`url-${item.key}`}
                            type="url"
                            inputMode="url"
                            value={form.data.url}
                            placeholder={item.default_url ?? 'https://'}
                            onChange={(e) =>
                                form.setData('url', e.target.value)
                            }
                            aria-invalid={!!form.errors.url}
                            translate="no"
                        />
                        <InputError message={form.errors.url} />
                        <p className="text-xs text-muted-foreground">
                            {item.default_url ? (
                                <>
                                    Leer = Vorgabe{' '}
                                    <span className="font-mono" translate="no">
                                        {item.default_url}
                                    </span>
                                </>
                            ) : (
                                'Noch keine Adresse vorgegeben – hier eintragen.'
                            )}
                        </p>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`open-${item.key}`}>Öffnen</Label>
                        <SelectField
                            id={`open-${item.key}`}
                            value={form.data.open}
                            options={MODE_LABELS}
                            onChange={(v) => form.setData('open', v as Mode)}
                        />
                        <InputError message={form.errors.open} />
                        <p className="text-xs text-muted-foreground">
                            Vorgabe: {MODE_LABELS[item.default_open]}
                        </p>
                    </div>
                    <div className="flex gap-2 md:pb-6">
                        <Button type="submit" disabled={form.processing}>
                            <Save /> Speichern
                        </Button>
                        {item.custom && (
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={reset}
                                title="Vorgabe wiederherstellen"
                            >
                                <RotateCcw />
                                <span className="sr-only">
                                    Vorgabe wiederherstellen
                                </span>
                            </Button>
                        )}
                    </div>
                </form>
                <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                    <Link
                        href={`/einbindung/${item.key}`}
                        className="text-primary hover:underline"
                    >
                        Öffnen
                    </Link>
                    {item.url && (
                        <a
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-primary hover:underline"
                        >
                            In neuem Tab <ExternalLink className="size-3" />
                        </a>
                    )}
                    {item.allowed && (
                        <span>
                            Einbetten vom Anbieter bestätigt (keine automatische
                            Prüfung).
                        </span>
                    )}
                    {item.url && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={checking}
                            onClick={() => void runCheck()}
                            data-testid={`check-${item.key}`}
                        >
                            <ScanSearch /> Einbetten prüfen
                        </Button>
                    )}
                </div>
                {check && (
                    <div className="mt-3">
                        <FrameDiagnosis result={check} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Einstellungen → Fachanwendungen / Weitere Einbindungen. */
export default function ModuleSettingsPage({
    title,
    items,
}: {
    section: string;
    title: string;
    items: Item[];
}) {
    const groups = items.reduce<Record<string, Item[]>>((acc, item) => {
        (acc[item.group_title] ??= []).push(item);

        return acc;
    }, {});

    return (
        <>
            <Head title={title} />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={title}
                    description="Adresse und Art des Öffnens je Menüpunkt. Gespeicherte Werte gelten für alle und übersteuern die Vorgabe aus der Konfiguration."
                />
                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                    <Puzzle className="mt-0.5 size-4 shrink-0" />
                    <span>
                        «Über die Browser-Erweiterung» öffnet die Anwendung in
                        einem eigenen Tab, gruppiert mit dem Intranet; ohne
                        installierte Erweiterung erscheint ein Hinweis.{' '}
                        <Link
                            href="/einstellungen/browser-erweiterung"
                            className="text-primary hover:underline"
                        >
                            Browser-Erweiterung
                        </Link>
                    </span>
                </p>
                {Object.entries(groups).map(([group, list]) => (
                    <section key={group} className="grid gap-3">
                        <h2 className="text-sm font-semibold text-muted-foreground">
                            {group}
                        </h2>
                        {list.map((item) => (
                            <ItemCard key={item.key} item={item} />
                        ))}
                    </section>
                ))}
            </div>
        </>
    );
}

ModuleSettingsPage.layout = (props: { title: string; section: string }) => ({
    breadcrumbs: [
        { title: 'Einstellungen', href: `/einstellungen/${props.section}` },
        { title: props.title, href: `/einstellungen/${props.section}` },
    ],
});
