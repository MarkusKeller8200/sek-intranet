import { Link } from '@inertiajs/react';
import {
    Download,
    Focus,
    Navigation,
    PenLine,
    Radar,
    Table2,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { onTabContext, requestTab } from '@/lib/connector';
import type { TabResult } from '@/lib/connector';
import { postJson } from '@/lib/http';
import { t } from '@/lib/i18n';
import { csv, toTable } from '@/lib/tab-table';

export type TabEndpoint = {
    plugin: string;
    id: string;
    label: string;
    method: string;
    path: string;
    params: { name: string; value: string; help?: string | null }[];
    body: unknown;
};

type ContextPerson = {
    id: string;
    name: string;
    type: string;
    class: string | null;
    device: string | null;
    matched: string;
    href: string;
};
type Context = {
    url: string;
    title: string;
    ids: string[];
    system: string | null;
    people: ContextPerson[];
};

function ResultTable({
    columns,
    rows,
    name,
}: {
    columns: string[];
    rows: string[][];
    name: string;
}) {
    const download = () => {
        const blob = new Blob(['﻿' + csv(columns, rows)], {
            type: 'text/csv;charset=utf-8',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${name}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                <span>{`${rows.length} Zeilen`}</span>
                <Button variant="outline" size="sm" onClick={download}>
                    <Download /> CSV
                </Button>
            </div>
            <div
                className="max-h-96 overflow-auto rounded-md border"
                translate="no"
            >
                <table className="w-full text-xs" data-testid="tab-table">
                    <thead className="sticky top-0 bg-muted text-left">
                        <tr>
                            {columns.map((c) => (
                                <th key={c} className="px-2 py-1 font-medium">
                                    {c}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r, i) => (
                            <tr key={i} className="border-t">
                                {r.map((v, j) => (
                                    <td key={j} className="px-2 py-1">
                                        {v}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/**
 * Verbindung zum Tab einer Fachanwendung (Browser-Erweiterung): Kontext abfragen und Meldungen, Abruf über die
 * Anmeldung im Tab, Tab steuern, Tabelle übernehmen. Nur lesend – nichts wird in der Anwendung abgeschickt.
 */
export default function TabConnection({
    appKey,
    endpoints,
}: {
    appKey: string;
    endpoints: TabEndpoint[];
}) {
    const [context, setContext] = useState<Context | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [path, setPath] = useState('/');
    const [selector, setSelector] = useState('');
    const [value, setValue] = useState('');
    const [tableSelector, setTableSelector] = useState('table');
    const [table, setTable] = useState<{
        columns: string[];
        rows: string[][];
        name: string;
    } | null>(null);
    const [endpointId, setEndpointId] = useState(endpoints[0]?.id ?? '');
    const endpoint = useMemo(
        () => endpoints.find((e) => e.id === endpointId),
        [endpoints, endpointId],
    );
    const [params, setParams] = useState<Record<string, string>>({});
    const [fetched, setFetched] = useState<TabResult | null>(null);

    useEffect(() => {
        setParams(
            Object.fromEntries(
                (endpoint?.params ?? []).map((p) => [p.name, p.value]),
            ),
        );
    }, [endpoint]);

    const lookup = async (url: string, title: string) => {
        const result = await postJson<{
            ids: string[];
            system: string | null;
            people: ContextPerson[];
        }>(`/einbindung/${appKey}/kontext`, { url });
        setContext({
            url,
            title,
            ids: result.ids ?? [],
            system: result.system ?? null,
            people: result.people ?? [],
        });
    };

    // Melden: neue Seite im Tab → Kontext automatisch aktualisieren
    useEffect(
        () => onTabContext(appKey, (url, title) => void lookup(url, title)),
        [appKey],
    );

    const run = async (
        name: string,
        command: string,
        args: Record<string, unknown> = {},
    ) => {
        setBusy(name);
        setMessage(null);
        const result = await requestTab(appKey, command, args);
        setBusy(null);
        if (!result.ok) {
            setMessage(result.error ?? t('Nicht möglich.'));
        }

        return result;
    };

    const askContext = async () => {
        const r = await run('context', 'context');
        if (r.ok && r.url) {
            await lookup(r.url, r.title ?? '');
        }
    };

    const readTable = async () => {
        const r = await run('table', 'table', {
            selector: tableSelector || 'table',
        });
        if (r.ok && r.columns && r.rows) {
            setTable({
                columns: r.columns,
                rows: r.rows,
                name: `${appKey}-tabelle`,
            });
        }
    };

    const fetchEndpoint = async () => {
        if (!endpoint) {
            return;
        }
        let target = endpoint.path;
        const query = new URLSearchParams();
        for (const p of endpoint.params) {
            const v = params[p.name] ?? '';
            if (target.includes(`{${p.name}}`)) {
                target = target.replace(`{${p.name}}`, encodeURIComponent(v));
            } else if (v !== '') {
                query.set(p.name, v);
            }
        }
        if (/\{[^}]+\}/.test(target)) {
            setMessage(t('Bitte alle Pfadwerte ausfüllen.'));

            return;
        }
        const r = await run('fetch', 'fetch', {
            path: target + (query.toString() ? `?${query.toString()}` : ''),
            method: endpoint.method,
            body: endpoint.method === 'GET' ? null : endpoint.body,
            read: true,
        });
        setFetched(r);
    };
    const fetchedTable = fetched?.json ? toTable(fetched.json) : null;

    return (
        <Card data-testid="tab-connection">
            <CardHeader>
                <CardTitle>Verbindung zum Tab</CardTitle>
                <CardDescription>
                    Über die Browser-Erweiterung, mit deiner Anmeldung in der
                    Anwendung. Nur lesend – es wird nichts abgeschickt oder
                    gespeichert.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6">
                {message && (
                    <p
                        className="rounded-md border border-warning/40 bg-warning-soft p-2 text-sm"
                        role="status"
                    >
                        {message}
                    </p>
                )}

                {/* Abfragen und Melden */}
                <section className="grid gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="text-sm font-semibold">
                            Was ist im Tab offen?
                        </h3>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() => void askContext()}
                            data-testid="tab-context"
                        >
                            <Radar /> Abfragen
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            disabled={busy !== null}
                            onClick={() => void run('focus', 'focus')}
                        >
                            <Focus /> Tab nach vorne
                        </Button>
                    </div>
                    {context ? (
                        <div
                            className="grid gap-2 text-sm"
                            data-testid="tab-context-result"
                        >
                            <p
                                className="text-xs text-muted-foreground"
                                translate="no"
                            >
                                {context.title} · {context.url}
                            </p>
                            {context.people.length > 0 ? (
                                <ul className="grid gap-1">
                                    {context.people.map((p) => (
                                        <li
                                            key={p.id}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            <UserRound className="size-4 text-muted-foreground" />
                                            <Link
                                                href={p.href}
                                                className="font-medium text-primary hover:underline"
                                                translate="no"
                                            >
                                                {p.name}
                                            </Link>
                                            {p.class && (
                                                <Badge variant="outline">
                                                    {p.class}
                                                </Badge>
                                            )}
                                            {p.device && (
                                                <Badge variant="secondary">
                                                    <span>Gerät</span>{' '}
                                                    <span translate="no">
                                                        {p.device}
                                                    </span>
                                                </Badge>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    {context.system
                                        ? t(
                                              'Keine Person im Intranet zu dieser Seite gefunden (Abgleich über die Datenherkunft).',
                                          )
                                        : t(
                                              'Für diese Anwendung ist kein Abgleich mit Personen eingerichtet.',
                                          )}
                                </p>
                            )}
                        </div>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Wechselt die Seite im Tab, aktualisiert sich die
                            Anzeige von selbst.
                        </p>
                    )}
                </section>

                {/* Abruf über die Anmeldung im Tab */}
                {endpoints.length > 0 && (
                    <section className="grid gap-2">
                        <h3 className="text-sm font-semibold">
                            Daten abrufen (Anmeldung im Tab)
                        </h3>
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="grid gap-1">
                                <Label htmlFor="tab-endpoint">Abruf</Label>
                                <select
                                    id="tab-endpoint"
                                    className="h-9 rounded-md border bg-background px-2 text-sm"
                                    value={endpointId}
                                    onChange={(e) =>
                                        setEndpointId(e.target.value)
                                    }
                                >
                                    {endpoints.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {e.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {endpoint?.params.map((p) => (
                                <div key={p.name} className="grid gap-1">
                                    <Label htmlFor={`tab-param-${p.name}`}>
                                        <span translate="no">{p.name}</span>
                                    </Label>
                                    <Input
                                        id={`tab-param-${p.name}`}
                                        className="h-9 w-36"
                                        value={params[p.name] ?? ''}
                                        title={p.help ?? undefined}
                                        onChange={(e) =>
                                            setParams({
                                                ...params,
                                                [p.name]: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            ))}
                            <Button
                                size="sm"
                                disabled={busy !== null}
                                onClick={() => void fetchEndpoint()}
                                data-testid="tab-fetch"
                            >
                                Abrufen
                            </Button>
                        </div>
                        {fetched?.ok && fetchedTable && (
                            <ResultTable
                                {...fetchedTable}
                                name={`${appKey}-${endpointId}`}
                            />
                        )}
                        {fetched?.ok && !fetchedTable && (
                            <p className="text-xs text-muted-foreground">
                                Antwort ohne Liste (HTTP {fetched.status}).
                            </p>
                        )}
                        {fetched && !fetched.ok && fetched.status && (
                            <p className="text-xs text-destructive">
                                <span>HTTP</span> {fetched.status}
                                {fetched.signedIn === false && (
                                    <span>
                                        {' '}
                                        –{' '}
                                        {t(
                                            'Im Tab zuerst anmelden und eine Seite der Anwendung öffnen.',
                                        )}
                                    </span>
                                )}
                            </p>
                        )}
                    </section>
                )}

                {/* Steuern */}
                <section className="grid gap-2">
                    <h3 className="text-sm font-semibold">Tab steuern</h3>
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="tab-path">Seite (Pfad)</Label>
                            <Input
                                id="tab-path"
                                className="h-9 w-64"
                                value={path}
                                onChange={(e) => setPath(e.target.value)}
                                translate="no"
                            />
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() =>
                                void run('navigate', 'navigate', { path })
                            }
                        >
                            <Navigation /> Öffnen
                        </Button>
                    </div>
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="tab-selector">
                                Feld (CSS-Selektor)
                            </Label>
                            <Input
                                id="tab-selector"
                                className="h-9 w-48"
                                placeholder="input[name=search]"
                                value={selector}
                                onChange={(e) => setSelector(e.target.value)}
                                translate="no"
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label htmlFor="tab-value">Wert</Label>
                            <Input
                                id="tab-value"
                                className="h-9 w-48"
                                value={value}
                                onChange={(e) => setValue(e.target.value)}
                            />
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy !== null || selector === ''}
                            onClick={() =>
                                void run('fill', 'fill', {
                                    selector,
                                    value,
                                }).then(
                                    (r) =>
                                        r.ok &&
                                        setMessage(
                                            t(
                                                'Feld ausgefüllt – nicht abgeschickt.',
                                            ),
                                        ),
                                )
                            }
                        >
                            <PenLine /> Ausfüllen
                        </Button>
                    </div>
                </section>

                {/* Übernehmen */}
                <section className="grid gap-2">
                    <h3 className="text-sm font-semibold">
                        Tabelle aus dem Tab übernehmen
                    </h3>
                    <div className="flex flex-wrap items-end gap-2">
                        <div className="grid gap-1">
                            <Label htmlFor="tab-table-selector">
                                Tabelle (CSS-Selektor)
                            </Label>
                            <Input
                                id="tab-table-selector"
                                className="h-9 w-48"
                                value={tableSelector}
                                onChange={(e) =>
                                    setTableSelector(e.target.value)
                                }
                                translate="no"
                            />
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={busy !== null}
                            onClick={() => void readTable()}
                            data-testid="tab-read-table"
                        >
                            <Table2 /> Auslesen
                        </Button>
                    </div>
                    {table && <ResultTable {...table} />}
                    <p className="text-xs text-muted-foreground">
                        Vorschau nur in diesem Browser; ins Intranet übernommen
                        wird erst über einen Import (Personendaten nur auf dem
                        Schweizer Server).
                    </p>
                </section>
            </CardContent>
        </Card>
    );
}
