import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ExternalLink,
    FlaskConical,
    Play,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import DataNotice from '@/components/plugins/data-notice';
import PluginFields, {
    initialValues,
} from '@/components/plugins/plugin-fields';
import type { FieldValues } from '@/components/plugins/plugin-fields';
import PluginResultView from '@/components/plugins/plugin-result';
import PluginStatus, {
    categoryLabels,
} from '@/components/plugins/plugin-status';
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDateTime } from '@/lib/format';
import { postJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import type {
    PluginAction,
    PluginEndpoint,
    PluginInfo,
    PluginResult,
    PluginRun,
} from '@/types';

type Section = 'settings' | 'actions' | 'endpoints' | 'log';

const kindLabels: Record<PluginAction['kind'], string> = {
    read: 'lesen',
    import: 'übernehmen',
    write: 'schreibend',
};

const endpointStatus: Record<string, { label: string; className: string }> = {
    full: {
        label: 'umgesetzt',
        className: 'border-success/30 bg-success-soft text-success',
    },
    partial: {
        label: 'teilweise',
        className: 'border-info/30 bg-info-soft text-info',
    },
    planned: { label: 'geplant', className: '' },
};

export default function PluginShow({
    plugin,
    values,
    secretsSet,
    runs,
    serverInSwitzerland,
    recordings = [],
}: {
    plugin: PluginInfo;
    values: FieldValues;
    secretsSet: string[];
    runs: PluginRun[];
    serverInSwitzerland: boolean;
    recordings?: Recording[];
}) {
    const [section, setSection] = useState<Section>('settings');
    const [testResult, setTestResult] = useState<PluginResult | null>(null);
    const [testing, setTesting] = useState(false);
    const [testRun, setTestRun] = useState(0);

    const ready = plugin.enabled && plugin.configured;

    const runTest = async () => {
        setTesting(true);
        setTestResult(
            await postJson<PluginResult>(`/plugins/${plugin.id}/test`),
        );
        setTestRun((n) => n + 1);
        setTesting(false);
        router.reload({ only: ['runs'] });
    };

    return (
        <>
            <Head title={plugin.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <Button
                    variant="ghost"
                    size="sm"
                    className="justify-self-start"
                    asChild
                >
                    <Link href="/plugins">
                        <ArrowLeft /> Alle Plugins
                    </Link>
                </Button>

                <PageHeader
                    title={plugin.name}
                    description={plugin.description}
                    actions={
                        <>
                            <Button
                                variant={plugin.enabled ? 'outline' : 'default'}
                                onClick={() =>
                                    router.post(
                                        `/plugins/${plugin.id}/schalter`,
                                        { enabled: !plugin.enabled },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {plugin.enabled ? 'Ausschalten' : 'Einschalten'}
                            </Button>
                            <Button
                                variant="outline"
                                disabled={!ready || testing}
                                onClick={() => void runTest()}
                            >
                                <FlaskConical /> Testen
                            </Button>
                        </>
                    }
                />

                <div className="flex flex-wrap items-center gap-2 text-sm">
                    <PluginStatus plugin={plugin} />
                    <Badge variant="secondary">
                        {categoryLabels[plugin.category] ?? plugin.category}
                    </Badge>
                    <span className="text-muted-foreground">
                        v{plugin.version}
                    </span>
                    {plugin.service.docs && (
                        <a
                            href={plugin.service.docs}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 text-primary hover:underline"
                        >
                            API-Doku <ExternalLink className="size-3.5" />
                        </a>
                    )}
                    {plugin.events.length > 0 && (
                        <span className="text-muted-foreground">
                            Reagiert auf: {plugin.events.join(', ')}
                        </span>
                    )}
                </div>

                {!serverInSwitzerland && (
                    <DataNotice notice={plugin.data_notice} />
                )}

                {testResult && (
                    <PluginResultView key={testRun} result={testResult} />
                )}

                <ToggleGroup
                    type="single"
                    value={section}
                    onValueChange={(v: string) => v && setSection(v as Section)}
                    variant="outline"
                    className="flex-wrap justify-start"
                >
                    <ToggleGroupItem value="settings" className="px-4">
                        Einstellungen
                    </ToggleGroupItem>
                    <ToggleGroupItem value="actions" className="px-4">
                        Aktionen ({plugin.actions.length})
                    </ToggleGroupItem>
                    <ToggleGroupItem value="endpoints" className="px-4">
                        Endpunkte &amp; Tester
                    </ToggleGroupItem>
                    <ToggleGroupItem value="log" className="px-4">
                        Protokoll
                    </ToggleGroupItem>
                </ToggleGroup>

                {section === 'settings' && (
                    <SettingsSection
                        plugin={plugin}
                        values={values}
                        secretsSet={secretsSet}
                    />
                )}
                {section === 'actions' && (
                    <ActionsSection plugin={plugin} ready={ready} />
                )}
                {section === 'endpoints' && (
                    <>
                        <RecordingsCard recordings={recordings} />
                        <EndpointsSection plugin={plugin} ready={ready} />
                    </>
                )}
                {section === 'log' && <LogSection runs={runs} />}
            </div>
        </>
    );
}

type Recording = {
    id: string;
    title: string;
    operations: {
        key: string;
        method: string;
        path: string;
        summary: string;
        endpoint: string | null;
    }[];
};

/** Aufgezeichnete APIs (API-Recorder), die diesem Plugin zugewiesen sind: fehlende Endpunkte. */
function RecordingsCard({ recordings }: { recordings: Recording[] }) {
    if (recordings.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Aufgezeichnete Endpunkte (API-Recorder)</CardTitle>
                <CardDescription>
                    Mit dem API-Recorder erfasste Endpunkte. «fehlt» = noch
                    nicht im Plugin; der Entwurf lässt sich in plugin.json
                    übernehmen.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {recordings.map((rec) => {
                    const missing = rec.operations.filter(
                        (o) => o.endpoint === null,
                    );

                    return (
                        <div
                            key={rec.id}
                            className="grid gap-2"
                            data-testid="recording"
                        >
                            <div className="flex flex-wrap items-center gap-2 text-sm">
                                <Link
                                    href={`/api-recorder/${rec.id}`}
                                    className="font-medium text-primary hover:underline"
                                    translate="no"
                                >
                                    {rec.title}
                                </Link>
                                <Badge variant="outline">
                                    {`${rec.operations.length - missing.length} von ${rec.operations.length} im Plugin`}
                                </Badge>
                                {missing.length > 0 && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="ml-auto"
                                        asChild
                                    >
                                        <a
                                            href={`/api-recorder/${rec.id}/plugin-entwurf`}
                                        >
                                            Entwurf für plugin.json
                                        </a>
                                    </Button>
                                )}
                            </div>
                            <ul className="grid gap-1 text-xs">
                                {rec.operations.map((op) => (
                                    <li
                                        key={op.key}
                                        className="flex items-center gap-2"
                                    >
                                        <code
                                            className="w-14 shrink-0 font-semibold"
                                            translate="no"
                                        >
                                            {op.method}
                                        </code>
                                        <code
                                            className="truncate"
                                            translate="no"
                                        >
                                            {op.path}
                                        </code>
                                        <span className="truncate text-muted-foreground">
                                            {op.summary}
                                        </span>
                                        <span className="ml-auto shrink-0">
                                            {op.endpoint ? (
                                                <code
                                                    className="text-emerald-700 dark:text-emerald-400"
                                                    translate="no"
                                                >
                                                    {op.endpoint}
                                                </code>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-amber-300 text-amber-800 dark:text-amber-300"
                                                >
                                                    fehlt
                                                </Badge>
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

function SettingsSection({
    plugin,
    values,
    secretsSet,
}: {
    plugin: PluginInfo;
    values: FieldValues;
    secretsSet: string[];
}) {
    const form = useForm<FieldValues>({
        ...initialValues(plugin.settings),
        ...values,
        _allow_write: plugin.allow_write,
        _clear_secrets: false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/plugins/${plugin.id}/einstellungen`, {
            preserveScroll: true,
            onSuccess: () =>
                form.setData((d) => ({
                    ...d,
                    ...Object.fromEntries(
                        plugin.settings
                            .filter((s) => s.type === 'password')
                            .map((s) => [s.key, '']),
                    ),
                    _clear_secrets: false,
                })),
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Einstellungen</CardTitle>
                <CardDescription>
                    {plugin.service.auth_note ??
                        'Zugangsdaten werden verschlüsselt gespeichert und nie angezeigt.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-5">
                    <PluginFields
                        prefix="setting"
                        fields={plugin.settings}
                        values={form.data}
                        secretsSet={secretsSet}
                        onChange={(key, value) => form.setData(key, value)}
                    />

                    <div className="grid gap-3 rounded-md border p-3">
                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="allow-write"
                                checked={form.data._allow_write === true}
                                onCheckedChange={(
                                    c: boolean | 'indeterminate',
                                ) => form.setData('_allow_write', c === true)}
                            />
                            <div className="grid gap-0.5">
                                <Label htmlFor="allow-write">
                                    Schreibende Aktionen erlauben
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Nötig für «Übernehmen» ins Intranet und für
                                    Aktionen, die im anderen System etwas
                                    ändern. Jede solche Aktion fragt zusätzlich
                                    nach.
                                </p>
                            </div>
                        </div>
                        {secretsSet.length > 0 && (
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="clear-secrets"
                                    checked={form.data._clear_secrets === true}
                                    onCheckedChange={(
                                        c: boolean | 'indeterminate',
                                    ) =>
                                        form.setData(
                                            '_clear_secrets',
                                            c === true,
                                        )
                                    }
                                />
                                <Label htmlFor="clear-secrets">
                                    Gespeicherte Zugangsdaten löschen
                                </Label>
                            </div>
                        )}
                    </div>

                    <InputError
                        message={
                            (form.errors as Record<string, string>).settings
                        }
                    />

                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Speichern
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

function ActionsSection({
    plugin,
    ready,
}: {
    plugin: PluginInfo;
    ready: boolean;
}) {
    const [active, setActive] = useState<string | null>(
        plugin.actions[0]?.id ?? null,
    );
    const action = plugin.actions.find((a) => a.id === active) ?? null;

    if (plugin.actions.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Dieses Plugin bietet keine Aktionen an.
            </p>
        );
    }

    return (
        <div className="grid gap-4 lg:grid-cols-[18rem_1fr]">
            <div className="grid content-start gap-2">
                {plugin.actions.map((a) => (
                    <button
                        key={a.id}
                        type="button"
                        onClick={() => setActive(a.id)}
                        className={cn(
                            'grid gap-1 rounded-lg border p-3 text-left text-sm transition-colors hover:bg-accent',
                            active === a.id && 'border-primary bg-accent',
                        )}
                    >
                        <span className="flex items-center gap-2 font-medium">
                            {a.kind === 'write' && (
                                <TriangleAlert className="size-4 text-warning" />
                            )}
                            {a.label}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {kindLabels[a.kind]}
                        </span>
                    </button>
                ))}
            </div>
            {action && (
                <ActionRunner
                    key={action.id}
                    plugin={plugin}
                    action={action}
                    ready={ready}
                />
            )}
        </div>
    );
}

function ActionRunner({
    plugin,
    action,
    ready,
}: {
    plugin: PluginInfo;
    action: PluginAction;
    ready: boolean;
}) {
    const [options, setOptions] = useState<FieldValues>(
        initialValues(action.options ?? []),
    );
    const [files, setFiles] = useState<Record<string, File>>({});
    const [result, setResult] = useState<PluginResult | null>(null);
    const [busy, setBusy] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const missingFile = (action.options ?? []).some(
        (o) => o.type === 'file' && o.required === true && !files[o.key],
    );
    const [pendingSelection, setPendingSelection] = useState<string[] | null>(
        null,
    );

    const run = async (apply = false, selected: string[] = []) => {
        setBusy(true);
        const res = await postJson<PluginResult>(
            `/plugins/${plugin.id}/aktion/${action.id}`,
            {
                options,
                apply,
                selected,
            },
            files,
        );
        setResult(res);
        setBusy(false);
        router.reload({ only: ['runs'] });
    };

    const start = () => {
        if (action.kind === 'write') {
            setPendingSelection(null);
            setConfirmOpen(true);
        } else {
            void run();
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>{action.label}</CardTitle>
                {action.description && (
                    <CardDescription>{action.description}</CardDescription>
                )}
            </CardHeader>
            <CardContent className="grid gap-5">
                {(action.options ?? []).length > 0 && (
                    <PluginFields
                        prefix={`action-${action.id}`}
                        fields={action.options ?? []}
                        values={options}
                        onChange={(key, value) =>
                            setOptions((o) => ({ ...o, [key]: value }))
                        }
                        onFile={(key, file) => {
                            setResult(null);
                            setFiles((current) => ({
                                ...Object.fromEntries(
                                    Object.entries(current).filter(
                                        ([k]) => k !== key,
                                    ),
                                ),
                                ...(file ? { [key]: file } : {}),
                            }));
                        }}
                    />
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        disabled={!ready || busy || missingFile}
                        onClick={start}
                        variant={
                            action.kind === 'write' ? 'destructive' : 'default'
                        }
                    >
                        <Play />{' '}
                        {busy
                            ? 'Läuft …'
                            : (action.run_label ??
                              (action.kind === 'import'
                                  ? 'Vorschau anzeigen'
                                  : 'Ausführen'))}
                    </Button>
                    {!ready && (
                        <span className="text-sm text-muted-foreground">
                            Zuerst einschalten und einrichten.
                        </span>
                    )}
                </div>

                {result && (
                    <PluginResultView
                        key={
                            JSON.stringify(result.rows?.length) + result.message
                        }
                        result={result}
                        canApply={plugin.allow_write}
                        applying={busy}
                        onApply={(selected) => {
                            setPendingSelection(selected);
                            setConfirmOpen(true);
                        }}
                    />
                )}

                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {pendingSelection
                                    ? `${pendingSelection.length} Einträge übernehmen?`
                                    : `${action.label}?`}
                            </DialogTitle>
                            <DialogDescription>
                                {action.confirm ??
                                    (pendingSelection
                                        ? 'Die ausgewählten Einträge werden im Intranet angelegt bzw. geändert. Gelöscht wird nichts.'
                                        : 'Diese Aktion ändert Daten im angeschlossenen System.')}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Abbrechen</Button>
                            </DialogClose>
                            <Button
                                onClick={() => {
                                    setConfirmOpen(false);
                                    void run(
                                        pendingSelection !== null ||
                                            action.kind === 'write',
                                        pendingSelection ?? [],
                                    );
                                }}
                            >
                                Ja, ausführen
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}

function EndpointsSection({
    plugin,
    ready,
}: {
    plugin: PluginInfo;
    ready: boolean;
}) {
    const endpoints = plugin.service.endpoints;
    const [endpointId, setEndpointId] = useState(
        endpoints.find((e) => e.status !== 'planned')?.id ??
            endpoints[0]?.id ??
            '',
    );
    const endpoint = endpoints.find((e) => e.id === endpointId);
    const [access, setAccess] = useState<PluginResult | null>(null);
    const [checking, setChecking] = useState(false);

    const checkAccess = async () => {
        setChecking(true);
        setAccess(
            await postJson<PluginResult>(`/plugins/${plugin.id}/zugriff`),
        );
        setChecking(false);
    };

    return (
        <div className="grid gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>{plugin.service.name}</CardTitle>
                    <CardDescription>
                        Anmeldung: {plugin.service.auth}
                        {plugin.service.home && (
                            <>
                                {' · '}
                                <a
                                    href={plugin.service.home}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-primary hover:underline"
                                >
                                    {plugin.service.home}
                                </a>
                            </>
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Endpunkt</TableHead>
                                <TableHead>Aufruf</TableHead>
                                <TableHead>Stand</TableHead>
                                <TableHead>Verwendung</TableHead>
                                <TableHead>Berechtigung</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {endpoints.map((e) => {
                                const status =
                                    endpointStatus[e.status ?? 'full'];

                                return (
                                    <TableRow
                                        key={e.id}
                                        className="cursor-pointer"
                                        onClick={() => setEndpointId(e.id)}
                                        data-state={
                                            e.id === endpointId
                                                ? 'selected'
                                                : undefined
                                        }
                                    >
                                        <TableCell className="font-medium">
                                            {e.danger && (
                                                <TriangleAlert className="mr-1 inline size-4 text-warning" />
                                            )}
                                            {e.label}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {e.method} {e.path}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={status?.className}
                                            >
                                                {status?.label ?? e.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell
                                            className="max-w-64 truncate text-xs"
                                            title={e.use}
                                        >
                                            {e.use ?? '–'}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {e.permission ?? '–'}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            variant="outline"
                            disabled={!ready || checking}
                            onClick={() => void checkAccess()}
                        >
                            <ShieldCheck />{' '}
                            {checking ? 'Prüft …' : 'Zugriff prüfen'}
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Ruft jeden lesenden Endpunkt einmal auf (nur GET).
                        </span>
                    </div>
                    {access && (
                        <PluginResultView
                            key={access.message}
                            result={access}
                        />
                    )}
                </CardContent>
            </Card>

            {endpoint && (
                <ApiTester
                    key={endpoint.id}
                    plugin={plugin}
                    endpoint={endpoint}
                    endpoints={endpoints}
                    ready={ready}
                    onSelect={setEndpointId}
                />
            )}
        </div>
    );
}

function ApiTester({
    plugin,
    endpoint,
    endpoints,
    ready,
    onSelect,
}: {
    plugin: PluginInfo;
    endpoint: PluginEndpoint;
    endpoints: PluginEndpoint[];
    ready: boolean;
    onSelect: (id: string) => void;
}) {
    const [params, setParams] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            (endpoint.params ?? []).map((p) => [
                p.name,
                p.default !== undefined && p.default !== null
                    ? String(p.default)
                    : '',
            ]),
        ),
    );
    const [body, setBody] = useState(
        endpoint.body ? JSON.stringify(endpoint.body, null, 2) : '',
    );
    const [result, setResult] = useState<PluginResult | null>(null);
    const [busy, setBusy] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const writing = endpoint.method.toUpperCase() !== 'GET';

    const send = async () => {
        setBusy(true);
        setResult(
            await postJson<PluginResult>(`/plugins/${plugin.id}/api`, {
                endpoint: endpoint.id,
                params,
                body: writing ? body : null,
            }),
        );
        setBusy(false);
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>API-Tester</CardTitle>
                <CardDescription>
                    Ruft einen Endpunkt mit den gespeicherten Zugangsdaten auf.
                    Zugangsdaten in der Antwort werden geschwärzt.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2 md:max-w-md">
                    <Label htmlFor="tester-endpoint">Endpunkt</Label>
                    <SelectField
                        id="tester-endpoint"
                        value={endpoint.id}
                        options={Object.fromEntries(
                            endpoints.map((e) => [
                                e.id,
                                `${e.method} ${e.label}`,
                            ]),
                        )}
                        onChange={onSelect}
                    />
                    <p className="font-mono text-xs text-muted-foreground">
                        {endpoint.method} {endpoint.path}
                    </p>
                    {endpoint.note && (
                        <p className="text-xs text-muted-foreground">
                            {endpoint.note}
                        </p>
                    )}
                </div>

                {(endpoint.params ?? []).length > 0 && (
                    <div className="grid gap-3 md:grid-cols-2">
                        {(endpoint.params ?? []).map((p) => (
                            <div key={p.name} className="grid gap-1.5">
                                <Label
                                    htmlFor={`param-${p.name}`}
                                    className="font-mono text-xs"
                                >
                                    {p.name}
                                    {p.required ? ' *' : ''}
                                </Label>
                                <Input
                                    id={`param-${p.name}`}
                                    value={params[p.name] ?? ''}
                                    placeholder={p.example}
                                    onChange={(e) =>
                                        setParams((s) => ({
                                            ...s,
                                            [p.name]: e.target.value,
                                        }))
                                    }
                                />
                                {p.help && (
                                    <p className="text-xs text-muted-foreground">
                                        {p.help}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {writing && (
                    <div className="grid gap-1.5">
                        <Label htmlFor="tester-body">Body (JSON)</Label>
                        <Textarea
                            id="tester-body"
                            rows={6}
                            className="font-mono text-xs"
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                        />
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        disabled={
                            !ready ||
                            busy ||
                            endpoint.status === 'planned' ||
                            (writing && !plugin.allow_write)
                        }
                        variant={writing ? 'destructive' : 'default'}
                        onClick={() =>
                            writing ? setConfirmOpen(true) : void send()
                        }
                    >
                        <Play /> {busy ? 'Sendet …' : 'Senden'}
                    </Button>
                    {endpoint.status === 'planned' && (
                        <span className="text-sm text-muted-foreground">
                            Geplant, noch nicht umgesetzt.
                        </span>
                    )}
                    {writing && !plugin.allow_write && (
                        <span className="text-sm text-muted-foreground">
                            Schreibend: erst nach Freigabe in den Einstellungen.
                        </span>
                    )}
                </div>

                {result && (
                    <div className="grid gap-2">
                        <PluginResultView
                            key={(result.ms ?? 0) + result.message}
                            result={result}
                        />
                        {result.body && (
                            <pre className="max-h-[32rem] overflow-auto rounded-md bg-muted p-3 text-xs">
                                {result.body}
                            </pre>
                        )}
                    </div>
                )}

                <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Schreibenden Aufruf senden?
                            </DialogTitle>
                            <DialogDescription>
                                {endpoint.method} {endpoint.path} ändert Daten
                                in {plugin.service.name}. Der Aufruf wird
                                protokolliert.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Abbrechen</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    setConfirmOpen(false);
                                    void send();
                                }}
                            >
                                Senden
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}

function LogSection({ runs }: { runs: PluginRun[] }) {
    const [open, setOpen] = useState<string | null>(null);

    if (runs.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">Noch keine Läufe.</p>
        );
    }

    return (
        <Card>
            <CardContent className="grid gap-2 pt-6">
                {runs.map((run) => (
                    <div
                        key={run.id}
                        className="grid gap-1 rounded-md border p-3 text-sm"
                    >
                        <button
                            type="button"
                            className="flex flex-wrap items-center gap-2 text-left"
                            onClick={() =>
                                setOpen(open === run.id ? null : run.id)
                            }
                        >
                            <Badge
                                variant="outline"
                                className={cn(
                                    run.status === 'error' &&
                                        'border-destructive/30 bg-destructive/10 text-destructive-foreground',
                                    run.status === 'success' &&
                                        'border-success/30 bg-success-soft text-success',
                                    run.status === 'warning' &&
                                        'border-warning/30 bg-warning-soft text-warning',
                                )}
                            >
                                {run.status === 'error'
                                    ? 'Fehler'
                                    : run.status === 'warning'
                                      ? 'Warnung'
                                      : 'ok'}
                            </Badge>
                            <span className="font-medium">{run.label}</span>
                            <span className="text-muted-foreground">
                                {run.message}
                            </span>
                            <span className="ml-auto text-xs text-muted-foreground">
                                {formatDateTime(run.created_at)} ·{' '}
                                {run.user_name ?? 'System'} · {run.ms} ms
                            </span>
                        </button>
                        {open === run.id && run.log.length > 0 && (
                            <pre className="max-h-64 overflow-auto rounded bg-muted p-2 text-xs">
                                {run.log.join('\n')}
                            </pre>
                        )}
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

PluginShow.layout = {
    breadcrumbs: [{ title: 'Plugins', href: '/plugins' }],
};
