import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronRight,
    Loader2,
    Plug,
    PlugZap,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import DataNotice from '@/components/plugins/data-notice';
import PluginStatus, {
    categoryLabels,
} from '@/components/plugins/plugin-status';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import { postJson } from '@/lib/http';
import type { PluginInfo, PluginResult } from '@/types';

/** Ergebnis von «Verbindung testen» je Plugin (ok null = übersprungen). */
type Check = { ok: boolean | null; message: string; ms?: number | null };

function CheckResult({ check }: { check: Check }) {
    return (
        <div
            className="flex items-start gap-2 rounded-md border bg-muted/30 p-2 text-xs"
            data-testid="connection-result"
            role="status"
        >
            {check.ok === true ? (
                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-success" />
            ) : check.ok === false ? (
                <XCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
            ) : (
                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            )}
            <span className="flex-1">{check.message}</span>
            {check.ms != null && (
                <span className="text-muted-foreground">{check.ms} ms</span>
            )}
        </div>
    );
}

export default function PluginsIndex({
    plugins,
    errors,
    serverInSwitzerland,
}: {
    plugins: PluginInfo[];
    errors: string[];
    serverInSwitzerland: boolean;
}) {
    const [checks, setChecks] = useState<Record<string, Check>>({});
    const [busy, setBusy] = useState<string | null>(null);

    const testOne = async (plugin: PluginInfo) => {
        setBusy(plugin.id);
        const start = performance.now();
        const result = await postJson<PluginResult>(`/plugins/${plugin.id}/test`);
        setChecks((c) => ({
            ...c,
            [plugin.id]: {
                ok: result.ok ?? result.status === 'success',
                message: result.message,
                ms: Math.round(performance.now() - start),
            },
        }));
        setBusy(null);
    };

    const testAll = async () => {
        setBusy('*');
        const result = await postJson<
            PluginResult & { results?: ({ id: string } & Check)[] }
        >('/plugins/verbindungen-testen');
        setChecks(
            Object.fromEntries(
                (result.results ?? []).map((r) => [r.id, r]),
            ),
        );
        setBusy(null);
        router.reload({ only: ['plugins'] });
    };

    return (
        <>
            <Head title="Plugins" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Plugins"
                    description="Anbindung anderer Systeme per API. Plugins kommen aus dem Repository und werden hier ein- und ausgeschaltet."
                    actions={
                        <Button
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() => void testAll()}
                            data-testid="test-all"
                        >
                            {busy === '*' ? (
                                <Loader2 className="animate-spin" />
                            ) : (
                                <PlugZap />
                            )}
                            Alle Verbindungen testen
                        </Button>
                    }
                />

                {!serverInSwitzerland && <DataNotice />}

                {errors.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Nicht geladene Plugins</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {errors.map((e) => (
                                    <li key={e}>{e}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {plugins.length === 0 && (
                    <EmptyState icon={Plug} title="Keine Plugins gefunden">
                        Plugins liegen im Ordner plugins/ (siehe PLUGINS.md).
                    </EmptyState>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {plugins.map((plugin) => (
                        <div
                            key={plugin.id}
                            className="grid gap-3 rounded-xl border bg-card p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="grid gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">
                                            {plugin.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {categoryLabels[plugin.category] ??
                                                plugin.category}
                                        </Badge>
                                        {plugin.unofficial && (
                                            <Badge
                                                variant="outline"
                                                className="border-warning text-warning"
                                                title="Keine offizielle Schnittstelle des Herstellers"
                                            >
                                                inoffiziell
                                            </Badge>
                                        )}
                                        <span className="text-xs text-muted-foreground">
                                            v{plugin.version}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {plugin.description}
                                    </p>
                                </div>
                                <PluginStatus plugin={plugin} />
                            </div>

                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>
                                    {plugin.service.endpoints.length} Endpunkte
                                </span>
                                <span>{plugin.actions.length} Aktionen</span>
                                {plugin.widgets.length > 0 && (
                                    <span>
                                        {plugin.widgets.length} Kachel(n)
                                    </span>
                                )}
                                {plugin.events.length > 0 && (
                                    <span>
                                        {plugin.events.length} Ereignisse
                                    </span>
                                )}
                                {plugin.last_run && (
                                    <span>
                                        Zuletzt: {plugin.last_run.label},{' '}
                                        {formatDateTime(
                                            plugin.last_run.created_at,
                                        )}
                                    </span>
                                )}
                            </div>

                            {checks[plugin.id] && (
                                <CheckResult check={checks[plugin.id]} />
                            )}

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`enable-${plugin.id}`}
                                        checked={plugin.enabled}
                                        onCheckedChange={(
                                            c: boolean | 'indeterminate',
                                        ) =>
                                            router.post(
                                                `/plugins/${plugin.id}/schalter`,
                                                { enabled: c === true },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                    <Label htmlFor={`enable-${plugin.id}`}>
                                        Eingeschaltet
                                    </Label>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={
                                            busy !== null ||
                                            !plugin.enabled ||
                                            !plugin.configured
                                        }
                                        title={
                                            !plugin.enabled
                                                ? 'Zuerst einschalten'
                                                : !plugin.configured
                                                  ? 'Zuerst einrichten'
                                                  : undefined
                                        }
                                        onClick={() => void testOne(plugin)}
                                    >
                                        {busy === plugin.id ? (
                                            <Loader2 className="animate-spin" />
                                        ) : (
                                            <PlugZap />
                                        )}
                                        Verbindung testen
                                    </Button>
                                    <Button variant="outline" size="sm" asChild>
                                        <Link href={`/plugins/${plugin.id}`}>
                                            Einrichten <ChevronRight />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

PluginsIndex.layout = {
    breadcrumbs: [{ title: 'Plugins', href: '/plugins' }],
};
