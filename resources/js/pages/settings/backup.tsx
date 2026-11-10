import { Head, router, useForm } from '@inertiajs/react';
import {
    DatabaseBackup,
    Download,
    HardDrive,
    History,
    LockKeyhole,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import DeleteButton from '@/components/delete-button';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertDialog } from '@/components/ui/alert-dialog';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatFileSize } from '@/lib/format';

type ServerSicherung = {
    name: string;
    size: number;
    created_at: string;
    art: 'manuell' | 'vor-wiederherstellung';
};

type Vorschau = {
    version: string;
    erstellt_am: string;
    erstellt_von: string | null;
    quelle: string;
    zugangsdaten: 'klartext' | 'ohne' | 'server';
    sammlungen: Record<string, number>;
    dateien: number;
    dateien_bytes: number;
};

const ZUGANGSDATEN: Record<Vorschau['zugangsdaten'], string> = {
    klartext:
        'Enthalten (Sicherung mit Passwort) – werden mit dem Schlüssel dieses Servers neu verschlüsselt.',
    ohne: 'Nicht enthalten – Plugin-Zugangsdaten danach neu eingeben.',
    server: 'Mit dem Schlüssel des Servers verschlüsselt – nur auf diesem Server lesbar.',
};

function xsrf(): string {
    return decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );
}

/** Sicherung herunterladen: optional mit Passwort (verschlüsselt, mit Zugangsdaten). */
function Herunterladen({ minPasswort }: { minPasswort: number }) {
    const [passwort, setPasswort] = useState('');
    const [wiederholen, setWiederholen] = useState('');
    const [fehler, setFehler] = useState<string | undefined>();
    const [laeuft, setLaeuft] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setFehler(undefined);
        if (passwort !== '' && passwort.length < minPasswort) {
            setFehler(
                `Das Passwort braucht mindestens ${minPasswort} Zeichen.`,
            );

            return;
        }
        if (passwort !== wiederholen) {
            setFehler('Die beiden Eingaben stimmen nicht überein.');

            return;
        }
        setLaeuft(true);
        try {
            const response = await fetch(
                '/einstellungen/sicherung/herunterladen',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-XSRF-TOKEN': xsrf(),
                    },
                    body: JSON.stringify({
                        passwort,
                        passwort_confirmation: wiederholen,
                    }),
                },
            );
            if (!response.ok) {
                const data: unknown = await response.json().catch(() => null);
                const message =
                    typeof data === 'object' &&
                    data !== null &&
                    'message' in data &&
                    typeof data.message === 'string'
                        ? data.message
                        : `Sicherung nicht möglich (HTTP ${response.status}).`;
                setFehler(message);

                return;
            }
            const name =
                /filename="?([^";]+)"?/.exec(
                    response.headers.get('Content-Disposition') ?? '',
                )?.[1] ?? 'sicherung.zip';
            const url = URL.createObjectURL(await response.blob());
            const a = document.createElement('a');
            a.href = url;
            a.download = name;
            a.click();
            URL.revokeObjectURL(url);
            toast.success('Sicherung heruntergeladen.');
            setPasswort('');
            setWiederholen('');
        } finally {
            setLaeuft(false);
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Download className="size-5" /> Sicherung herunterladen
                </CardTitle>
                <CardDescription>
                    Alle Daten, Dokumente und Dateien als eine Datei – zum
                    Ablegen auf dem eigenen Rechner.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="b-passwort"
                        label="Passwort (optional)"
                        hint={`Mindestens ${minPasswort} Zeichen. Ohne Passwort: normale ZIP-Datei ohne Zugangsdaten.`}
                        error={fehler}
                    >
                        <Input
                            id="b-passwort"
                            type="password"
                            autoComplete="new-password"
                            value={passwort}
                            onChange={(e) => setPasswort(e.target.value)}
                        />
                    </FormField>
                    <FormField id="b-passwort2" label="Passwort wiederholen">
                        <Input
                            id="b-passwort2"
                            type="password"
                            autoComplete="new-password"
                            value={wiederholen}
                            onChange={(e) => setWiederholen(e.target.value)}
                            disabled={passwort === ''}
                        />
                    </FormField>
                    {passwort !== '' && (
                        <Alert className="sm:col-span-2">
                            <LockKeyhole />
                            <AlertTitle>Verschlüsselt mit Passwort</AlertTitle>
                            <AlertDescription>
                                Die Datei enthält auch die Zugangsdaten der
                                Plugins. Ohne das Passwort lässt sie sich nicht
                                öffnen und nicht wiederherstellen – Passwort im
                                Passwort-Manager ablegen.
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="sm:col-span-2">
                        <Button type="submit" disabled={laeuft}>
                            <Download />
                            {laeuft
                                ? 'Sicherung wird erstellt …'
                                : passwort !== ''
                                  ? 'Verschlüsselt herunterladen'
                                  : 'Als ZIP herunterladen'}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    );
}

/** Sicherungen auf dem Server (manuell und automatisch vor jeder Wiederherstellung). */
function AufDemServer({ sicherungen }: { sicherungen: ServerSicherung[] }) {
    const [laeuft, setLaeuft] = useState(false);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <HardDrive className="size-5" /> Auf dem Server
                </CardTitle>
                <CardDescription>
                    Vor jeder Wiederherstellung wird der aktuelle Stand hier
                    automatisch gesichert. Zugangsdaten bleiben mit dem
                    Schlüssel dieses Servers verschlüsselt.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div>
                    <Button
                        variant="outline"
                        disabled={laeuft}
                        onClick={() =>
                            router.post(
                                '/einstellungen/sicherung/server',
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setLaeuft(true),
                                    onFinish: () => setLaeuft(false),
                                },
                            )
                        }
                    >
                        <DatabaseBackup />
                        {laeuft
                            ? 'Sicherung wird erstellt …'
                            : 'Jetzt auf dem Server sichern'}
                    </Button>
                </div>
                {sicherungen.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Sicherungen auf dem Server.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Erstellt</TableHead>
                                    <TableHead>Art</TableHead>
                                    <TableHead>Grösse</TableHead>
                                    <TableHead className="sr-only">
                                        Aktionen
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sicherungen.map((s) => (
                                    <TableRow key={s.name}>
                                        <TableCell>
                                            {formatDateTime(s.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    s.art === 'manuell'
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                            >
                                                {s.art === 'manuell'
                                                    ? 'Manuell'
                                                    : 'Vor Wiederherstellung'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {formatFileSize(s.size)}
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex justify-end gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/einstellungen/sicherung/server/${s.name}`}
                                                    >
                                                        <Download />
                                                        Herunterladen
                                                    </a>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        router.post(
                                                            `/einstellungen/sicherung/server/${s.name}/vorbereiten`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <History />
                                                    Wiederherstellen …
                                                </Button>
                                                <DeleteButton
                                                    url={`/einstellungen/sicherung/server/${s.name}`}
                                                    title="Sicherung löschen?"
                                                    description="Die Sicherung wird vom Server entfernt und lässt sich nicht zurückholen."
                                                    size="icon"
                                                />
                                            </span>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/** Datei hochladen und prüfen (noch keine Änderung). */
function Hochladen({ maxUploadMb }: { maxUploadMb: number }) {
    const form = useForm<{ datei: File | null; passwort: string }>({
        datei: null,
        passwort: '',
    });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/einstellungen/sicherung/vorbereiten', {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => form.setData('passwort', ''),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
            <FormField
                id="r-datei"
                label="Sicherungsdatei"
                hint={`.zip oder .intranet-backup, höchstens ${maxUploadMb} MB`}
                error={form.errors.datei}
            >
                <Input
                    id="r-datei"
                    type="file"
                    accept=".zip,.intranet-backup"
                    onChange={(e) =>
                        form.setData('datei', e.target.files?.[0] ?? null)
                    }
                />
            </FormField>
            <FormField
                id="r-passwort"
                label="Passwort (bei verschlüsselter Sicherung)"
                error={form.errors.passwort}
            >
                <Input
                    id="r-passwort"
                    type="password"
                    autoComplete="off"
                    value={form.data.passwort}
                    onChange={(e) => form.setData('passwort', e.target.value)}
                />
            </FormField>
            <div className="sm:col-span-2">
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing || form.data.datei === null}
                >
                    <Upload />
                    {form.processing
                        ? 'Wird geprüft …'
                        : 'Hochladen und prüfen'}
                </Button>
            </div>
        </form>
    );
}

/** Vorschau der geprüften Sicherung und Bestätigung. */
function Bestaetigen({
    vorschau,
    aktuell,
}: {
    vorschau: Vorschau;
    aktuell: Record<string, number>;
}) {
    const form = useForm({ bestaetigung: '' });
    const [frage, setFrage] = useState(false);
    const namen = Array.from(
        new Set([...Object.keys(vorschau.sammlungen), ...Object.keys(aktuell)]),
    ).sort();

    return (
        <div className="grid gap-4">
            <Alert>
                <History />
                <AlertTitle>Sicherung geprüft</AlertTitle>
                <AlertDescription>
                    <span>
                        Stand {formatDateTime(vorschau.erstellt_am)} · Version{' '}
                        {vorschau.version}
                        {vorschau.quelle !== '' && (
                            <>
                                {' '}
                                · von{' '}
                                <span translate="no">{vorschau.quelle}</span>
                            </>
                        )}
                        {vorschau.erstellt_von && (
                            <>
                                {' '}
                                · erstellt von{' '}
                                <span translate="no">
                                    {vorschau.erstellt_von}
                                </span>
                            </>
                        )}
                    </span>
                    <span>
                        {vorschau.dateien} Dateien (
                        {formatFileSize(vorschau.dateien_bytes)}). Zugangsdaten:{' '}
                        {ZUGANGSDATEN[vorschau.zugangsdaten]}
                    </span>
                </AlertDescription>
            </Alert>
            <div className="max-h-80 overflow-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Sammlung</TableHead>
                            <TableHead className="text-right">
                                In der Sicherung
                            </TableHead>
                            <TableHead className="text-right">
                                Aktuell
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {namen.map((n) => (
                            <TableRow key={n}>
                                <TableCell translate="no">{n}</TableCell>
                                <TableCell className="text-right">
                                    {vorschau.sammlungen[n] ?? 0}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground">
                                    {aktuell[n] ?? 0}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <Alert variant="destructive">
                <TriangleAlert />
                <AlertTitle>Alle Daten werden ersetzt</AlertTitle>
                <AlertDescription>
                    Alle aktuellen Daten, Dokumente und Dateien werden durch
                    diesen Stand ersetzt. Der aktuelle Stand wird vorher auf dem
                    Server gesichert. Danach ist eine neue Anmeldung nötig.
                </AlertDescription>
            </Alert>
            <form
                className="flex flex-wrap items-end gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    setFrage(true);
                }}
            >
                <FormField
                    id="r-bestaetigung"
                    label="Zur Bestätigung «WIEDERHERSTELLEN» eintippen"
                    error={form.errors.bestaetigung}
                >
                    <Input
                        id="r-bestaetigung"
                        autoComplete="off"
                        className="w-64"
                        value={form.data.bestaetigung}
                        onChange={(e) =>
                            form.setData('bestaetigung', e.target.value)
                        }
                    />
                </FormField>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={
                        form.processing ||
                        form.data.bestaetigung !== 'WIEDERHERSTELLEN'
                    }
                >
                    <History />
                    {form.processing
                        ? 'Wird wiederhergestellt …'
                        : 'Wiederherstellen …'}
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() =>
                        router.delete('/einstellungen/sicherung/vorbereitung', {
                            preserveScroll: true,
                        })
                    }
                >
                    Abbrechen
                </Button>
            </form>
            <AlertDialog
                open={frage}
                onOpenChange={setFrage}
                title="Jetzt wiederherstellen?"
                description="Alle Daten werden durch die Sicherung ersetzt. Der aktuelle Stand wird vorher auf dem Server gesichert."
                confirmLabel="Wiederherstellen"
                destructive
                onConfirm={() =>
                    form.post('/einstellungen/sicherung/wiederherstellen')
                }
            />
        </div>
    );
}

/** Einstellungen → Sicherung (#174): herunterladen, auf dem Server sichern, wiederherstellen. */
export default function BackupSettings({
    sicherungen,
    vorschau,
    aktuell,
    minPasswort,
    maxUploadMb,
}: {
    sicherungen: ServerSicherung[];
    vorschau: Vorschau | null;
    aktuell: Record<string, number> | null;
    minPasswort: number;
    maxUploadMb: number;
}) {
    return (
        <>
            <Head title="Sicherung" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Sicherung"
                    description="Ganze Installation sichern und wiederherstellen: alle Sammlungen, Dokumente und Dateien. Nicht enthalten sind der Schlüssel des Servers, Logs und Sitzungen."
                />
                <Herunterladen minPasswort={minPasswort} />
                <AufDemServer sicherungen={sicherungen} />
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <History className="size-5" /> Wiederherstellen
                        </CardTitle>
                        <CardDescription>
                            Zuerst wird die Sicherung geprüft (Prüfsummen,
                            Version, Inhalt) – geändert wird erst nach der
                            Bestätigung.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {vorschau !== null && aktuell !== null ? (
                            <Bestaetigen
                                vorschau={vorschau}
                                aktuell={aktuell}
                            />
                        ) : (
                            <Hochladen maxUploadMb={maxUploadMb} />
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BackupSettings.layout = {
    breadcrumbs: [{ title: 'Sicherung', href: '/einstellungen/sicherung' }],
};
