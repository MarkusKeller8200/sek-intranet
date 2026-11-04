import { Head, Link } from '@inertiajs/react';
import { ExternalLink, Lock, Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import DeleteButton from '@/components/delete-button';
import Markdown from '@/components/markdown';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import type { GlossaryEntry } from '@/lib/glossary';
import { entryTitle } from '@/lib/glossary';

type Herkunft = {
    id: string;
    kuerzel: string;
    bezeichnung: string;
    url?: string;
};

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-1 sm:grid-cols-[12rem_1fr]">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{children}</dd>
        </div>
    );
}

/** Ein Glossar-Eintrag: offiziell und eigen nebeneinander. */
export default function GlossaryShow({
    eintrag: e,
    andere,
    kategorien,
    herkunft,
    bereiche,
    status,
    rechte,
}: {
    eintrag: GlossaryEntry;
    andere: { id: string; abk: string; bez: string }[];
    kategorien: Record<string, string>;
    herkunft: Herkunft[];
    /** «Intern genutzt in»: Bereich/Modul → Titel und Link (#60) */
    bereiche: Record<string, { title: string; href: string | null }>;
    status: Record<string, string>;
    rechte: { edit: boolean; official: boolean };
}) {
    const titel = entryTitle(e);

    return (
        <>
            <Head title={titel} />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={titel}
                    description={e.kurzbeschreibung || undefined}
                    actions={
                        rechte.edit && (
                            <>
                                <Button asChild>
                                    <Link href={`/glossar/${e.id}/bearbeiten`}>
                                        <Pencil /> Bearbeiten
                                    </Link>
                                </Button>
                                <DeleteButton
                                    url={`/glossar/${e.id}`}
                                    title="Eintrag löschen?"
                                    size="default"
                                />
                            </>
                        )
                    }
                />
                <div className="flex flex-wrap gap-2">
                    {e.kategorien.map((k) => (
                        <Badge key={k} variant="outline">
                            {kategorien[k] ?? k}
                        </Badge>
                    ))}
                    <Badge
                        variant={
                            e.status === 'aktuell' ? 'secondary' : 'default'
                        }
                    >
                        {status[e.status] ?? e.status}
                    </Badge>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="size-4 text-muted-foreground" />{' '}
                                Offiziell
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-2 text-sm">
                                <Row label="Abkürzung">
                                    <span
                                        className="font-semibold"
                                        translate="no"
                                    >
                                        {e.abk_offiziell || '–'}
                                    </span>
                                </Row>
                                {e.aliase_offiziell.length > 0 && (
                                    <Row label="Aliase">
                                        <span translate="no">
                                            {e.aliase_offiziell.join(', ')}
                                        </span>
                                    </Row>
                                )}
                                <Row label="Bezeichnung">
                                    <span translate="no">
                                        {e.bez_offiziell || '–'}
                                    </span>
                                </Row>
                            </dl>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Wie wir es verwenden</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-2 text-sm">
                                <Row label="Abkürzung">
                                    <span
                                        className="font-semibold"
                                        translate="no"
                                    >
                                        {e.abk_eigen || '–'}
                                    </span>
                                </Row>
                                {e.alias_eigen && (
                                    <Row label="Alias">
                                        <span translate="no">
                                            {e.alias_eigen}
                                        </span>
                                    </Row>
                                )}
                                <Row label="Bezeichnung">
                                    <span translate="no">
                                        {e.bez_eigen || '–'}
                                    </span>
                                </Row>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <dl className="grid gap-3 text-sm">
                            {e.beschreibung && (
                                <Row label="Beschreibung">
                                    <span
                                        className="whitespace-pre-line"
                                        translate="no"
                                    >
                                        {e.beschreibung}
                                    </span>
                                </Row>
                            )}
                            {e.url && (
                                <Row label="URL">
                                    <a
                                        href={e.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 text-primary underline-offset-2 hover:underline"
                                    >
                                        {e.url}{' '}
                                        <ExternalLink className="size-3.5" />
                                    </a>
                                </Row>
                            )}
                            <Row label="Herkunft">
                                <span className="flex flex-wrap gap-1">
                                    {e.herkunft.map((id) => {
                                        const h = herkunft.find(
                                            (x) => x.id === id,
                                        );

                                        return h?.url ? (
                                            <a
                                                key={id}
                                                href={h.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                <Badge variant="secondary">{`${h.kuerzel} – ${h.bezeichnung}`}</Badge>
                                            </a>
                                        ) : (
                                            <Badge key={id} variant="secondary">
                                                {h
                                                    ? `${h.kuerzel} – ${h.bezeichnung}`
                                                    : id}
                                            </Badge>
                                        );
                                    })}
                                    {e.herkunft.length === 0 && '–'}
                                </span>
                            </Row>
                            <Row label="Intern genutzt in">
                                <span className="flex flex-wrap gap-x-3 gap-y-1">
                                    {e.genutzt_in.map((b) => {
                                        const area = bereiche[b];

                                        return area?.href ? (
                                            <Link
                                                key={b}
                                                href={area.href}
                                                className="text-primary underline-offset-2 hover:underline"
                                            >
                                                {area.title}
                                            </Link>
                                        ) : (
                                            <span key={b}>
                                                {area?.title ?? b}
                                            </span>
                                        );
                                    })}
                                    {e.genutzt_in.length === 0 && '–'}
                                </span>
                            </Row>
                            {e.suchbegriffe && (
                                <Row label="Suchbegriffe">{e.suchbegriffe}</Row>
                            )}
                            {(e.gueltig_ab || e.gueltig_bis) && (
                                <Row label="Gültig">{`${e.gueltig_ab ?? '…'} bis ${e.gueltig_bis ?? '…'}`}</Row>
                            )}
                            {andere.length > 0 && (
                                <Row label="Andere Bedeutungen">
                                    <span className="flex flex-wrap gap-2">
                                        {andere.map((a) => (
                                            <Link
                                                key={a.id}
                                                href={`/glossar/${a.id}`}
                                                className="text-primary underline-offset-2 hover:underline"
                                            >
                                                <span translate="no">{`${a.abk} – ${a.bez}`}</span>
                                            </Link>
                                        ))}
                                    </span>
                                </Row>
                            )}
                            {e.updated_at && (
                                <Row label="Geändert">
                                    {formatDateTime(e.updated_at)}
                                </Row>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                {e.notiz && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Notiz</CardTitle>
                        </CardHeader>
                        <CardContent translate="no">
                            <Markdown text={e.notiz} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

GlossaryShow.layout = {
    breadcrumbs: [{ title: 'Glossar', href: '/glossar' }],
};
