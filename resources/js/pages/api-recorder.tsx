import { Head } from '@inertiajs/react';
import {
    Bookmark,
    Check,
    ChevronDown,
    ChevronRight,
    CircleDot,
    Download,
    ExternalLink,
    FileUp,
    Plus,
    Save,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    bookmarkletSource,
    captureFromMessage,
    MESSAGE_TYPE,
    parseHar,
} from '@/lib/api-recorder/capture';
import {
    buildOpenApi,
    header,
    isNoise,
    mergeOperation,
    operationFrom,
    operationKey,
    safeHeaders,
    toYaml,
} from '@/lib/api-recorder/openapi';
import type { ApiDoc, Capture, DocOperation } from '@/lib/api-recorder/types';
import { postJson } from '@/lib/http';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const METHOD_COLORS: Record<string, string> = {
    GET: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    POST: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    PUT: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    PATCH: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    DELETE: 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
};

function Method({ method }: { method: string }) {
    return (
        <span
            className={cn(
                'inline-block min-w-14 rounded px-1.5 py-0.5 text-center font-mono text-[11px] font-semibold',
                METHOD_COLORS[method] ?? 'bg-muted',
            )}
            translate="no"
        >
            {method}
        </span>
    );
}

function preview(text: string | null, max = 3000): string {
    if (!text) {
        return '–';
    }
    try {
        return JSON.stringify(JSON.parse(text), null, 2).slice(0, max);
    } catch {
        return text.slice(0, max);
    }
}

const emptyDoc = (): ApiDoc => ({ title: '', base_url: '', description: '', operations: [] });

function download(content: string, type: string, filename: string) {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

const slug = (s: string) =>
    s
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '') || 'api';

export default function ApiRecorder({ docs: initialDocs, origin }: { docs: ApiDoc[]; origin: string }) {
    const [url, setUrl] = useState('https://');
    const [captures, setCaptures] = useState<Capture[]>([]);
    const [connected, setConnected] = useState<string | null>(null);
    const [onlyApi, setOnlyApi] = useState(true);
    const [ask, setAsk] = useState(true);
    const [withExamples, setWithExamples] = useState(false);
    const [expanded, setExpanded] = useState<string | null>(null);
    const [ignored, setIgnored] = useState<string[]>([]);
    const [queue, setQueue] = useState<Capture[]>([]);
    const [docs, setDocs] = useState<ApiDoc[]>(initialDocs);
    const [doc, setDoc] = useState<ApiDoc>(initialDocs[0] ?? emptyDoc());
    const [showSpec, setShowSpec] = useState<'json' | 'yaml' | null>(null);
    const [saving, setSaving] = useState(false);
    const popup = useRef<Window | null>(null);
    const bookmarklet = useRef<HTMLAnchorElement>(null);
    const docRef = useRef(doc);
    docRef.current = doc;
    const askRef = useRef(ask);
    askRef.current = ask;
    const ignoredRef = useRef(ignored);
    ignoredRef.current = ignored;

    // React blockiert javascript:-Links im JSX – deshalb direkt setzen
    useEffect(() => {
        bookmarklet.current?.setAttribute('href', bookmarkletSource(origin || window.location.origin));
    }, [origin]);

    const documented = useMemo(() => new Set(doc.operations.map((o) => o.key)), [doc.operations]);

    const addCapture = (capture: Capture) => {
        setCaptures((list) => [capture, ...list].slice(0, 2000));
        if (isNoise(capture)) {
            return;
        }
        let key: string;
        try {
            key = operationKey(capture.method, capture.url);
        } catch {
            return;
        }
        const current = docRef.current;
        if (current.operations.some((o) => o.key === key)) {
            // Bekannter Endpunkt: Schema mit der neuen Aufzeichnung ergänzen
            setDoc((d) => ({ ...d, operations: d.operations.map((o) => (o.key === key ? mergeOperation(o, capture) : o)) }));
        } else if (askRef.current && !ignoredRef.current.includes(key)) {
            setQueue((q) => (q.some((c) => operationKey(c.method, c.url) === key) ? q : [...q, capture]));
        }
    };

    const addRef = useRef(addCapture);
    addRef.current = addCapture;

    // Meldungen aus dem aufgezeichneten Fenster (nur vom selbst geöffneten Fenster)
    useEffect(() => {
        const onMessage = (event: MessageEvent) => {
            if (!popup.current || event.source !== popup.current) {
                return;
            }
            const data = event.data as { type?: string; hello?: string } | null;
            if (data?.type === MESSAGE_TYPE && typeof data.hello === 'string') {
                setConnected(data.hello);

                return;
            }
            const capture = captureFromMessage(event.data);
            if (capture) {
                addRef.current(capture);
            }
        };
        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, []);

    const openWindow = () => {
        let target: URL;
        try {
            target = new URL(url.trim());
            if (!/^https?:$/.test(target.protocol)) {
                throw new Error();
            }
        } catch {
            toast.error(t('Bitte eine gültige Adresse mit https:// eingeben.'));

            return;
        }
        popup.current = window.open(target.href, 'sbi-api-recorder', 'popup,width=1280,height=860');
        if (!popup.current) {
            toast.error(t('Das Fenster wurde blockiert. Bitte Pop-ups für das Intranet erlauben.'));

            return;
        }
        setConnected(null);
        setDoc((d) => (d.base_url || d.operations.length > 0 ? d : { ...d, base_url: target.origin, title: d.title || target.hostname }));
    };

    const importHar = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        void file.text().then((text) => {
            try {
                const list = parseHar(text);
                list.forEach(addCapture);
                toast.success(`${list.length} Anfragen eingelesen.`);
            } catch (e) {
                toast.error(e instanceof Error ? e.message : t('Datei konnte nicht gelesen werden.'));
            }
        });
    };

    const accept = (capture: Capture, overrides: Partial<DocOperation> = {}) => {
        const first = { ...operationFrom(capture, withExamples), ...overrides };
        // Weitere Aufzeichnungen desselben Endpunkts gleich einrechnen
        const op = captures
            .filter((c) => c.id !== capture.id && !isNoise(c) && operationKey(c.method, c.url) === first.key)
            .reduce((acc, c) => mergeOperation(acc, c), first);
        setDoc((d) =>
            d.operations.some((o) => o.key === op.key)
                ? d
                : {
                      ...d,
                      base_url: d.base_url || new URL(capture.url).origin,
                      title: d.title || new URL(capture.url).hostname,
                      operations: [...d.operations, op],
                  },
        );
        setQueue((q) => q.filter((c) => operationKey(c.method, c.url) !== op.key));
    };

    const ignore = (capture: Capture) => {
        const key = operationKey(capture.method, capture.url);
        setIgnored((list) => [...list, key]);
        setQueue((q) => q.filter((c) => operationKey(c.method, c.url) !== key));
    };

    const save = async () => {
        if (!doc.title.trim()) {
            toast.error(t('Bitte einen Titel für die Dokumentation eingeben.'));

            return;
        }
        setSaving(true);
        try {
            const res = await postJson<{ doc?: ApiDoc; message?: string }>('/api-recorder', doc);
            if (!res.doc) {
                throw new Error(res.message ?? t('Speichern fehlgeschlagen.'));
            }
            const saved = res.doc;
            setDoc(saved);
            setDocs((list) => [saved, ...list.filter((d) => d.id !== saved.id)]);
            toast.success(t('Dokumentation gespeichert.'));
        } catch (e) {
            toast.error(e instanceof Error ? e.message : t('Speichern fehlgeschlagen.'));
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        if (!doc.id || !window.confirm(t('Diese Dokumentation löschen?'))) {
            return;
        }
        const token = decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.slice(11) ?? '');
        await fetch(`/api-recorder/${doc.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        });
        const rest = docs.filter((d) => d.id !== doc.id);
        setDocs(rest);
        setDoc(rest[0] ?? emptyDoc());
        toast.success(t('Dokumentation gelöscht.'));
    };

    const spec = useMemo(() => buildOpenApi(doc), [doc]);
    const visible = onlyApi ? captures.filter((c) => !isNoise(c)) : captures;
    const current = queue[0] ?? null;
    const updateOp = (key: string, patch: Partial<DocOperation>) =>
        setDoc((d) => ({ ...d, operations: d.operations.map((o) => (o.key === key ? { ...o, ...patch } : o)) }));

    return (
        <>
            <Head title="API-Recorder" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="API-Recorder"
                    description="Eine Website im eigenen Fenster bedienen, die gesendeten Anfragen und Antworten mitschneiden, ausgewählte Endpunkte dokumentieren und als OpenAPI-Datei (Swagger) speichern."
                />

                <div className="flex items-start gap-2 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Die Aufzeichnung bleibt in diesem Browser. Gespeichert werden nur Endpunkte und
                        abgeleitete Schemas – Beispielwerte nur auf Wunsch und maskiert. Passwörter,
                        Tokens und Cookies werden nie übernommen.
                    </span>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>1 · Website aufzeichnen</CardTitle>
                        <CardDescription>
                            Browser erlauben aus Sicherheitsgründen nicht, den Verkehr einer fremden Website
                            direkt mitzulesen. Deshalb: Fenster öffnen, dort einmal das Lesezeichen
                            «SBI-Recorder» anklicken, dann die Funktionen der Website bedienen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="flex flex-wrap gap-2">
                            <Input
                                value={url}
                                onChange={(e) => setUrl(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && openWindow()}
                                placeholder="https://www.beispiel.ch"
                                className="max-w-xl flex-1"
                                translate="no"
                                data-testid="recorder-url"
                            />
                            <Button onClick={openWindow}>
                                <ExternalLink /> Fenster öffnen
                            </Button>
                        </div>

                        <ol className="grid list-decimal gap-2 pl-5 text-sm">
                            <li>
                                Lesezeichen einmalig in die Lesezeichenleiste ziehen:{' '}
                                <a
                                    ref={bookmarklet}
                                    className="inline-flex items-center gap-1.5 rounded-md border bg-background px-2 py-1 font-medium text-primary shadow-xs"
                                    onClick={(e) => {
                                        e.preventDefault();
                                        toast.info(t('Das Lesezeichen in die Lesezeichenleiste ziehen und im Fenster der Website anklicken.'));
                                    }}
                                    data-testid="bookmarklet"
                                >
                                    <Bookmark className="size-4" /> SBI-Recorder
                                </a>
                            </li>
                            <li>Adresse eingeben und «Fenster öffnen».</li>
                            <li>
                                Im neuen Fenster auf das Lesezeichen klicken – unten rechts erscheint ein roter
                                Punkt «SBI-Recorder». Nach einem Neuladen der Seite erneut klicken.
                            </li>
                            <li>Funktionen der Website bedienen. Jede Anfrage erscheint unten.</li>
                        </ol>

                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                            <span className="flex items-center gap-1.5">
                                <CircleDot
                                    className={cn('size-4', connected ? 'text-emerald-600' : 'text-muted-foreground')}
                                />
                                {connected ? (
                                    <>
                                        <span>Verbunden mit</span>
                                        <code className="text-xs" translate="no">
                                            {new URL(connected).host}
                                        </code>
                                    </>
                                ) : (
                                    <span className="text-muted-foreground">Noch kein Fenster verbunden</span>
                                )}
                            </span>
                            <Button variant="outline" size="sm" asChild>
                                <label className="cursor-pointer">
                                    <FileUp /> HAR-Datei importieren
                                    <input type="file" accept=".har,.json" className="hidden" onChange={importHar} />
                                </label>
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Alternative ohne Lesezeichen (auch für ganze Seitenaufrufe und Formulare): in den
                            Entwicklertools des Browsers Netzwerk → «Alle als HAR speichern», dann hier
                            importieren. Manche Websites blockieren Lesezeichen-Skripte; dann diesen Weg wählen.
                        </p>

                        <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <label className="flex items-center gap-2">
                                <Checkbox checked={onlyApi} onCheckedChange={(v: boolean | 'indeterminate') => setOnlyApi(v === true)} />
                                Nur API-Anfragen zeigen (ohne Bilder, Skripte, Schriften)
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox checked={ask} onCheckedChange={(v: boolean | 'indeterminate') => setAsk(v === true)} />
                                Bei neuem Endpunkt nachfragen
                            </label>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={withExamples}
                                    onCheckedChange={(v: boolean | 'indeterminate') => setWithExamples(v === true)}
                                />
                                Beispielwerte übernehmen (maskiert)
                            </label>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            2 · Aufgezeichnete Anfragen
                            <Badge variant="outline">{`${visible.length} von ${captures.length}`}</Badge>
                            {captures.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="ml-auto"
                                    onClick={() => {
                                        setCaptures([]);
                                        setQueue([]);
                                    }}
                                >
                                    <Trash2 /> Liste leeren
                                </Button>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {visible.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Noch keine Anfragen aufgezeichnet.</p>
                        ) : (
                            <div className="max-h-[32rem] overflow-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="w-6 px-2 py-2" />
                                            <th className="px-2 py-2 font-medium">Methode</th>
                                            <th className="px-2 py-2 font-medium">Adresse</th>
                                            <th className="px-2 py-2 font-medium">Status</th>
                                            <th className="px-2 py-2 font-medium">Typ</th>
                                            <th className="px-2 py-2 font-medium">Zeit</th>
                                            <th className="px-2 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visible.map((c) => {
                                            let key = '';
                                            let path = c.url;
                                            try {
                                                key = operationKey(c.method, c.url);
                                                const u = new URL(c.url);
                                                path = `${u.host}${u.pathname}${u.search}`;
                                            } catch {
                                                /* ungültig */
                                            }
                                            const isDoc = documented.has(key);
                                            const isIgnored = ignored.includes(key);
                                            const open = expanded === c.id;

                                            return [
                                                <tr
                                                    key={c.id}
                                                    className="cursor-pointer border-t hover:bg-muted/40"
                                                    onClick={() => setExpanded(open ? null : c.id)}
                                                >
                                                    <td className="px-2 py-1.5">
                                                        {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <Method method={c.method} />
                                                    </td>
                                                    <td className="max-w-md truncate px-2 py-1.5 font-mono text-xs" translate="no" title={c.url}>
                                                        {path}
                                                    </td>
                                                    <td className={cn('px-2 py-1.5 font-mono text-xs', c.status >= 400 && 'text-destructive')}>
                                                        {c.status || '–'}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-xs text-muted-foreground" translate="no">
                                                        {header(c.responseHeaders, 'content-type').split(';')[0] || c.kind}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-xs whitespace-nowrap text-muted-foreground">
                                                        {`${c.duration} ms`}
                                                    </td>
                                                    <td className="px-2 py-1 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                                                        {isDoc ? (
                                                            <Badge variant="outline" className="text-emerald-700 dark:text-emerald-400">
                                                                <Check /> dokumentiert
                                                            </Badge>
                                                        ) : (
                                                            !isNoise(c) && (
                                                                <>
                                                                    <Button variant="ghost" size="sm" onClick={() => accept(c)}>
                                                                        <Plus /> In Doku
                                                                    </Button>
                                                                    {!isIgnored && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="size-8"
                                                                            title="Ignorieren"
                                                                            aria-label="Ignorieren"
                                                                            onClick={() => ignore(c)}
                                                                        >
                                                                            <X />
                                                                        </Button>
                                                                    )}
                                                                </>
                                                            )
                                                        )}
                                                    </td>
                                                </tr>,
                                                open && (
                                                    <tr key={`${c.id}-detail`} className="border-t bg-muted/20">
                                                        <td />
                                                        <td colSpan={6} className="grid gap-3 p-3 md:grid-cols-2">
                                                            <div className="min-w-0">
                                                                <div className="mb-1 text-xs font-semibold">Anfrage</div>
                                                                <pre className="max-h-64 overflow-auto rounded bg-background p-2 text-[11px]" translate="no">
                                                                    {JSON.stringify(safeHeaders(c.requestHeaders), null, 2)}
                                                                    {'\n\n'}
                                                                    {preview(c.requestBody)}
                                                                </pre>
                                                            </div>
                                                            <div className="min-w-0">
                                                                <div className="mb-1 text-xs font-semibold">Antwort</div>
                                                                <pre className="max-h-64 overflow-auto rounded bg-background p-2 text-[11px]" translate="no">
                                                                    {preview(c.responseBody)}
                                                                </pre>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ),
                                            ];
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            3 · API-Dokumentation
                            <select
                                className="ml-auto h-8 rounded-md border border-input bg-background px-2 text-sm font-normal"
                                value={doc.id ?? ''}
                                onChange={(e) => setDoc(docs.find((d) => d.id === e.target.value) ?? emptyDoc())}
                                aria-label="Dokumentation wählen"
                            >
                                <option value="">Neue Dokumentation</option>
                                {docs.map((d) => (
                                    <option key={d.id} value={d.id} translate="no">
                                        {d.title}
                                    </option>
                                ))}
                            </select>
                        </CardTitle>
                        <CardDescription>
                            {
                                'Endpunkte werden aus den Aufzeichnungen zusammengefasst: IDs im Pfad werden zu Parametern ({id}), Schemas aus allen Beispielen abgeleitet.'
                            }
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-3 md:grid-cols-2">
                            <label className="grid gap-1 text-sm">
                                Titel
                                <Input value={doc.title} onChange={(e) => setDoc({ ...doc, title: e.target.value })} />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Basis-URL
                                <Input
                                    value={doc.base_url}
                                    onChange={(e) => setDoc({ ...doc, base_url: e.target.value })}
                                    placeholder="https://api.beispiel.ch"
                                    translate="no"
                                />
                            </label>
                            <label className="grid gap-1 text-sm md:col-span-2">
                                Beschreibung
                                <Textarea
                                    value={doc.description}
                                    onChange={(e) => setDoc({ ...doc, description: e.target.value })}
                                    rows={2}
                                />
                            </label>
                        </div>

                        {doc.operations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Noch keine Endpunkte. Anfragen aufzeichnen und «In Doku» wählen.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-2 py-2 font-medium">Methode</th>
                                            <th className="px-2 py-2 font-medium">Pfad</th>
                                            <th className="px-2 py-2 font-medium">Beschreibung</th>
                                            <th className="px-2 py-2 font-medium">Gruppe</th>
                                            <th className="px-2 py-2 font-medium">Status</th>
                                            <th className="px-2 py-2 font-medium">Beispiele</th>
                                            <th className="px-2 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {doc.operations.map((op) => (
                                            <tr key={op.key} className="border-t">
                                                <td className="px-2 py-1.5">
                                                    <Method method={op.method} />
                                                </td>
                                                <td className="px-2 py-1">
                                                    <Input
                                                        value={op.path}
                                                        onChange={(e) => updateOp(op.key, { path: e.target.value })}
                                                        className="h-8 min-w-56 font-mono text-xs"
                                                        translate="no"
                                                    />
                                                </td>
                                                <td className="px-2 py-1">
                                                    <Input
                                                        value={op.summary}
                                                        onChange={(e) => updateOp(op.key, { summary: e.target.value })}
                                                        placeholder="Was macht der Endpunkt?"
                                                        className="h-8 min-w-48"
                                                    />
                                                </td>
                                                <td className="px-2 py-1">
                                                    <Input
                                                        value={op.tag}
                                                        onChange={(e) => updateOp(op.key, { tag: e.target.value })}
                                                        className="h-8 w-32"
                                                    />
                                                </td>
                                                <td className="px-2 py-1.5 font-mono text-xs">{op.status}</td>
                                                <td className="px-2 py-1.5 text-xs">{op.samples}</td>
                                                <td className="px-2 py-1 text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        title="Aus der Dokumentation entfernen"
                                                        aria-label="Aus der Dokumentation entfernen"
                                                        onClick={() =>
                                                            setDoc({ ...doc, operations: doc.operations.filter((o) => o.key !== op.key) })
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <div className="flex flex-wrap gap-2">
                            <Button onClick={() => void save()} disabled={saving}>
                                <Save /> Dokumentation speichern
                            </Button>
                            <Button
                                variant="outline"
                                disabled={doc.operations.length === 0}
                                onClick={() =>
                                    download(JSON.stringify(spec, null, 2), 'application/json', `${slug(doc.title)}-openapi.json`)
                                }
                            >
                                <Download /> OpenAPI-Datei (JSON)
                            </Button>
                            <Button
                                variant="outline"
                                disabled={doc.operations.length === 0}
                                onClick={() => download(`${toYaml(spec)}\n`, 'application/yaml', `${slug(doc.title)}-openapi.yaml`)}
                            >
                                <Download /> OpenAPI-Datei (YAML)
                            </Button>
                            <Button
                                variant="ghost"
                                disabled={doc.operations.length === 0}
                                onClick={() => setShowSpec(showSpec ? null : 'yaml')}
                            >
                                {showSpec ? 'Vorschau schliessen' : 'Vorschau'}
                            </Button>
                            {doc.id && (
                                <Button variant="ghost" className="ml-auto text-destructive" onClick={() => void remove()}>
                                    <Trash2 /> Löschen
                                </Button>
                            )}
                        </div>
                        {showSpec && (
                            <pre
                                className="max-h-[28rem] overflow-auto rounded-md border bg-muted/30 p-3 text-[11px]"
                                translate="no"
                                data-testid="spec-preview"
                            >
                                {toYaml(spec)}
                            </pre>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Die Datei lässt sich im Swagger Editor (editor.swagger.io), in Postman oder Insomnia
                            öffnen.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <QuestionDialog
                capture={current}
                remaining={queue.length}
                onAccept={(c, patch) => accept(c, patch)}
                onIgnore={ignore}
                onLater={() => setQueue((q) => q.slice(1))}
                onStopAsking={() => {
                    setAsk(false);
                    setQueue([]);
                }}
            />
        </>
    );
}

/** Rückfrage bei einem neuen Endpunkt: aufnehmen (mit Beschreibung), ignorieren oder später. */
function QuestionDialog({
    capture,
    remaining,
    onAccept,
    onIgnore,
    onLater,
    onStopAsking,
}: {
    capture: Capture | null;
    remaining: number;
    onAccept: (capture: Capture, patch: Partial<DocOperation>) => void;
    onIgnore: (capture: Capture) => void;
    onLater: () => void;
    onStopAsking: () => void;
}) {
    const [summary, setSummary] = useState('');
    const [path, setPath] = useState('');
    const [tag, setTag] = useState('');

    useEffect(() => {
        if (capture) {
            const op = operationFrom(capture, false);
            setSummary('');
            setPath(op.path);
            setTag(op.tag);
        }
    }, [capture]);

    return (
        <Dialog open={capture !== null} onOpenChange={(open: boolean) => !open && onLater()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Neuer Endpunkt – in die Dokumentation aufnehmen?</DialogTitle>
                    <DialogDescription>
                        {remaining > 1 ? `Noch ${remaining - 1} weitere warten.` : 'Beschreibung ergänzen und aufnehmen.'}
                    </DialogDescription>
                </DialogHeader>
                {capture && (
                    <div className="grid gap-3 text-sm">
                        <div className="flex items-center gap-2">
                            <Method method={capture.method} />
                            <code className="truncate text-xs" translate="no" title={capture.url}>
                                {capture.url}
                            </code>
                            <span className="ml-auto font-mono text-xs">{capture.status}</span>
                        </div>
                        <label className="grid gap-1">
                            {'Pfad (IDs als Parameter in geschweiften Klammern)'}
                            <Input value={path} onChange={(e) => setPath(e.target.value)} className="font-mono text-xs" translate="no" />
                        </label>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1">
                                Beschreibung
                                <Input
                                    value={summary}
                                    onChange={(e) => setSummary(e.target.value)}
                                    placeholder="z. B. Termine eines Monats laden"
                                    autoFocus
                                />
                            </label>
                            <label className="grid gap-1">
                                Gruppe
                                <Input value={tag} onChange={(e) => setTag(e.target.value)} />
                            </label>
                        </div>
                        <div>
                            <div className="mb-1 text-xs font-semibold">Antwort (Ausschnitt)</div>
                            <pre className="max-h-48 overflow-auto rounded-md border bg-muted/30 p-2 text-[11px]" translate="no">
                                {preview(capture.responseBody, 1500)}
                            </pre>
                        </div>
                    </div>
                )}
                <DialogFooter className="flex-wrap gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" onClick={onStopAsking}>
                        Nicht mehr fragen
                    </Button>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={onLater}>
                            Später
                        </Button>
                        <Button variant="outline" onClick={() => capture && onIgnore(capture)}>
                            Ignorieren
                        </Button>
                        <Button onClick={() => capture && onAccept(capture, { summary, path, tag })} data-testid="accept-endpoint">
                            <Check /> Aufnehmen
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

ApiRecorder.layout = {
    breadcrumbs: [{ title: 'API-Recorder', href: '/api-recorder' }],
};
