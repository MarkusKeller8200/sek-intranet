import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, GitMerge, Split } from 'lucide-react';
import { useState } from 'react';
import EmptyState from '@/components/empty-state';
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
import { NativeSelect } from '@/components/ui/native-select';
import type { GlossaryEntry } from '@/lib/glossary';
import { entryTitle } from '@/lib/glossary';

type Gruppe = {
    key: string;
    art: string;
    wert: string;
    ids: string[];
    eintraege: GlossaryEntry[];
};

/** Texte, bei denen beim Zusammenführen gewählt wird (Mehrfachwerte werden vereinigt). */
const TEXTFELDER = [
    'abk_offiziell',
    'abk_eigen',
    'alias_eigen',
    'bez_offiziell',
    'bez_eigen',
    'kurzbeschreibung',
    'beschreibung',
    'url',
    'notiz',
    'status',
] as const;

function GruppeKarte({
    g,
    kategorien,
    herkunft,
    felder,
}: {
    g: Gruppe;
    kategorien: Record<string, string>;
    herkunft: Record<string, string>;
    felder: Record<string, string>;
}) {
    const [ziel, setZiel] = useState(g.ids[0]);
    const [auswahl, setAuswahl] = useState<Record<string, string>>({});
    const [offen, setOffen] = useState(false);
    const unterschiedlich = TEXTFELDER.filter(
        (f) => new Set(g.eintraege.map((e) => String(e[f] ?? ''))).size > 1,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    <Badge variant="outline">{g.art}</Badge>
                    <span translate="no">{g.wert}</span>
                    <Badge variant="secondary">{`${g.eintraege.length} Einträge`}</Badge>
                </CardTitle>
                <CardDescription>
                    Zusammenführen nur, wenn die Bedeutung gleich ist – der
                    Eintrag erhält dann alle Kategorien. Sonst «als mehrdeutig
                    belassen».
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                    {g.eintraege.map((e) => (
                        <div
                            key={e.id}
                            className="grid gap-1 rounded-lg border p-3 text-sm"
                        >
                            <Link
                                href={`/glossar/${e.id}`}
                                className="font-semibold text-primary underline-offset-2 hover:underline"
                                translate="no"
                            >
                                {entryTitle(e)}
                            </Link>
                            <span className="flex flex-wrap gap-1">
                                {e.kategorien.map((k) => (
                                    <Badge key={k} variant="outline">
                                        {kategorien[k] ?? k}
                                    </Badge>
                                ))}
                            </span>
                            {e.kurzbeschreibung && (
                                <span
                                    className="text-muted-foreground"
                                    translate="no"
                                >
                                    {e.kurzbeschreibung}
                                </span>
                            )}
                            <span className="text-xs text-muted-foreground">
                                {e.herkunft
                                    .map((h) => herkunft[h] ?? h)
                                    .join(', ')}
                            </span>
                            {e.notiz && (
                                <span
                                    className="text-xs text-muted-foreground"
                                    translate="no"
                                >
                                    {e.notiz}
                                </span>
                            )}
                        </div>
                    ))}
                </div>

                {offen && (
                    <div className="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm">
                        <label className="grid gap-1 sm:grid-cols-[14rem_1fr] sm:items-center">
                            <span>Eintrag, der bleibt</span>
                            <NativeSelect
                                value={ziel}
                                onChange={(e) => setZiel(e.target.value)}
                            >
                                {g.eintraege.map((e) => (
                                    <option
                                        key={e.id}
                                        value={e.id}
                                    >{`${entryTitle(e)} (${e.kategorien.map((k) => kategorien[k] ?? k).join(', ')})`}</option>
                                ))}
                            </NativeSelect>
                        </label>
                        {unterschiedlich.map((f) => (
                            <label
                                key={f}
                                className="grid gap-1 sm:grid-cols-[14rem_1fr] sm:items-center"
                            >
                                <span>{`${felder[f] ?? f} übernehmen von`}</span>
                                <NativeSelect
                                    value={auswahl[f] ?? ziel}
                                    onChange={(e) =>
                                        setAuswahl({
                                            ...auswahl,
                                            [f]: e.target.value,
                                        })
                                    }
                                >
                                    {g.eintraege.map((e) => (
                                        <option key={e.id} value={e.id}>
                                            {String(e[f] ?? '') || '(leer)'}
                                        </option>
                                    ))}
                                </NativeSelect>
                            </label>
                        ))}
                        <p className="text-muted-foreground">
                            Vereinigt werden: Kategorien, Aliase, Herkunft,
                            «genutzt in», Suchbegriffe. Die anderen Einträge
                            werden danach entfernt (im Änderungsverlauf
                            nachvollziehbar).
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={() =>
                                    router.post(
                                        '/glossar/doppelte/zusammenfuehren',
                                        {
                                            ids: g.ids,
                                            ziel,
                                            auswahl: Object.fromEntries(
                                                unterschiedlich.map((f) => [
                                                    f,
                                                    auswahl[f] ?? ziel,
                                                ]),
                                            ),
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <GitMerge /> Jetzt zusammenführen
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOffen(false)}
                            >
                                Abbrechen
                            </Button>
                        </div>
                    </div>
                )}

                {!offen && (
                    <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={() => setOffen(true)}>
                            <GitMerge /> Zusammenführen …
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    '/glossar/doppelte/belassen',
                                    { key: g.key, ids: g.ids },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Split /> Als mehrdeutig belassen
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Doppelte Abkürzungen oder Begriffe finden und bereinigen. */
export default function GlossaryDuplicates({
    gruppen,
    kategorien,
    herkunft,
    felder,
}: {
    gruppen: Gruppe[];
    kategorien: Record<string, string>;
    herkunft: Record<string, string>;
    felder: Record<string, string>;
}) {
    return (
        <>
            <Head title="Doppelte Abkürzungen oder Begriffe" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Doppelte Abkürzungen oder Begriffe"
                    description={`${gruppen.length} offene Gruppen mit gleicher Abkürzung oder gleicher Bezeichnung. Nichts wird automatisch geändert.`}
                />
                {gruppen.length === 0 ? (
                    <EmptyState
                        icon={CheckCircle2}
                        title="Keine offenen Doppelten"
                    >
                        Alle Gruppen sind zusammengeführt oder als mehrdeutig
                        belassen.
                    </EmptyState>
                ) : (
                    gruppen.map((g) => (
                        <GruppeKarte
                            key={g.key}
                            g={g}
                            kategorien={kategorien}
                            herkunft={herkunft}
                            felder={felder}
                        />
                    ))
                )}
            </div>
        </>
    );
}

GlossaryDuplicates.layout = {
    breadcrumbs: [
        { title: 'Glossar', href: '/glossar' },
        { title: 'Doppelte', href: '/glossar/doppelte' },
    ],
};
