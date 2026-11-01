import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Info } from 'lucide-react';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import type { GuiSummary } from '@/lib/gui-prototypes';
import { moduleIcon } from '@/lib/module-icons';

type Item = GuiSummary & {
    key: string;
    title: string;
    icon: string;
    description: string;
};

/** GUI-Prototypen: Übersicht über alle Bausteine mit Status, Versionen und offener Überarbeitung. */
export default function GuiPrototypesIndex({ items }: { items: Item[] }) {
    const openRework = items.reduce((n, i) => n + i.rework_open, 0);

    return (
        <>
            <Head title="GUI-Prototypen" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="GUI-Prototypen"
                    description="Bausteine der Oberfläche mit Beispielen, Status und Versionen. Die zuletzt freigegebene Version ist das verbindliche Template für das ganze Intranet."
                />

                <Alert>
                    <Info />
                    <AlertTitle>Ablauf</AlertTitle>
                    <AlertDescription>
                        <span>
                            Entwicklung → Prüfung → Freigegeben. Mit der Freigabe
                            einer neuen Version werden alle Seiten, die den
                            Baustein verwenden, zur Überarbeitung vorgemerkt.
                            Verbesserungswünsche werden beim Baustein erfasst und
                            fliessen in die nächste Version ein.
                        </span>
                    </AlertDescription>
                </Alert>

                {openRework > 0 && (
                    <p className="text-sm" data-testid="rework-total">
                        <Badge variant="secondary" className="mr-2 tabular-nums">
                            {openRework}
                        </Badge>
                        <span>Seiten müssen an freigegebene Versionen angepasst werden.</span>
                    </p>
                )}

                <Card className="py-0">
                    <CardContent className="px-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Baustein</TableHead>
                                    <TableHead>Verbindlich</TableHead>
                                    <TableHead>In Arbeit</TableHead>
                                    <TableHead className="text-right">
                                        Überarbeitung offen
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Wünsche
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => {
                                    const Icon = moduleIcon(item.icon);

                                    return (
                                        <TableRow
                                            key={item.key}
                                            data-testid={`gui-row-${item.key}`}
                                        >
                                            <TableCell>
                                                <Link
                                                    href={`/gui-prototypen/${item.key}`}
                                                    className="flex items-center gap-2 font-medium hover:underline"
                                                >
                                                    <Icon className="size-4 text-muted-foreground" />
                                                    <span>{item.title}</span>
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {item.released ? (
                                                    <span className="text-sm">
                                                        {`v${item.released.version} · ${formatDate(item.released.released_at ?? null)}`}
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        noch keine Freigabe
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {item.open ? (
                                                    <span className="flex items-center gap-2">
                                                        <span className="text-sm tabular-nums">
                                                            {`v${item.open.version}`}
                                                        </span>
                                                        <OptionBadge
                                                            list="guiStatuses"
                                                            value={item.open.status}
                                                        />
                                                    </span>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        –
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.rework_total > 0
                                                    ? `${item.rework_open} / ${item.rework_total}`
                                                    : '–'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {item.wishes_open || '–'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link
                                                    href={`/gui-prototypen/${item.key}`}
                                                    className="inline-flex text-primary"
                                                    title="Öffnen"
                                                >
                                                    <ArrowRight className="size-4" />
                                                    <span className="sr-only">Öffnen</span>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

GuiPrototypesIndex.layout = {
    breadcrumbs: [{ title: 'GUI-Prototypen', href: '/gui-prototypen' }],
};
