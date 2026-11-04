import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    ChevronRight,
    Copy,
    Eraser,
    ExternalLink,
    KeyRound,
    Play,
    Plus,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import ApiBodyEditor from '@/components/api-body-editor';
import PageHeader from '@/components/page-header';
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
import { collectIds, parseJson, showValue } from '@/lib/api-examples';
import { formatDateTime } from '@/lib/format';
import { postJson } from '@/lib/http';
import { palette } from '@/lib/colors';
import { cn } from '@/lib/utils';

type Parameter = {
    name: string;
    in: 'query' | 'path';
    description?: string;
    required?: boolean;
    /** Beispielwert, im Tester vorausgefüllt */
    example?: unknown;
    schema?: { type?: string; enum?: string[]; default?: unknown };
};

/** IDs aus der letzten Antwort (z. B. «id» aus der Personenliste) für Pfadparameter anderer Endpunkte. */
let lastIds: Record<string, string> = {};

/** Startwert: ID aus der letzten Antwort (Pfad), sonst Beispiel, sonst Vorgabe. */
function startValue(p: Parameter): string {
    if (p.in === 'path' && lastIds[p.name]) {
        return lastIds[p.name];
    }
    if (p.example !== undefined && p.example !== null) {
        return showValue(p.example);
    }

    return showValue(p.schema?.default);
}

type Operation = {
    tags?: string[];
    summary?: string;
    description?: string;
    parameters?: Parameter[];
    requestBody?: { content?: { 'application/json'?: { example?: unknown } } };
    responses?: Record<string, unknown>;
};

type Spec = {
    info: { title: string; version: string; description: string };
    tags: { name: string; description: string }[];
    paths: Record<string, Record<string, Operation | Parameter[]>>;
};

type Token = {
    id: string;
    name: string;
    prefix: string;
    abilities: string[];
    owner_name: string | null;
    expires_at: string | null;
    last_used_at: string | null;
    created_at: string;
};

type Person = { id: string; name: string; role: string };

const METHODS = ['get', 'post', 'patch', 'delete'] as const;
type Method = (typeof METHODS)[number];

const METHOD_COLOR: Record<Method, string> = {
    get: palette.sky.badge,
    post: palette.emerald.badge,
    patch: palette.amber.badge,
    delete: palette.rose.badge,
};

const TOKEN_KEY = 'intranet.api.token';

function readToken(): string {
    try {
        return window.sessionStorage.getItem(TOKEN_KEY) ?? '';
    } catch {
        return '';
    }
}

function copy(textValue: string, label = 'Kopiert.') {
    void navigator.clipboard
        .writeText(textValue)
        .then(() => toast.success(label))
        .catch(() => toast.error('Kopieren nicht möglich.'));
}

function TokensCard({
    tokens,
    people,
    onToken,
}: {
    tokens: Token[];
    people: Person[];
    onToken: (token: string) => void;
}) {
    const [name, setName] = useState('');
    const [write, setWrite] = useState(false);
    const [owner, setOwner] = useState('');
    const [days, setDays] = useState('90');
    const [created, setCreated] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const create = async () => {
        setBusy(true);
        const res = await postJson<{ token?: string; message?: string }>(
            '/api-doku/tokens',
            {
                name,
                abilities: write ? ['read', 'write'] : ['read'],
                owner_id: owner || null,
                days: days === '' ? null : Number(days),
            },
        );
        setBusy(false);
        if (!res.token) {
            toast.error(res.message ?? 'Token nicht erstellt.');

            return;
        }
        setCreated(res.token);
        onToken(res.token);
        setName('');
        router.reload({ only: ['tokens'] });
    };

    const revoke = async (token: Token) => {
        const xsrf = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length) ?? '',
        );
        const response = await fetch(`/api-doku/tokens/${token.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf,
            },
        });
        if (response.ok) {
            toast.success(`Token «${token.name}» widerrufen.`);
            router.reload({ only: ['tokens'] });
        } else {
            toast.error('Widerrufen nicht möglich.');
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <KeyRound className="size-4" /> Zugänge (API-Tokens)
                </CardTitle>
                <CardDescription>
                    Ein Token handelt im Namen der gewählten Person und hat
                    deren Rechte. Gespeichert wird nur ein Hash – das Token wird
                    genau einmal angezeigt.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto_auto] md:items-end">
                    <label className="grid gap-1 text-sm">
                        Bezeichnung
                        <Input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            placeholder="z. B. Abgleich IDA"
                            maxLength={80}
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Im Namen von
                        <select
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            value={owner}
                            onChange={(e) => setOwner(e.target.value)}
                        >
                            <option value="">mir</option>
                            {people.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name} ({p.role})
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">
                        Gültig
                        <select
                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            value={days}
                            onChange={(e) => setDays(e.target.value)}
                        >
                            <option value="1">1 Tag</option>
                            <option value="30">30 Tage</option>
                            <option value="90">90 Tage</option>
                            <option value="365">1 Jahr</option>
                            <option value="">unbegrenzt</option>
                        </select>
                    </label>
                    <label className="flex h-9 items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={write}
                            onChange={(e) => setWrite(e.target.checked)}
                        />
                        darf schreiben
                    </label>
                    <Button
                        disabled={busy || name.trim() === ''}
                        onClick={() => void create()}
                    >
                        <Plus /> Token erstellen
                    </Button>
                </div>

                {created && (
                    <div className="grid gap-2 rounded-lg border border-warning/40 bg-warning-soft p-3 text-sm">
                        <strong>
                            Neues Token – jetzt kopieren, es wird nicht mehr
                            angezeigt:
                        </strong>
                        <div className="flex flex-wrap items-center gap-2">
                            <code className="rounded bg-background px-2 py-1 font-mono text-xs break-all">
                                {created}
                            </code>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => copy(created, 'Token kopiert.')}
                            >
                                <Copy /> Kopieren
                            </Button>
                        </div>
                        <span className="text-muted-foreground">
                            Im Tester unten ist es bereits eingesetzt.
                        </span>
                    </div>
                )}

                {tokens.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Tokens.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted text-left">
                                <tr>
                                    {[
                                        'Bezeichnung',
                                        'Token',
                                        'Im Namen von',
                                        'Rechte',
                                        'Gültig bis',
                                        'Zuletzt verwendet',
                                        '',
                                    ].map((h) => (
                                        <th
                                            key={h}
                                            className="px-3 py-2 font-semibold"
                                        >
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {tokens.map((t) => (
                                    <tr key={t.id} className="border-t">
                                        <td className="px-3 py-2">{t.name}</td>
                                        <td className="px-3 py-2 font-mono text-xs">
                                            {t.prefix}…
                                        </td>
                                        <td className="px-3 py-2">
                                            {t.owner_name ?? '–'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {t.abilities.join(', ')}
                                        </td>
                                        <td className="px-3 py-2">
                                            {t.expires_at
                                                ? formatDateTime(t.expires_at)
                                                : 'unbegrenzt'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {t.last_used_at
                                                ? formatDateTime(t.last_used_at)
                                                : 'nie'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => void revoke(t)}
                                            >
                                                <Trash2 /> Widerrufen
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

type Result = {
    status: number;
    ms: number;
    body: string;
};

function Endpoint({
    path,
    method,
    operation,
    shared,
    token,
}: {
    path: string;
    method: Method;
    operation: Operation;
    shared: Parameter[];
    token: string;
}) {
    const parameters = [...shared, ...(operation.parameters ?? [])];
    const example =
        operation.requestBody?.content?.['application/json']?.example;
    const [open, setOpen] = useState(false);
    const fill = () =>
        Object.fromEntries(parameters.map((p) => [p.name, startValue(p)]));
    const exampleBody =
        example === undefined ? '' : JSON.stringify(example, null, 2);
    const [values, setValues] = useState<Record<string, string>>(fill);
    const [body, setBody] = useState(exampleBody);
    const [result, setResult] = useState<Result | null>(null);
    const [busy, setBusy] = useState(false);

    const url = () => {
        let built = path.replace(/\{(\w+)\}/g, (_, name: string) =>
            encodeURIComponent(values[name] ?? ''),
        );
        const query = parameters
            .filter((p) => p.in === 'query' && (values[p.name] ?? '') !== '')
            .map(
                (p) =>
                    `${encodeURIComponent(p.name)}=${encodeURIComponent(values[p.name])}`,
            );
        if (query.length > 0) {
            built += `?${query.join('&')}`;
        }

        return `/api/v1${built}`;
    };

    const curl = () =>
        [
            `curl -X ${method.toUpperCase()} '${window.location.origin}${url()}'`,
            `  -H 'Authorization: Bearer ${token || '<TOKEN>'}'`,
            `  -H 'Accept: application/json'`,
            ...(body && method !== 'get' && method !== 'delete'
                ? [
                      `  -H 'Content-Type: application/json'`,
                      `  -d '${body.replace(/'/g, "'\\''")}'`,
                  ]
                : []),
        ].join(' \\\n');

    const run = async () => {
        if (method !== 'get' && method !== 'delete' && body.trim() !== '') {
            try {
                JSON.parse(body);
            } catch {
                toast.error('Der Body ist kein gültiges JSON.');

                return;
            }
        }
        setBusy(true);
        const started = performance.now();
        try {
            const response = await fetch(url(), {
                method: method.toUpperCase(),
                headers: {
                    Accept: 'application/json',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                    ...(body && method !== 'get' && method !== 'delete'
                        ? { 'Content-Type': 'application/json' }
                        : {}),
                },
                body:
                    method !== 'get' && method !== 'delete' && body
                        ? body
                        : undefined,
            });
            const raw = await response.text();
            let pretty = raw;
            try {
                pretty = JSON.stringify(JSON.parse(raw), null, 2);
            } catch {
                // kein JSON (z. B. 204 ohne Inhalt)
            }
            setResult({
                status: response.status,
                ms: Math.round(performance.now() - started),
                body: pretty,
            });
            lastIds = { ...lastIds, ...collectIds(parseJson(raw), ['id']) };
        } catch (e) {
            setResult({
                status: 0,
                ms: Math.round(performance.now() - started),
                body: e instanceof Error ? e.message : 'Netzwerkfehler',
            });
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="rounded-lg border">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center gap-3 px-3 py-2 text-left hover:bg-accent"
                aria-expanded={open}
            >
                <ChevronRight
                    className={cn(
                        'size-4 shrink-0 transition-transform',
                        open && 'rotate-90',
                    )}
                />
                <Badge
                    variant="outline"
                    className={cn(
                        'w-16 justify-center font-mono uppercase',
                        METHOD_COLOR[method],
                    )}
                >
                    {method}
                </Badge>
                <code className="font-mono text-sm">{path}</code>
                <span className="truncate text-sm text-muted-foreground">
                    {operation.summary}
                </span>
            </button>

            {open && (
                <div className="grid gap-4 border-t p-4">
                    {operation.description && (
                        <p className="text-sm text-muted-foreground">
                            {operation.description}
                        </p>
                    )}

                    {parameters.length > 0 && (
                        <div className="grid gap-3 md:grid-cols-2">
                            {parameters.map((p) => (
                                <label
                                    key={`${p.in}-${p.name}`}
                                    className="grid gap-1 text-sm"
                                >
                                    <span>
                                        <code className="font-mono">
                                            {p.name}
                                        </code>{' '}
                                        <span className="text-xs text-muted-foreground">
                                            (
                                            {p.in === 'path'
                                                ? 'Pfad'
                                                : 'Abfrage'}
                                            {p.required ? ', Pflicht' : ''})
                                        </span>
                                    </span>
                                    {p.schema?.enum ? (
                                        <select
                                            className="h-9 rounded-md border border-input bg-background px-2 text-sm"
                                            value={values[p.name] ?? ''}
                                            onChange={(e) =>
                                                setValues({
                                                    ...values,
                                                    [p.name]: e.target.value,
                                                })
                                            }
                                        >
                                            <option value="">–</option>
                                            {p.schema.enum.map((v) => (
                                                <option key={v} value={v}>
                                                    {v}
                                                </option>
                                            ))}
                                        </select>
                                    ) : (
                                        <Input
                                            value={values[p.name] ?? ''}
                                            onChange={(e) =>
                                                setValues({
                                                    ...values,
                                                    [p.name]: e.target.value,
                                                })
                                            }
                                            placeholder={
                                                p.description ?? p.schema?.type
                                            }
                                        />
                                    )}
                                </label>
                            ))}
                        </div>
                    )}

                    {method !== 'get' && method !== 'delete' && (
                        <ApiBodyEditor
                            id={`body-${method}-${path}`}
                            value={body}
                            onChange={setBody}
                            example={example}
                        />
                    )}

                    {(parameters.length > 0 || exampleBody !== '') && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setValues(fill());
                                    setBody(exampleBody);
                                }}
                            >
                                <Wand2 /> Beispiel einsetzen
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setValues({});
                                    setBody('');
                                }}
                            >
                                <Eraser /> Felder löschen
                            </Button>
                        </div>
                    )}

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            disabled={busy}
                            variant={
                                method === 'delete' ? 'destructive' : 'default'
                            }
                            onClick={() => void run()}
                        >
                            <Play /> {busy ? 'Läuft …' : 'Ausführen'}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => copy(curl(), 'curl-Befehl kopiert.')}
                        >
                            <Copy /> Als curl kopieren
                        </Button>
                        {!token && (
                            <span className="text-sm text-warning">
                                Kein Token gesetzt – die Antwort wird 401 sein.
                            </span>
                        )}
                    </div>

                    {result && (
                        <div className="grid gap-1">
                            <div className="flex items-center gap-2 text-sm">
                                <Badge
                                    variant="outline"
                                    className={
                                        result.status >= 200 &&
                                        result.status < 300
                                            ? palette.emerald.badge
                                            : palette.rose.badge
                                    }
                                >
                                    {result.status === 0
                                        ? 'Fehler'
                                        : `HTTP ${result.status}`}
                                </Badge>
                                <span className="text-muted-foreground">
                                    {result.ms} ms
                                </span>
                                <code className="font-mono text-xs text-muted-foreground">
                                    {method.toUpperCase()} {url()}
                                </code>
                            </div>
                            <pre className="max-h-96 overflow-auto rounded-md border bg-muted p-3 font-mono text-xs">
                                {result.body || '(keine Daten)'}
                            </pre>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

export default function ApiDocs({
    spec,
    tokens,
    people,
}: {
    spec: Spec;
    tokens: Token[];
    people: Person[];
}) {
    const [token, setTokenState] = useState(readToken);
    const setToken = (value: string) => {
        setTokenState(value);
        try {
            window.sessionStorage.setItem(TOKEN_KEY, value);
        } catch {
            // ohne Speicher nur bis zum Neuladen
        }
    };

    const operations = Object.entries(spec.paths).flatMap(([path, item]) => {
        const shared = (
            Array.isArray(item.parameters) ? item.parameters : []
        ) as Parameter[];

        return METHODS.filter((m) => item[m] !== undefined).map((method) => ({
            path,
            method,
            operation: item[method] as Operation,
            shared,
        }));
    });

    return (
        <>
            <Head title="API" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`${spec.info.title} ${spec.info.version}`}
                    description="REST-API mit OpenAPI-Beschreibung. Endpunkte direkt hier ausprobieren."
                    actions={
                        <>
                            <Button asChild>
                                <Link href="/api-doku/referenz">
                                    <BookOpen /> Referenz (Scalar)
                                </Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <a
                                    href="/api/openapi.json"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink /> openapi.json
                                </a>
                            </Button>
                        </>
                    }
                />

                <p className="max-w-4xl text-sm whitespace-pre-line text-muted-foreground">
                    {spec.info.description}
                </p>

                <TokensCard
                    tokens={tokens}
                    people={people}
                    onToken={setToken}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Tester</CardTitle>
                        <CardDescription>
                            Basis-URL{' '}
                            <code className="font-mono">
                                {window.location.origin}/api/v1
                            </code>
                            . Das Token bleibt nur in diesem Browser-Tab
                            gespeichert.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        <label className="grid max-w-xl gap-1 text-sm">
                            API-Token
                            <Input
                                type="password"
                                autoComplete="off"
                                value={token}
                                onChange={(e) =>
                                    setToken(e.target.value.trim())
                                }
                                placeholder="sbi_…"
                                className="font-mono"
                            />
                        </label>

                        {spec.tags.map((tag) => {
                            const list = operations.filter((o) =>
                                o.operation.tags?.includes(tag.name),
                            );
                            if (list.length === 0) {
                                return null;
                            }

                            return (
                                <section key={tag.name} className="grid gap-2">
                                    <h2 className="font-semibold">
                                        {tag.name}{' '}
                                        <span className="text-sm font-normal text-muted-foreground">
                                            – {tag.description}
                                        </span>
                                    </h2>
                                    {list.map((o) => (
                                        <Endpoint
                                            key={`${o.method}-${o.path}`}
                                            {...o}
                                            token={token}
                                        />
                                    ))}
                                </section>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ApiDocs.layout = {
    breadcrumbs: [{ title: 'API', href: '/api-doku' }],
};
