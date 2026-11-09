import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FlaskConical, RefreshCw, Settings } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { GLOSSARY_SOURCE } from '@/lib/glossary';

type SyncStatus = {
    vorhanden: boolean;
    eingeschaltet?: boolean;
    automatisch?: boolean;
    schreiben?: boolean;
    adresse?: string;
    token?: boolean;
    admin?: boolean;
    stand?: {
        cursor: number | null;
        release: number | null;
        letzter_erfolg: string | null;
        letzter_abruf: string | null;
        fehler: string | null;
    };
};

type Ergebnis = {
    status: string;
    message: string;
    columns: { key: string; label: string }[];
    rows: Record<string, string>[];
};

function Zeile({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-wrap gap-x-2">
            <dt className="text-muted-foreground">{label}:</dt>
            <dd translate="no">{value}</dd>
        </div>
    );
}

/** Sync mit dem EduKSX-Glossary im Glossar (#161): Stand, Probelauf, Sync. */
export default function GlossarySync({
    sync,
    ergebnis,
}: {
    sync: SyncStatus;
    ergebnis: Ergebnis | null;
}) {
    const [neu, setNeu] = useState(false);
    const [laeuft, setLaeuft] = useState(false);
    const bereit = sync.vorhanden && sync.eingeschaltet === true;
    const stand = sync.stand;
    const senden = (url: string) =>
        router.post(
            url,
            { neu },
            {
                onStart: () => setLaeuft(true),
                onFinish: () => setLaeuft(false),
            },
        );

    return (
        <>
            <Head title="Glossar: EduKSX-Glossary" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Button
                    variant="ghost"
                    size="sm"
                    className="self-start"
                    asChild
                >
                    <Link href="/glossar">
                        <ArrowLeft /> Zum Glossar
                    </Link>
                </Button>
                <PageHeader
                    title="EduKSX-Glossary"
                    description="Begriffe aus dem EduKSX-Glossary übernehmen und aktuell halten. Eigene Formen, «Intern genutzt in», Notiz und Suchbegriffe bleiben im Intranet; es wird nie etwas gelöscht."
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            Stand
                            {bereit ? (
                                <Badge variant="secondary">eingeschaltet</Badge>
                            ) : (
                                <Badge variant="outline">
                                    noch nicht eingerichtet
                                </Badge>
                            )}
                            {bereit && sync.automatisch && (
                                <Badge variant="secondary">automatisch</Badge>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        {!bereit && (
                            <p>
                                Sync noch nicht eingerichtet. Die Administration
                                schaltet das Plugin «EduKSX-Glossary» ein,
                                sobald die Plattform erreichbar ist.
                            </p>
                        )}
                        <dl className="grid gap-1">
                            <Zeile
                                label="Adresse"
                                value={sync.adresse ?? '–'}
                            />
                            <Zeile
                                label="Letzter Sync"
                                value={
                                    stand?.letzter_erfolg
                                        ? formatDateTime(stand.letzter_erfolg)
                                        : 'noch nie'
                                }
                            />
                            <Zeile
                                label="Datenstand"
                                value={
                                    typeof stand?.release === 'number'
                                        ? `${stand.release} (Cursor ${stand.cursor ?? '–'})`
                                        : '–'
                                }
                            />
                            <Zeile
                                label="Vorschläge"
                                value={
                                    sync.token
                                        ? 'möglich (Token hinterlegt)'
                                        : 'nicht möglich (kein Token)'
                                }
                            />
                        </dl>
                        {stand?.fehler && (
                            <p className="text-destructive">
                                {`Letzter Abruf ${stand.letzter_abruf ? formatDateTime(stand.letzter_abruf) : ''} mit Fehler: ${stand.fehler}`}
                            </p>
                        )}
                        {sync.admin && sync.vorhanden && (
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="self-start"
                            >
                                <Link href="/plugins/eduksx-glossary">
                                    <Settings /> Plugin einrichten
                                </Link>
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {bereit && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Synchronisieren</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm">
                            <p>
                                Zuerst den Probelauf prüfen: Er zeigt, was sich
                                ändern würde, und speichert nichts.
                            </p>
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    checked={neu}
                                    onCheckedChange={(c) => setNeu(c === true)}
                                />
                                Vollstand neu laden (sonst nur Änderungen)
                            </label>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    disabled={laeuft}
                                    onClick={() =>
                                        senden('/glossar/sync/probelauf')
                                    }
                                >
                                    <FlaskConical /> Probelauf
                                </Button>
                                <Button
                                    disabled={laeuft}
                                    onClick={() => senden('/glossar/sync')}
                                >
                                    <RefreshCw /> Mit EduKSX-Glossary
                                    synchronisieren
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {ergebnis && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Ergebnis des Probelaufs</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 overflow-x-auto">
                            <p
                                className={
                                    ergebnis.status === 'success'
                                        ? 'text-sm'
                                        : 'text-sm text-destructive'
                                }
                            >
                                {ergebnis.message}
                            </p>
                            {ergebnis.rows.length > 0 && (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {ergebnis.columns.map((c) => (
                                                <TableHead key={c.key}>
                                                    {c.label}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {ergebnis.rows.map((r, i) => (
                                            <TableRow key={i}>
                                                {ergebnis.columns.map((c) => (
                                                    <TableCell
                                                        key={c.key}
                                                        className="whitespace-normal"
                                                        translate="no"
                                                    >
                                                        {r[c.key]}
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                <p className="text-xs text-muted-foreground">
                    {GLOSSARY_SOURCE}
                </p>
            </div>
        </>
    );
}

GlossarySync.layout = {
    breadcrumbs: [{ title: 'Glossar', href: '/glossar' }],
};
