import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import DeleteButton from '@/components/delete-button';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

type Eintrag = {
    id: string;
    kuerzel: string;
    label?: string;
    bezeichnung: string;
    beschreibung: string;
    url: string;
    notiz: string;
    verwendet: number;
};

const LISTEN = { kategorien: 'Kategorien', herkunft: 'Herkunft' } as const;
const leer = {
    kuerzel: '',
    label: '',
    bezeichnung: '',
    beschreibung: '',
    url: '',
    notiz: '',
};

/** Listen des Glossars: Kategorien und Herkunft. */
export default function GlossaryLists({
    liste,
    eintraege,
}: {
    liste: keyof typeof LISTEN;
    eintraege: Eintrag[];
}) {
    const [bearbeiten, setBearbeiten] = useState<Eintrag | null>(null);
    const form = useForm({ ...leer });
    const waehlen = (e: Eintrag | null) => {
        setBearbeiten(e);
        form.clearErrors();
        form.setData(
            e
                ? {
                      kuerzel: e.kuerzel,
                      label: e.label ?? '',
                      bezeichnung: e.bezeichnung,
                      beschreibung: e.beschreibung,
                      url: e.url,
                      notiz: e.notiz,
                  }
                : { ...leer },
        );
    };
    const submit = (ev: FormEvent) => {
        ev.preventDefault();
        const fertig = { preserveScroll: true, onSuccess: () => waehlen(null) };
        if (bearbeiten) {
            form.put(`/glossar/listen/${liste}/${bearbeiten.id}`, fertig);
        } else {
            form.post(`/glossar/listen/${liste}`, fertig);
        }
    };

    return (
        <>
            <Head title={`Glossar: ${LISTEN[liste]}`} />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Kategorien & Herkunft"
                    description="Listen für das Glossar. Jeder Eintrag hat Abkürzung, Bezeichnung, Beschreibung, URL und Notiz."
                />
                <div
                    className="flex flex-wrap gap-2"
                    role="tablist"
                    aria-label="Liste wählen"
                >
                    {(Object.keys(LISTEN) as (keyof typeof LISTEN)[]).map(
                        (k) => (
                            <Button
                                key={k}
                                asChild
                                variant={k === liste ? 'default' : 'outline'}
                            >
                                <Link
                                    href={`/glossar/listen?liste=${k}`}
                                    role="tab"
                                    aria-selected={k === liste}
                                >
                                    {LISTEN[k]}
                                </Link>
                            </Button>
                        ),
                    )}
                </div>
                <Card>
                    <CardContent className="overflow-x-auto pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Abkürzung</TableHead>
                                    <TableHead>Label</TableHead>
                                    <TableHead>Bezeichnung</TableHead>
                                    <TableHead>Beschreibung</TableHead>
                                    <TableHead>URL</TableHead>
                                    <TableHead>Notiz</TableHead>
                                    <TableHead>Verwendet</TableHead>
                                    <TableHead className="sr-only">
                                        Aktionen
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {eintraege.map((e) => (
                                    <TableRow key={e.id} className="align-top">
                                        <TableCell
                                            className="font-mono font-medium"
                                            translate="no"
                                        >
                                            {e.kuerzel}
                                        </TableCell>
                                        <TableCell>
                                            {e.label && (
                                                <Badge
                                                    variant="secondary"
                                                    translate="no"
                                                >
                                                    {e.label}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className="whitespace-normal"
                                            translate="no"
                                        >
                                            {e.bezeichnung}
                                        </TableCell>
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
                                            <Badge variant="outline">
                                                {e.verwendet}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex justify-end gap-1">
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
                                                    url={`/glossar/listen/${liste}/${e.id}`}
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
                <Card>
                    <CardHeader>
                        <CardTitle>
                            {bearbeiten ? 'Bearbeiten' : 'Hinzufügen'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 sm:grid-cols-[10rem_1fr]"
                        >
                            <FormField
                                id="gl-k"
                                label="Abkürzung *"
                                error={form.errors.kuerzel}
                            >
                                <Input
                                    id="gl-k"
                                    maxLength={20}
                                    value={form.data.kuerzel}
                                    onChange={(e) =>
                                        form.setData('kuerzel', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="gl-b"
                                label="Bezeichnung *"
                                error={form.errors.bezeichnung}
                            >
                                <Input
                                    id="gl-b"
                                    value={form.data.bezeichnung}
                                    onChange={(e) =>
                                        form.setData(
                                            'bezeichnung',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                                                            <FormField
                                    id="gl-l"
                                    label="Label"
                                    hint="Kurz, erscheint in der Tabelle als Tag (leer: Abkürzung)"
                                    error={form.errors.label}
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="gl-l"
                                        maxLength={30}
                                        className="max-w-60"
                                        value={form.data.label}
                                        onChange={(e) =>
                                            form.setData(
                                                'label',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                            <FormField
                                id="gl-d"
                                label="Beschreibung"
                                error={form.errors.beschreibung}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="gl-d"
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
                                id="gl-u"
                                label="URL"
                                error={form.errors.url}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="gl-u"
                                    type="url"
                                    placeholder="https://"
                                    value={form.data.url}
                                    onChange={(e) =>
                                        form.setData('url', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="gl-n"
                                label="Notiz"
                                error={form.errors.notiz}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="gl-n"
                                    rows={2}
                                    value={form.data.notiz}
                                    onChange={(e) =>
                                        form.setData('notiz', e.target.value)
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
        </>
    );
}

GlossaryLists.layout = {
    breadcrumbs: [
        { title: 'Glossar', href: '/glossar' },
        { title: 'Kategorien & Herkunft', href: '/glossar/listen' },
    ],
};
