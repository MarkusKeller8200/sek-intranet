import { Head, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    GitBranchPlus,
    Lightbulb,
    RotateCcw,
    Send,
    ShieldCheck,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import GuiDemo from '@/components/gui-demos';
import OptionBadge from '@/components/option-badge';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDateTime } from '@/lib/format';
import type {
    GuiComponent,
    GuiRecord,
    GuiSummary,
    GuiVersion,
} from '@/lib/gui-prototypes';
import { nameOf } from '@/lib/gui-prototypes';
import { t } from '@/lib/i18n';
import { moduleIcon } from '@/lib/module-icons';

type Section = 'beispiele' | 'versionen' | 'ueberarbeitung' | 'wuensche';

type Props = {
    component: GuiComponent;
    record: GuiRecord;
    summary: GuiSummary;
    usages: string[];
    names: Record<string, string>;
};

/** Ein Status-Schritt einer Version mit Datum und Person. */
function Step({
    label,
    at,
    by,
    names,
}: {
    label: string;
    at?: string | null;
    by?: string | null;
    names: Record<string, string>;
}) {
    return (
        <div className="grid gap-0.5 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <span className="tabular-nums">
                {at ? `${formatDateTime(at)} · ${nameOf(names, by)}` : '–'}
            </span>
        </div>
    );
}

function VersionCard({
    version,
    current,
    names,
}: {
    version: GuiVersion;
    current: boolean;
    names: Record<string, string>;
}) {
    return (
        <Card data-testid={`version-${version.version}`}>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    <span className="tabular-nums">{`v${version.version}`}</span>
                    <OptionBadge list="guiStatuses" value={version.status} />
                    {version.status === 'freigegeben' &&
                        (current ? (
                            <Badge variant="outline">verbindlich</Badge>
                        ) : (
                            <Badge variant="secondary">abgelöst</Badge>
                        ))}
                </CardTitle>
                <CardDescription className="whitespace-pre-line">
                    {version.changes}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Step
                        label="Entwicklung"
                        at={version.created_at}
                        by={version.created_by}
                        names={names}
                    />
                    <Step
                        label="Prüfung"
                        at={version.review_at}
                        by={version.review_by}
                        names={names}
                    />
                    <Step
                        label="Freigegeben"
                        at={version.released_at}
                        by={version.released_by}
                        names={names}
                    />
                </div>
                {(version.log ?? []).some((l) => l.comment) && (
                    <ul className="grid gap-1 border-t pt-3 text-xs">
                        {(version.log ?? [])
                            .filter((l) => l.comment)
                            .map((l) => (
                                <li key={l.at} className="flex gap-2">
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatDateTime(l.at)}
                                    </span>
                                    <span>{`${nameOf(names, l.by)}: ${l.comment ?? ''}`}</span>
                                </li>
                            ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

function StatusActions({
    componentKey,
    open,
}: {
    componentKey: string;
    open: GuiVersion;
}) {
    const [comment, setComment] = useState('');
    const [busy, setBusy] = useState(false);

    const send = (action: 'pruefen' | 'zurueck' | 'freigeben') => {
        if (
            action === 'freigeben' &&
            !window.confirm(
                t(
                    'Freigeben? Diese Version wird verbindliches Template; alle verwendenden Seiten werden zur Überarbeitung vorgemerkt.',
                ),
            )
        ) {
            return;
        }
        setBusy(true);
        router.post(
            `/gui-prototypen/${componentKey}/status`,
            { action, comment: comment || null },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => setComment(''),
            },
        );
    };

    return (
        <Card data-testid="status-actions">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <span>{`Offene Version v${open.version}`}</span>
                    <OptionBadge list="guiStatuses" value={open.status} />
                </CardTitle>
                <CardDescription>
                    {open.status === 'entwicklung'
                        ? 'Wenn die Beispiele und der Code fertig sind: zur Prüfung geben.'
                        : 'Prüfen: Beispiele ansehen, Barrierefreiheit und Farbkonzept kontrollieren. Dann freigeben oder mit Bemerkung zurückgeben.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                <FormField id="gui-comment" label="Bemerkung (optional)">
                    <Textarea
                        id="gui-comment"
                        rows={2}
                        value={comment}
                        onChange={(e) => setComment(e.target.value)}
                    />
                </FormField>
                <div className="flex flex-wrap gap-2">
                    {open.status === 'entwicklung' && (
                        <Button disabled={busy} onClick={() => send('pruefen')}>
                            <Send /> Zur Prüfung geben
                        </Button>
                    )}
                    {open.status === 'pruefung' && (
                        <>
                            <Button
                                disabled={busy}
                                onClick={() => send('freigeben')}
                                data-testid="release"
                            >
                                <ShieldCheck /> Freigeben
                            </Button>
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => send('zurueck')}
                            >
                                <RotateCcw /> Zurück an Entwicklung
                            </Button>
                        </>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function NewVersion({
    componentKey,
    record,
}: {
    componentKey: string;
    record: GuiRecord;
}) {
    const form = useForm<{ bump: string; changes: string; wishes: string[] }>({
        bump: 'minor',
        changes: '',
        wishes: [],
    });
    const openWishes = record.wishes.filter((w) => !w.version);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/gui-prototypen/${componentKey}/versionen`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <GitBranchPlus className="size-5 text-muted-foreground" />
                    <span>Neue Version anlegen</span>
                </CardTitle>
                <CardDescription>
                    Startet in «Entwicklung». Es kann immer nur eine Version
                    offen sein.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4">
                    <FormField
                        id="gui-bump"
                        label="Art der Änderung"
                        className="max-w-sm"
                    >
                        <SelectField
                            id="gui-bump"
                            value={form.data.bump}
                            options={{
                                patch: 'Korrektur (x.x.+1)',
                                minor: 'Erweiterung (x.+1.0)',
                                major: 'Grundlegend neu (+1.0.0)',
                            }}
                            onChange={(v) => form.setData('bump', v)}
                        />
                    </FormField>
                    <FormField
                        id="gui-changes"
                        label="Was ändert sich? *"
                        error={form.errors.changes}
                    >
                        <Textarea
                            id="gui-changes"
                            rows={3}
                            value={form.data.changes}
                            onChange={(e) =>
                                form.setData('changes', e.target.value)
                            }
                        />
                    </FormField>
                    {openWishes.length > 0 && (
                        <div className="grid gap-2">
                            <Label>Wünsche übernehmen</Label>
                            {openWishes.map((w) => (
                                <div
                                    key={w.id}
                                    className="flex items-start gap-2"
                                >
                                    <Checkbox
                                        id={`wish-${w.id}`}
                                        checked={form.data.wishes.includes(
                                            w.id,
                                        )}
                                        onCheckedChange={(
                                            c: boolean | 'indeterminate',
                                        ) =>
                                            form.setData(
                                                'wishes',
                                                c === true
                                                    ? [
                                                          ...form.data.wishes,
                                                          w.id,
                                                      ]
                                                    : form.data.wishes.filter(
                                                          (id) => id !== w.id,
                                                      ),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`wish-${w.id}`}
                                        className="font-normal"
                                    >
                                        {w.text}
                                    </Label>
                                </div>
                            ))}
                        </div>
                    )}
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            <GitBranchPlus /> Version anlegen
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function Wishes({
    componentKey,
    record,
    names,
}: {
    componentKey: string;
    record: GuiRecord;
    names: Record<string, string>;
}) {
    const form = useForm({ text: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/gui-prototypen/${componentKey}/wuensche`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Lightbulb className="size-5 text-muted-foreground" />
                        <span>Verbesserungswunsch erfassen</span>
                    </CardTitle>
                    <CardDescription>
                        Wünsche sammeln sich hier und werden beim Anlegen der
                        nächsten Version übernommen.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="grid gap-3">
                        <FormField
                            id="gui-wish"
                            label="Wunsch *"
                            error={form.errors.text}
                        >
                            <Textarea
                                id="gui-wish"
                                rows={2}
                                value={form.data.text}
                                onChange={(e) =>
                                    form.setData('text', e.target.value)
                                }
                            />
                        </FormField>
                        <div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={form.processing}
                            >
                                <Lightbulb /> Wunsch erfassen
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
            {record.wishes.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Noch keine Wünsche.
                </p>
            ) : (
                <ul className="grid gap-2">
                    {[...record.wishes].reverse().map((w) => (
                        <li
                            key={w.id}
                            className="rounded-md border p-3 text-sm"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                {w.version ? (
                                    <Badge variant="outline">{`in v${w.version}`}</Badge>
                                ) : (
                                    <Badge variant="secondary">offen</Badge>
                                )}
                                <span className="text-xs text-muted-foreground">
                                    {`${formatDateTime(w.at)} · ${nameOf(names, w.by)}`}
                                </span>
                            </div>
                            <p className="mt-1 whitespace-pre-line">{w.text}</p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Rework({
    componentKey,
    record,
    usages,
    summary,
    names,
}: Omit<Props, 'component'> & { componentKey: string }) {
    const toggle = (file: string, done: boolean) =>
        router.post(
            `/gui-prototypen/${componentKey}/ueberarbeitung`,
            { file, done },
            { preserveScroll: true },
        );

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {summary.released
                            ? `Anpassen an v${summary.released.version}`
                            : 'Noch keine Freigabe'}
                    </CardTitle>
                    <CardDescription>
                        {summary.released
                            ? 'Diese Seiten verwendeten den Baustein bei der Freigabe. Jede Seite prüfen und an das verbindliche Template anpassen, dann abhaken.'
                            : 'Nach der ersten Freigabe erscheinen hier alle Seiten, die den Baustein verwenden.'}
                    </CardDescription>
                </CardHeader>
                {record.rework.length > 0 && (
                    <CardContent>
                        <ul className="grid gap-2" data-testid="rework-list">
                            {record.rework.map((r) => (
                                <li
                                    key={r.file}
                                    className="flex items-start gap-2 text-sm"
                                >
                                    <Checkbox
                                        id={`rw-${r.file}`}
                                        checked={r.done_at !== null}
                                        onCheckedChange={(
                                            c: boolean | 'indeterminate',
                                        ) => toggle(r.file, c === true)}
                                    />
                                    <div className="grid gap-0.5">
                                        <Label
                                            htmlFor={`rw-${r.file}`}
                                            className="font-mono text-xs font-normal"
                                            translate="no"
                                        >
                                            {r.file}
                                        </Label>
                                        {r.done_at && (
                                            <span className="text-xs text-muted-foreground">
                                                {`überarbeitet ${formatDateTime(r.done_at)} · ${nameOf(names, r.done_by)}`}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                )}
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Verwendet heute in</CardTitle>
                    <CardDescription>
                        Seiten, die den Baustein derzeit importieren
                        (automatisch gesucht).
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {usages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Keine Seiten gefunden.
                        </p>
                    ) : (
                        <ul
                            className="grid gap-1 font-mono text-xs"
                            translate="no"
                        >
                            {usages.map((u) => (
                                <li key={u}>{u}</li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

/** GUI-Prototyp: Beispiele, Versionen mit Status, Überarbeitung und Wünsche eines Bausteins. */
export default function GuiPrototypeShow(props: Props) {
    const { component, record, summary, names } = props;
    const [section, setSection] = useState<Section>('beispiele');
    const Icon = moduleIcon(component.icon);
    const versions = [...record.versions].reverse();

    return (
        <>
            <Head title={component.title} />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={component.title}
                    description={component.description}
                />

                <div className="flex flex-wrap items-center gap-3 text-sm">
                    <Icon className="size-5 text-muted-foreground" />
                    {summary.released ? (
                        <span
                            className="flex items-center gap-2"
                            data-testid="binding"
                        >
                            <CheckCircle2 className="size-4 text-success" />
                            <span>{`Verbindlich: v${summary.released.version} (${formatDateTime(summary.released.released_at ?? null)})`}</span>
                        </span>
                    ) : (
                        <span className="text-muted-foreground">
                            Noch keine freigegebene Version
                        </span>
                    )}
                    {summary.open && (
                        <span className="flex items-center gap-2">
                            <span>{`In Arbeit: v${summary.open.version}`}</span>
                            <OptionBadge
                                list="guiStatuses"
                                value={summary.open.status}
                            />
                        </span>
                    )}
                    {summary.rework_open > 0 && (
                        <Badge variant="secondary">
                            {summary.rework_open === 1
                                ? '1 Seite anzupassen'
                                : `${summary.rework_open} Seiten anzupassen`}
                        </Badge>
                    )}
                </div>

                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={section}
                    onValueChange={(v: string) => v && setSection(v as Section)}
                    className="justify-start"
                >
                    <ToggleGroupItem value="beispiele" className="px-4">
                        Beispiele
                    </ToggleGroupItem>
                    <ToggleGroupItem value="versionen" className="px-4">
                        Versionen &amp; Status
                    </ToggleGroupItem>
                    <ToggleGroupItem value="ueberarbeitung" className="px-4">
                        Überarbeitung
                    </ToggleGroupItem>
                    <ToggleGroupItem value="wuensche" className="px-4">
                        Wünsche
                    </ToggleGroupItem>
                </ToggleGroup>

                {section === 'beispiele' && (
                    <GuiDemo componentKey={component.key} />
                )}

                {section === 'versionen' && (
                    <div className="grid gap-4">
                        {summary.open ? (
                            <StatusActions
                                componentKey={component.key}
                                open={summary.open}
                            />
                        ) : (
                            <NewVersion
                                componentKey={component.key}
                                record={record}
                            />
                        )}
                        {versions.map((v) => (
                            <VersionCard
                                key={v.version}
                                version={v}
                                current={
                                    summary.released?.version === v.version
                                }
                                names={names}
                            />
                        ))}
                    </div>
                )}

                {section === 'ueberarbeitung' && (
                    <Rework {...props} componentKey={component.key} />
                )}

                {section === 'wuensche' && (
                    <Wishes
                        componentKey={component.key}
                        record={record}
                        names={names}
                    />
                )}
            </div>
        </>
    );
}

GuiPrototypeShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'GUI-Prototypen', href: '/gui-prototypen' },
        {
            title: props.component.title,
            href: `/gui-prototypen/${props.component.key}`,
        },
    ],
});
