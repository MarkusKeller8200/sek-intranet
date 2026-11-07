import { Head, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Zeile = {
    zeile: number;
    art: 'neu' | 'geaendert' | 'fehler';
    id: string;
    titel: string;
    meldungen: string[];
    aenderungen: Record<string, string>;
};

const ART = { neu: 'neu', geaendert: 'geändert', fehler: 'Fehler' } as const;

/** Vorschau des Excel-Imports: neu, geändert, Fehler – erst «Übernehmen» speichert. */
export default function GlossaryImport({
    token,
    datei,
    plan,
    felder,
    titel = 'Excel-Import prüfen',
    ziel,
    zurueck = '/glossar',
}: {
    token: string;
    datei: string;
    plan: Zeile[];
    felder: Record<string, string>;
    /** Import einer Liste (#157): eigene Überschrift, Ziel und Rückweg */
    titel?: string;
    ziel?: string;
    zurueck?: string;
}) {
    const zahl = (art: Zeile['art']) =>
        plan.filter((p) => p.art === art).length;

    return (
        <>
            <Head title="Glossar: Import" />
            <div className="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={titel}
                    description={`${datei}: ${zahl('neu')} neu, ${zahl('geaendert')} geändert, ${zahl('fehler')} Fehler. Unveränderte Zeilen werden nicht angezeigt. Es wird nie etwas gelöscht.`}
                    actions={
                        <>
                            <Button
                                disabled={zahl('neu') + zahl('geaendert') === 0}
                                onClick={() =>
                                    router.post(
                                        ziel ?? `/glossar/import/${token}`,
                                    )
                                }
                            >
                                <Check /> Übernehmen
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={zurueck}>Abbrechen</Link>
                            </Button>
                        </>
                    }
                />
                <Card>
                    <CardContent className="overflow-x-auto pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Zeile</TableHead>
                                    <TableHead>Art</TableHead>
                                    <TableHead>Eintrag</TableHead>
                                    <TableHead>
                                        Änderungen bzw. Fehler
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {plan.map((p) => (
                                    <TableRow
                                        key={`${p.zeile}`}
                                        className="align-top"
                                    >
                                        <TableCell className="tabular-nums">
                                            {p.zeile}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    p.art === 'fehler'
                                                        ? 'destructive'
                                                        : p.art === 'neu'
                                                          ? 'default'
                                                          : 'secondary'
                                                }
                                            >
                                                {ART[p.art]}
                                            </Badge>
                                        </TableCell>
                                        <TableCell
                                            className="whitespace-normal"
                                            translate="no"
                                        >
                                            {p.titel}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {p.art === 'fehler' ? (
                                                <ul className="list-disc pl-4 text-destructive">
                                                    {p.meldungen.map((m) => (
                                                        <li key={m}>{m}</li>
                                                    ))}
                                                </ul>
                                            ) : (
                                                <dl className="grid gap-0.5 text-xs">
                                                    {Object.entries(
                                                        p.aenderungen,
                                                    ).map(([f, v]) => (
                                                        <div
                                                            key={f}
                                                            className="flex gap-2"
                                                        >
                                                            <dt className="text-muted-foreground">
                                                                {felder[f] ?? f}
                                                                :
                                                            </dt>
                                                            <dd translate="no">
                                                                {v}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

GlossaryImport.layout = {
    breadcrumbs: [{ title: 'Glossar', href: '/glossar' }],
};
