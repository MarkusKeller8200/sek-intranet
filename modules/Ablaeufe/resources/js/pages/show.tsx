import { Head, Link } from '@inertiajs/react';
import { ArrowLeftRight, FileText, Pencil, Printer } from 'lucide-react';
import DeleteButton from '@/components/delete-button';
import PageHeader from '@/components/page-header';
import { TermText } from '@/components/term-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { druckeAblauf } from '../lib/druck';
import type { Ablauf, Dokument, Gruppe } from '../lib/types';
import { fristText, nummer } from '../lib/types';

/** Ein Ablauf als Tabelle je Phase – wie die Excel-Vorlage. */
export default function AblaufShow({
    ablauf,
    gruppen,
    dokumente,
    bereiche,
    status,
    darfBearbeiten,
}: {
    ablauf: Ablauf;
    gruppen: Gruppe[];
    dokumente: Dokument[];
    bereiche: Record<string, string>;
    status: Record<string, string>;
    darfBearbeiten: boolean;
}) {
    const gruppe = (id: string) => gruppen.find((g) => g.id === id)?.name ?? id;
    const dokument = (id: string) => dokumente.find((d) => d.id === id);
    const phase = (key: string) =>
        ablauf.phasen.find((p) => p.key === key)?.titel ?? key;

    return (
        <>
            <Head title={ablauf.titel} />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={ablauf.titel}
                    description={ablauf.beschreibung || undefined}
                />
                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge variant="outline">
                        {bereiche[ablauf.bereich] ?? ablauf.bereich}
                    </Badge>
                    <Badge
                        variant={
                            ablauf.status === 'gueltig'
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        {status[ablauf.status] ?? ablauf.status}
                    </Badge>
                    {ablauf.verantwortlich && (
                        <span>
                            Verantwortlich:{' '}
                            <span translate="no">{ablauf.verantwortlich}</span>
                        </span>
                    )}
                    {ablauf.gueltig_ab && (
                        <span>{`gültig ab ${formatDate(ablauf.gueltig_ab)}`}</span>
                    )}
                    {ablauf.updated_at && (
                        <span>
                            {`geändert ${formatDateTime(ablauf.updated_at)}`}
                            {ablauf.geaendert_von_name && (
                                <>
                                    {' · '}
                                    <span translate="no">
                                        {ablauf.geaendert_von_name}
                                    </span>
                                </>
                            )}
                        </span>
                    )}
                    <span className="ml-auto flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                druckeAblauf(
                                    ablauf,
                                    gruppen,
                                    dokumente,
                                    bereiche[ablauf.bereich] ?? ablauf.bereich,
                                )
                            }
                        >
                            <Printer /> Drucken (PDF)
                        </Button>
                        {darfBearbeiten && (
                            <>
                                <Button asChild>
                                    <Link
                                        href={`/ablaeufe/${ablauf.id}/bearbeiten`}
                                    >
                                        <Pencil /> Bearbeiten
                                    </Link>
                                </Button>
                                <DeleteButton
                                    url={`/ablaeufe/${ablauf.id}`}
                                    title="Ablauf löschen?"
                                    description="Der Ablauf mit allen Schritten wird endgültig gelöscht. Statt löschen kann er auch archiviert werden."
                                    size="default"
                                />
                            </>
                        )}
                    </span>
                </div>

                {ablauf.phasen.map((p) => {
                    const schritte = ablauf.schritte.filter(
                        (s) => s.phase === p.key,
                    );

                    return (
                        <Card key={p.key}>
                            <CardHeader>
                                <CardTitle translate="no">{p.titel}</CardTitle>
                                <CardDescription>{`${schritte.length} Schritte`}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-10 text-right">
                                                Nr.
                                            </TableHead>
                                            <TableHead>Schritt</TableHead>
                                            <TableHead>Verantwortung</TableHead>
                                            <TableHead>
                                                Kommunikation an
                                            </TableHead>
                                            <TableHead>Dokumente</TableHead>
                                            <TableHead>Bemerkungen</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {schritte.map((s, i) => {
                                            const gegen = ablauf.schritte.find(
                                                (x) => x.id === s.gegenstueck,
                                            );

                                            return (
                                                <TableRow
                                                    key={s.id}
                                                    className="align-top"
                                                >
                                                    <TableCell className="text-right tabular-nums">
                                                        {i + 1}
                                                    </TableCell>
                                                    <TableCell className="max-w-sm whitespace-normal">
                                                        <span translate="no">
                                                            <TermText
                                                                text={s.text}
                                                            />
                                                        </span>
                                                        {s.gilt_fuer.length >
                                                            0 && (
                                                            <span className="mt-1 flex flex-wrap gap-1">
                                                                {s.gilt_fuer.map(
                                                                    (g) => (
                                                                        <Badge
                                                                            key={
                                                                                g
                                                                            }
                                                                            variant="secondary"
                                                                        >
                                                                            <span translate="no">
                                                                                {gruppe(
                                                                                    g,
                                                                                )}
                                                                            </span>
                                                                        </Badge>
                                                                    ),
                                                                )}
                                                            </span>
                                                        )}
                                                        {gegen && (
                                                            <span className="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                                                                <ArrowLeftRight className="size-3" />
                                                                <span translate="no">
                                                                    {`${phase(gegen.phase)} ${nummer(ablauf.schritte, gegen)}: ${gegen.text}`}
                                                                </span>
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell
                                                        className="whitespace-normal"
                                                        translate="no"
                                                    >
                                                        {s.verantwortung}
                                                    </TableCell>
                                                    <TableCell
                                                        className="whitespace-normal"
                                                        translate="no"
                                                    >
                                                        {s.kommunikation}
                                                    </TableCell>
                                                    <TableCell className="whitespace-normal">
                                                        <span className="grid gap-1">
                                                            {s.dokumente.map(
                                                                (id) => {
                                                                    const d =
                                                                        dokument(
                                                                            id,
                                                                        );

                                                                    return d ? (
                                                                        <a
                                                                            key={
                                                                                id
                                                                            }
                                                                            href={
                                                                                d.href
                                                                            }
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                            className="inline-flex items-center gap-1 text-primary underline-offset-2 hover:underline"
                                                                        >
                                                                            <FileText className="size-3.5" />
                                                                            <span translate="no">
                                                                                {
                                                                                    d.title
                                                                                }
                                                                            </span>
                                                                        </a>
                                                                    ) : null;
                                                                },
                                                            )}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell className="max-w-xs whitespace-normal">
                                                        {s.frist_tage !==
                                                            null && (
                                                            <Badge
                                                                variant="outline"
                                                                className="mb-1"
                                                            >
                                                                {fristText(
                                                                    s.frist_tage,
                                                                )}
                                                            </Badge>
                                                        )}
                                                        <span
                                                            className="block"
                                                            translate="no"
                                                        >
                                                            <TermText
                                                                text={
                                                                    s.bemerkung
                                                                }
                                                            />
                                                        </span>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>
        </>
    );
}

AblaufShow.layout = {
    breadcrumbs: [{ title: 'Abläufe Verwaltung', href: '/ablaeufe' }],
};
