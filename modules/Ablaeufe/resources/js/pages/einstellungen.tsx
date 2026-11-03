import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ExternalLink, Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import DeleteButton from '@/components/delete-button';
import FormField from '@/components/form-field';
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
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

type Liste = { titel: string; einzahl: string; beschreibung: string };
type Option = { id: string; label: string };
type Eintrag = {
    id: string;
    kuerzel: string;
    bezeichnung: string;
    beschreibung?: string;
    url?: string;
    notiz?: string;
    fachbereich_id?: string | null;
    typ?: string;
    parent_id?: string | null;
};

const leer = {
    kuerzel: '',
    bezeichnung: '',
    beschreibung: '',
    url: '',
    notiz: '',
    fachbereich_id: '',
    typ: 'schulhaus',
    parent_id: '',
};

/** Einstellungen der Abläufe: erweiterbare Tabellen (Orte, Fachbereiche, Rollen, Zeitlich, Mitwirkung). */
export default function AblaeufeEinstellungen({
    listen,
    anzahl,
    aktiv,
    eintraege,
    fachbereiche,
    orte,
    ortTypen,
}: {
    listen: Record<string, Liste>;
    anzahl: Record<string, number>;
    aktiv: string;
    eintraege: Eintrag[];
    fachbereiche: Option[];
    orte: Option[];
    ortTypen: Record<string, string>;
}) {
    const liste = listen[aktiv];
    const [bearbeiten, setBearbeiten] = useState<Eintrag | null>(null);
    const form = useForm({ ...leer });
    const formular = useRef<HTMLDivElement>(null);
    const name = (opts: Option[], id?: string | null) =>
        opts.find((o) => o.id === id)?.label ?? '';

    useEffect(() => {
        setBearbeiten(null);
        form.setData({ ...leer });
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [aktiv]);

    const waehlen = (e: Eintrag | null) => {
        setBearbeiten(e);
        form.clearErrors();
        form.setData({
            kuerzel: e?.kuerzel ?? '',
            bezeichnung: e?.bezeichnung ?? '',
            beschreibung: e?.beschreibung ?? '',
            url: e?.url ?? '',
            notiz: e?.notiz ?? '',
            fachbereich_id: e?.fachbereich_id ?? '',
            typ: e?.typ ?? 'schulhaus',
            parent_id: e?.parent_id ?? '',
        });
        formular.current?.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });
    };
    const submit = (ev: FormEvent) => {
        ev.preventDefault();
        form.transform((d) => ({
            ...d,
            fachbereich_id: d.fachbereich_id || null,
            parent_id: d.parent_id || null,
        }));
        const fertig = { preserveScroll: true, onSuccess: () => waehlen(null) };
        if (bearbeiten) {
            form.put(
                `/ablaeufe/einstellungen/${aktiv}/${bearbeiten.id}`,
                fertig,
            );
        } else {
            form.post(`/ablaeufe/einstellungen/${aktiv}`, fertig);
        }
    };
    const verschieben = (index: number, nach: number) => {
        const ids = eintraege.map((e) => e.id);
        const [id] = ids.splice(index, 1);
        ids.splice(nach, 0, id);
        router.post(
            `/ablaeufe/einstellungen/${aktiv}/reihenfolge`,
            { ids },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Einstellungen der Abläufe" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Einstellungen der Abläufe"
                    description="Erweiterbare Tabellen für die Abläufe. Jeder Eintrag hat Abkürzung, Bezeichnung, Beschreibung, URL und Notiz."
                />
                <div
                    className="flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Tabelle wählen"
                >
                    {Object.entries(listen).map(([key, l]) => (
                        <Button
                            key={key}
                            asChild
                            variant={key === aktiv ? 'default' : 'outline'}
                        >
                            <Link
                                href={`/ablaeufe/einstellungen?liste=${key}`}
                                role="tab"
                                aria-selected={key === aktiv}
                                preserveScroll
                            >
                                {l.titel}
                                <Badge variant="secondary">
                                    {anzahl[key] ?? 0}
                                </Badge>
                            </Link>
                        </Button>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{liste.titel}</CardTitle>
                        <CardDescription>{liste.beschreibung}</CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Abkürzung</TableHead>
                                    <TableHead>Bezeichnung</TableHead>
                                    {aktiv === 'rollen' && (
                                        <TableHead>Fachbereich</TableHead>
                                    )}
                                    {aktiv === 'orte' && (
                                        <>
                                            <TableHead>Typ</TableHead>
                                            <TableHead>Übergeordnet</TableHead>
                                        </>
                                    )}
                                    <TableHead>Beschreibung</TableHead>
                                    <TableHead>URL</TableHead>
                                    <TableHead>Notiz</TableHead>
                                    <TableHead className="sr-only">
                                        Aktionen
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {eintraege.map((e, i) => (
                                    <TableRow key={e.id} className="align-top">
                                        <TableCell
                                            className="font-mono font-medium"
                                            translate="no"
                                        >
                                            {e.kuerzel}
                                        </TableCell>
                                        <TableCell
                                            className="whitespace-normal"
                                            translate="no"
                                        >
                                            {e.bezeichnung}
                                        </TableCell>
                                        {aktiv === 'rollen' && (
                                            <TableCell
                                                className="whitespace-normal"
                                                translate="no"
                                            >
                                                {name(
                                                    fachbereiche,
                                                    e.fachbereich_id,
                                                )}
                                            </TableCell>
                                        )}
                                        {aktiv === 'orte' && (
                                            <>
                                                <TableCell>
                                                    {ortTypen[e.typ ?? ''] ??
                                                        e.typ}
                                                </TableCell>
                                                <TableCell
                                                    className="whitespace-normal"
                                                    translate="no"
                                                >
                                                    {name(orte, e.parent_id)}
                                                </TableCell>
                                            </>
                                        )}
                                        <TableCell
                                            className="max-w-xs whitespace-normal"
                                            translate="no"
                                        >
                                            {e.beschreibung}
                                        </TableCell>
                                        <TableCell>
                                            {e.url && (
                                                <a
                                                    href={e.url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 text-primary underline-offset-2 hover:underline"
                                                >
                                                    Öffnen{' '}
                                                    <ExternalLink className="size-3.5" />
                                                </a>
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className="max-w-xs whitespace-normal text-muted-foreground"
                                            translate="no"
                                        >
                                            {e.notiz}
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex justify-end gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={i === 0}
                                                    aria-label={`${e.bezeichnung} nach oben`}
                                                    onClick={() =>
                                                        verschieben(i, i - 1)
                                                    }
                                                >
                                                    <ArrowUp />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    disabled={
                                                        i ===
                                                        eintraege.length - 1
                                                    }
                                                    aria-label={`${e.bezeichnung} nach unten`}
                                                    onClick={() =>
                                                        verschieben(i, i + 1)
                                                    }
                                                >
                                                    <ArrowDown />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    aria-label={`${e.bezeichnung} bearbeiten`}
                                                    onClick={() => waehlen(e)}
                                                >
                                                    <Pencil />
                                                </Button>
                                                <DeleteButton
                                                    url={`/ablaeufe/einstellungen/${aktiv}/${e.id}`}
                                                    title={`«${e.bezeichnung}» löschen?`}
                                                    size="icon"
                                                />
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div ref={formular}>
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {bearbeiten
                                    ? `${liste.einzahl} bearbeiten`
                                    : `${liste.einzahl} hinzufügen`}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="grid gap-4 sm:grid-cols-[10rem_1fr]"
                            >
                                <FormField
                                    id="l-kuerzel"
                                    label="Abkürzung *"
                                    error={form.errors.kuerzel}
                                >
                                    <Input
                                        id="l-kuerzel"
                                        value={form.data.kuerzel}
                                        maxLength={20}
                                        onChange={(e) =>
                                            form.setData(
                                                'kuerzel',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="l-bezeichnung"
                                    label="Bezeichnung *"
                                    error={form.errors.bezeichnung}
                                >
                                    <Input
                                        id="l-bezeichnung"
                                        value={form.data.bezeichnung}
                                        onChange={(e) =>
                                            form.setData(
                                                'bezeichnung',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                {aktiv === 'rollen' && (
                                    <FormField
                                        id="l-fachbereich"
                                        label="Fachbereich"
                                        error={form.errors.fachbereich_id}
                                        className="sm:col-span-2"
                                    >
                                        <NativeSelect
                                            id="l-fachbereich"
                                            value={form.data.fachbereich_id}
                                            onChange={(e) =>
                                                form.setData(
                                                    'fachbereich_id',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="">Keiner</option>
                                            {fachbereiche.map((f) => (
                                                <option key={f.id} value={f.id}>
                                                    {f.label}
                                                </option>
                                            ))}
                                        </NativeSelect>
                                    </FormField>
                                )}
                                {aktiv === 'orte' && (
                                    <>
                                        <FormField
                                            id="l-typ"
                                            label="Typ *"
                                            error={form.errors.typ}
                                        >
                                            <NativeSelect
                                                id="l-typ"
                                                value={form.data.typ}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'typ',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {Object.entries(ortTypen).map(
                                                    ([k, l]) => (
                                                        <option
                                                            key={k}
                                                            value={k}
                                                        >
                                                            {l}
                                                        </option>
                                                    ),
                                                )}
                                            </NativeSelect>
                                        </FormField>
                                        <FormField
                                            id="l-parent"
                                            label="Übergeordneter Ort"
                                            error={form.errors.parent_id}
                                        >
                                            <NativeSelect
                                                id="l-parent"
                                                value={form.data.parent_id}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'parent_id',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                <option value="">Keiner</option>
                                                {orte
                                                    .filter(
                                                        (o) =>
                                                            o.id !==
                                                            bearbeiten?.id,
                                                    )
                                                    .map((o) => (
                                                        <option
                                                            key={o.id}
                                                            value={o.id}
                                                        >
                                                            {o.label}
                                                        </option>
                                                    ))}
                                            </NativeSelect>
                                        </FormField>
                                    </>
                                )}
                                <FormField
                                    id="l-beschreibung"
                                    label="Beschreibung"
                                    error={form.errors.beschreibung}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="l-beschreibung"
                                        rows={2}
                                        value={form.data.beschreibung}
                                        onChange={(e) =>
                                            form.setData(
                                                'beschreibung',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="l-url"
                                    label="URL"
                                    error={form.errors.url}
                                    hint="z. B. Link auf eine Weisung, ein Formular oder eine Seite"
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="l-url"
                                        type="url"
                                        placeholder="https://"
                                        value={form.data.url}
                                        onChange={(e) =>
                                            form.setData('url', e.target.value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="l-notiz"
                                    label="Notiz"
                                    error={form.errors.notiz}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="l-notiz"
                                        rows={2}
                                        value={form.data.notiz}
                                        onChange={(e) =>
                                            form.setData(
                                                'notiz',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <div className="flex flex-wrap gap-2 sm:col-span-2">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {bearbeiten ? (
                                            'Speichern'
                                        ) : (
                                            <>
                                                <Plus /> Hinzufügen
                                            </>
                                        )}
                                    </Button>
                                    {bearbeiten && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => waehlen(null)}
                                        >
                                            Abbrechen
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

AblaeufeEinstellungen.layout = {
    breadcrumbs: [
        { title: 'Abläufe Verwaltung', href: '/ablaeufe' },
        { title: 'Einstellungen', href: '/ablaeufe/einstellungen' },
    ],
};
