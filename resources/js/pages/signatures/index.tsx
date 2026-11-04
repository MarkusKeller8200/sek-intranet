import { Head, router } from '@inertiajs/react';
import {
    BellRing,
    ExternalLink,
    FlaskConical,
    Play,
    RefreshCw,
    Signature,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertDialog } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { postJson } from '@/lib/http';

type Signee = {
    name: string;
    email: string;
    order: number;
    status: string;
    signed_at: string | null;
};

type Auftrag = {
    id: string;
    module: string;
    reference: string;
    title: string;
    status: string;
    signees: Signee[];
    observers: string[];
    created_at?: string;
    sent_at?: string | null;
    completed_at?: string | null;
    fetched_at?: string | null;
    last_checked_at?: string | null;
    error?: string | null;
};

type Props = {
    auftraege: Auftrag[];
    status: Record<string, string>;
    personStatus: Record<string, string>;
    anbieter: { id: string; name: string; demo: boolean } | null;
    darfOeffnen: boolean;
    filter: { modul: string; monat: string; status: string };
};

type Rueckfrage = {
    title: string;
    description: string;
    confirmLabel: string;
    destructive?: boolean;
    run: () => void;
};

const OFFEN = ['versendet', 'teilweise'];

function variant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'unterschrieben') {
        return 'default';
    }
    if (['abgelehnt', 'zurueckgezogen', 'abgelaufen', 'fehler'].includes(status)) {
        return 'destructive';
    }

    return status === 'entwurf' ? 'outline' : 'secondary';
}

/** Unterschriften (#42): Aufträge mit Status je Person, erinnern, zurückziehen, löschen. */
export default function Signatures({
    auftraege,
    status,
    personStatus,
    anbieter,
    darfOeffnen,
    filter,
}: Props) {
    const [modul, setModul] = useState(filter.modul);
    const [monat, setMonat] = useState(filter.monat);
    const [nurStatus, setNurStatus] = useState(filter.status);
    const [frage, setFrage] = useState<Rueckfrage | null>(null);
    const modulListe = useMemo(
        () => [...new Set(auftraege.map((a) => a.module))].sort(),
        [auftraege],
    );
    const sichtbar = auftraege.filter(
        (a) =>
            (modul === '' || a.module === modul) &&
            (monat === '' || a.reference.includes(monat)) &&
            (nurStatus === '' || a.status === nurStatus),
    );

    const post = (url: string, data: Record<string, unknown> = {}) =>
        router.post(url, data, { preserveScroll: true });
    const oeffnen = async (id: string) => {
        const answer = await postJson<{ url?: string; message?: string }>(
            `/unterschriften/${id}/link`,
        );
        if (answer.url) {
            window.open(answer.url, '_blank', 'noopener,noreferrer');
        } else {
            toast.error(answer.message ?? 'Kein Link erhalten.');
        }
    };

    return (
        <>
            <Head title="Unterschriften" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Unterschriften"
                    description="Elektronische Unterschriften aus den Modulen (z. B. Monatsrapporte der Zeiterfassung)."
                />

                {!anbieter && (
                    <Alert>
                        <AlertTitle>Kein Anbieter eingerichtet</AlertTitle>
                        <AlertDescription>
                            Unter Prototyp → Plugins das Plugin DeepSign
                            einrichten und einschalten. Für das Senden
                            «Schreibende Aktionen erlauben» einschalten.
                        </AlertDescription>
                    </Alert>
                )}
                {anbieter?.demo && (
                    <Alert>
                        <FlaskConical />
                        <AlertTitle>Demo-Modus</AlertTitle>
                        <AlertDescription>
                            Es geht nichts an DeepSign. Mit «Demo: nächste
                            Unterschrift» lässt sich der Ablauf durchspielen.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-end gap-3">
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-modul">Modul</Label>
                        <NativeSelect
                            id="f-modul"
                            value={modul}
                            onChange={(e) => setModul(e.target.value)}
                        >
                            <option value="">alle</option>
                            {modulListe.map((m) => (
                                <option key={m} value={m}>
                                    {m}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-monat">Monat</Label>
                        <Input
                            id="f-monat"
                            type="month"
                            value={monat}
                            onChange={(e) => setMonat(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor="f-status">Status</Label>
                        <NativeSelect
                            id="f-status"
                            value={nurStatus}
                            onChange={(e) => setNurStatus(e.target.value)}
                        >
                            <option value="">alle</option>
                            {Object.entries(status).map(([k, v]) => (
                                <option key={k} value={k}>
                                    {v}
                                </option>
                            ))}
                        </NativeSelect>
                    </div>
                    <Button
                        variant="outline"
                        className="ml-auto"
                        disabled={!anbieter}
                        onClick={() => post('/unterschriften/aktualisieren')}
                    >
                        <RefreshCw /> Alle aktualisieren
                    </Button>
                </div>

                {sichtbar.length === 0 ? (
                    <EmptyState icon={Signature} title="Keine Aufträge">
                        Aufträge entstehen in den Modulen, z. B. beim Visum der
                        Zeiterfassung.
                    </EmptyState>
                ) : (
                    <Card>
                        <CardContent className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Dokument</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Unterzeichnende</TableHead>
                                        <TableHead>Zeitpunkte</TableHead>
                                        <TableHead className="w-0">
                                            <span className="sr-only">
                                                Aktionen
                                            </span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sichtbar.map((a) => (
                                        <TableRow key={a.id} className="align-top">
                                            <TableCell className="max-w-72 whitespace-normal">
                                                <span
                                                    className="font-medium"
                                                    translate="no"
                                                >
                                                    {a.title}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {a.module}
                                                </span>
                                                {a.error && (
                                                    <span
                                                        className="block text-xs text-destructive"
                                                        translate="no"
                                                    >
                                                        {a.error}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={variant(a.status)}>
                                                    {status[a.status] ??
                                                        a.status}
                                                </Badge>
                                                {a.status === 'unterschrieben' &&
                                                    !a.fetched_at && (
                                                        <span className="mt-1 block text-xs text-warning">
                                                            noch nicht abgelegt
                                                        </span>
                                                    )}
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                <ul className="grid gap-1 text-sm">
                                                    {a.signees.map((s, i) => (
                                                        <li
                                                            key={`${i}-${s.email}`}
                                                            className="flex flex-wrap items-center gap-2"
                                                        >
                                                            <span className="text-muted-foreground tabular-nums">
                                                                {s.order + 1}.
                                                            </span>
                                                            <span translate="no">
                                                                {s.name}
                                                            </span>
                                                            <Badge variant="outline">
                                                                {personStatus[
                                                                    s.status
                                                                ] ?? s.status}
                                                            </Badge>
                                                            {s.status ===
                                                                'offen' &&
                                                                OFFEN.includes(
                                                                    a.status,
                                                                ) && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            setFrage({
                                                                                title: 'Erinnerung senden?',
                                                                                description: `${s.name} erhält die Einladung zum Unterschreiben noch einmal per E-Mail.`,
                                                                                confirmLabel:
                                                                                    'Erinnern',
                                                                                run: () =>
                                                                                    post(
                                                                                        `/unterschriften/${a.id}/erinnern`,
                                                                                        {
                                                                                            person: i,
                                                                                        },
                                                                                    ),
                                                                            })
                                                                        }
                                                                    >
                                                                        <BellRing />{' '}
                                                                        Erinnern
                                                                    </Button>
                                                                )}
                                                        </li>
                                                    ))}
                                                </ul>
                                            </TableCell>
                                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                                {a.sent_at && (
                                                    <span className="block">
                                                        {`Versendet ${formatDateTime(a.sent_at)}`}
                                                    </span>
                                                )}
                                                {a.completed_at && (
                                                    <span className="block">
                                                        {`Abgeschlossen ${formatDateTime(a.completed_at)}`}
                                                    </span>
                                                )}
                                                {a.last_checked_at && (
                                                    <span className="block">
                                                        {`Abgefragt ${formatDateTime(a.last_checked_at)}`}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-wrap justify-end gap-1">
                                                    {anbieter?.demo &&
                                                        OFFEN.includes(
                                                            a.status,
                                                        ) && (
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() =>
                                                                    post(
                                                                        `/unterschriften/${a.id}/demo`,
                                                                    )
                                                                }
                                                            >
                                                                <FlaskConical />{' '}
                                                                Demo: nächste
                                                                Unterschrift
                                                            </Button>
                                                        )}
                                                    {a.status === 'entwurf' && (
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                post(
                                                                    `/unterschriften/${a.id}/starten`,
                                                                )
                                                            }
                                                        >
                                                            <Play /> Starten
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-label="Status aktualisieren"
                                                        title="Status aktualisieren"
                                                        disabled={!anbieter}
                                                        onClick={() =>
                                                            post(
                                                                `/unterschriften/${a.id}/aktualisieren`,
                                                            )
                                                        }
                                                    >
                                                        <RefreshCw />
                                                    </Button>
                                                    {darfOeffnen &&
                                                        a.status !== 'fehler' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="In DeepSign öffnen (Link 5 Minuten gültig, volle Kontrolle)"
                                                                aria-label="In DeepSign öffnen"
                                                                onClick={() =>
                                                                    void oeffnen(
                                                                        a.id,
                                                                    )
                                                                }
                                                            >
                                                                <ExternalLink />
                                                            </Button>
                                                        )}
                                                    {OFFEN.includes(
                                                        a.status,
                                                    ) && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                setFrage({
                                                                    title: 'Zurückziehen?',
                                                                    description:
                                                                        'Niemand kann danach mehr unterschreiben. Ein zurückgezogenes Dokument lässt sich nicht neu starten, nur neu senden.',
                                                                    confirmLabel:
                                                                        'Zurückziehen',
                                                                    destructive: true,
                                                                    run: () =>
                                                                        post(
                                                                            `/unterschriften/${a.id}/zurueckziehen`,
                                                                        ),
                                                                })
                                                            }
                                                        >
                                                            <Undo2 />{' '}
                                                            Zurückziehen
                                                        </Button>
                                                    )}
                                                    {!OFFEN.includes(
                                                        a.status,
                                                    ) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            aria-label="Löschen"
                                                            title="Löschen"
                                                            onClick={() =>
                                                                setFrage({
                                                                    title: 'Auftrag löschen?',
                                                                    description:
                                                                        'Der Auftrag verschwindet aus der Übersicht (im Änderungsverlauf nachvollziehbar). Ein bereits abgelegtes PDF bleibt beim Modul.',
                                                                    confirmLabel:
                                                                        'Löschen',
                                                                    destructive: true,
                                                                    run: () =>
                                                                        router.delete(
                                                                            `/unterschriften/${a.id}`,
                                                                            {
                                                                                preserveScroll: true,
                                                                            },
                                                                        ),
                                                                })
                                                            }
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            <AlertDialog
                open={frage !== null}
                onOpenChange={(open) => !open && setFrage(null)}
                title={frage?.title ?? ''}
                description={frage?.description ?? ''}
                confirmLabel={frage?.confirmLabel}
                destructive={frage?.destructive}
                onConfirm={() => {
                    frage?.run();
                    setFrage(null);
                }}
            />
        </>
    );
}

Signatures.layout = {
    breadcrumbs: [
        { title: 'Verwaltung', href: '/unterschriften' },
        { title: 'Unterschriften', href: '/unterschriften' },
    ],
};
