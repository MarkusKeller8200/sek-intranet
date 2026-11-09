import { Head, Link, router } from '@inertiajs/react';
import {
    Bell,
    Download,
    ListTree,
    ShieldAlert,
    TriangleAlert,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
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
import MonatNavigation from '../components/monat-navigation';
import StatusBadge from '../components/status-badge';
import type { Status, Summen } from '../lib/types';
import { stunden } from '../lib/types';

type Person = {
    id: string;
    name: string;
    email: string;
    status: Status['status'];
    eintraege: number;
    summen: Summen;
    /** Arbeitstage ohne Eintrag bis heute (#33) */
    fehlend?: number;
    /** Tage mit Fehlern bzw. Hinweisen (#167) */
    pruefen?: { fehler: number; hinweise: number };
    erinnert_at?: string | null;
};

/** Monatsvisum: alle Personen mit Einträgen im Monat, Status und Stunden; Export für die Lohnbuchhaltung. */
export default function ZeiterfassungVisum({
    monat,
    personen,
    status,
}: {
    monat: string;
    personen: Person[];
    status: Record<string, string>;
}) {
    const offen = personen.filter((p) => p.status === 'eingereicht').length;
    const nichtEingereicht = personen.filter(
        (p) => p.status === 'offen' || p.status === 'zurueck',
    ).length;

    return (
        <>
            <Head title="Zeiterfassung – Visum" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Zeiterfassung – Visum"
                    description="Eingereichte Monate prüfen und visieren. Personen mit ClickTime schliessen den Monat in ClickTime ab. Der Export für die Lohnbuchhaltung enthält standardmässig nur visierte und abgeschlossene Monate."
                    actions={
                        <Button asChild variant="outline">
                            <Link href="/zeiterfassung/buchungsarten">
                                <ListTree /> Buchungsarten
                            </Link>
                        </Button>
                    }
                />
                <div className="flex flex-wrap items-center gap-3">
                    <MonatNavigation
                        monat={monat}
                        basis="/zeiterfassung/visum"
                    />
                    <span className="text-sm text-muted-foreground">
                        {`${offen} zu visieren`}
                    </span>
                    <span className="ml-auto flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            disabled={nichtEingereicht === 0}
                            onClick={() =>
                                router.post(
                                    '/zeiterfassung/visum/erinnerung',
                                    { monat },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Bell /> {`Erinnern (${nichtEingereicht})`}
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={`/zeiterfassung/visum/export?monat=${monat}`}
                            >
                                <Download /> Export Lohnbuchhaltung (CSV)
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={`/zeiterfassung/visum/export?monat=${monat}&art=details&nur_visiert=0`}
                            >
                                <Download /> Alle Einträge (CSV)
                            </a>
                        </Button>
                    </span>
                </div>
                <Card>
                    <CardContent>
                        {personen.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                In diesem Monat hat noch niemand Zeit erfasst.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Person</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Einträge
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Arbeit
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Abwesenheit
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {personen.map((p) => (
                                            <TableRow key={p.id}>
                                                <TableCell>
                                                    <Link
                                                        href={`/zeiterfassung/visum/${p.id}?monat=${monat}`}
                                                        className="font-medium underline-offset-4 hover:underline"
                                                        translate="no"
                                                    >
                                                        {p.name}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="flex flex-wrap items-center gap-1">
                                                        <StatusBadge
                                                            status={p.status}
                                                            labels={status}
                                                        />
                                                        {(p.fehlend ?? 0) >
                                                            0 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {`${p.fehlend} Tage ohne Eintrag`}
                                                            </span>
                                                        )}
                                                        {(p.pruefen?.fehler ??
                                                            0) > 0 && (
                                                            <span className="inline-flex items-center gap-1 text-xs text-destructive">
                                                                <ShieldAlert
                                                                    className="size-4"
                                                                    aria-hidden
                                                                />
                                                                {`${p.pruefen?.fehler} Tage mit Fehlern`}
                                                            </span>
                                                        )}
                                                        {(p.pruefen?.hinweise ??
                                                            0) > 0 && (
                                                            <span className="inline-flex items-center gap-1 text-xs text-warning">
                                                                <TriangleAlert
                                                                    className="size-4"
                                                                    aria-hidden
                                                                />
                                                                {`${p.pruefen?.hinweise} Tage prüfen`}
                                                            </span>
                                                        )}
                                                        {p.erinnert_at && (
                                                            <span className="text-xs text-muted-foreground">
                                                                erinnert
                                                            </span>
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {p.eintraege}
                                                </TableCell>
                                                <TableCell
                                                    className="text-right tabular-nums"
                                                    translate="no"
                                                >
                                                    {stunden(p.summen.arbeit)}
                                                </TableCell>
                                                <TableCell
                                                    className="text-right tabular-nums"
                                                    translate="no"
                                                >
                                                    {stunden(
                                                        p.summen.abwesenheit,
                                                    )}
                                                </TableCell>
                                                <TableCell
                                                    className="text-right font-semibold tabular-nums"
                                                    translate="no"
                                                >
                                                    {stunden(p.summen.total)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ZeiterfassungVisum.layout = {
    breadcrumbs: [
        { title: 'Zeiterfassung', href: '/zeiterfassung' },
        { title: 'Visum', href: '/zeiterfassung/visum' },
    ],
};
