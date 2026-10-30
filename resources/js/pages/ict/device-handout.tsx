import { Head } from '@inertiajs/react';
import {
    Download,
    FileDown,
    FileSpreadsheet,
    FileUp,
    KeyRound,
    RefreshCw,
    ShieldCheck,
    TriangleAlert,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ChangeEvent } from 'react';
import { toast } from 'sonner';
import ClassImportDialog from '@/components/class-import-dialog';
import type { ImportedRow } from '@/components/class-import-dialog';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { PS_SCRIPT } from '@/lib/access-codes/assets';
import {
    DEMO_INPUT,
    downloadBlob,
    generatePassword,
    generatePin,
    getEmailIssue,
    parseCsv,
    parseInput,
    SAFE_SYMBOLS,
    slipsHtml,
    toCsv,
} from '@/lib/access-codes/generator';
import type { PasswordStyle, Student } from '@/lib/access-codes/generator';
import { buildStudentPdf } from '@/lib/access-codes/pdf';
import { cn } from '@/lib/utils';

type Planned = { features: string[] };

/** Zeilen im Format der Eingabe (Semikolon, Reihenfolge wie im Hinweis). */
const toLines = (rows: ImportedRow[]) =>
    rows
        .map((r) =>
            [
                r.nachname,
                r.vorname,
                r.benutzer,
                r.inventar,
                r.lehrperson,
                r.klasse,
            ]
                .map((v) => v.replace(/[;\t\n]/g, ' '))
                .join(';'),
        )
        .join('\n');

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm';

export default function DeviceHandout({
    planned,
    mailDomain,
}: {
    planned: Planned;
    mailDomain: string;
}) {
    const [input, setInput] = useState('');
    const [length, setLength] = useState(10);
    const [style, setStyle] = useState<PasswordStyle>('animalcolor');
    const [avoidAmbig, setAvoidAmbig] = useState(true);
    const [symbols, setSymbols] = useState<string[]>(['.']);
    const [ort, setOrt] = useState('Bülach');
    const [data, setData] = useState<Student[]>([]);
    const [busy, setBusy] = useState(false);
    const [importOpen, setImportOpen] = useState(false);

    const importRows = (rows: ImportedRow[], replace: boolean) => {
        const lines = toLines(rows);
        setInput((current) =>
            replace || !current.trim()
                ? lines
                : `${current.trimEnd()}\n${lines}`,
        );
        toast.success(`${rows.length} Schüler:innen übernommen.`);
    };

    const newCredentials = () => ({
        pw: generatePassword(length, style, avoidAmbig, symbols),
        pin: generatePin(),
    });

    const generateAll = () => {
        setData(parseInput(input).map((p) => ({ ...p, ...newCredentials() })));
    };

    const regenerateOne = (index: number) => {
        setData((rows) =>
            rows.map((row, i) =>
                i === index ? { ...row, ...newCredentials() } : row,
            ),
        );
    };

    const regenerateAll = () => {
        setData((rows) => rows.map((row) => ({ ...row, ...newCredentials() })));
    };

    const toggleSymbol = (symbol: string, on: boolean) => {
        setSymbols((current) =>
            on
                ? SAFE_SYMBOLS.filter(
                      (s) => s === symbol || current.includes(s),
                  )
                : current.filter((s) => s !== symbol),
        );
    };

    const importCsv = (event: ChangeEvent<HTMLInputElement>) => {
        const input = event.target;
        const file = input.files?.[0];
        if (!file) {
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const result = parseCsv(
                typeof reader.result === 'string' ? reader.result : '',
            );
            if ('error' in result) {
                toast.error(result.error);
            } else {
                setData(result.data);
                toast.success(`${result.data.length} Einträge eingelesen.`);
            }
            input.value = ''; // dieselbe Datei später erneut wählbar
        };
        reader.onerror = () =>
            toast.error('Datei konnte nicht gelesen werden.');
        reader.readAsText(file, 'UTF-8');
    };

    const downloadPdf = async () => {
        if (data.length === 0) {
            return;
        }
        setBusy(true);
        try {
            const bytes = await buildStudentPdf(data, ort);
            downloadBlob(
                bytes as BlobPart,
                'application/pdf',
                'schueler-zugangsdaten.pdf',
            );
        } finally {
            setBusy(false);
        }
    };

    const issues = useMemo(
        () => data.map((d) => getEmailIssue(d.benutzer)),
        [data],
    );
    const invalidCount = issues.filter((issue) => issue !== null).length;

    return (
        <>
            <Head title="Geräteausgabe" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Geräteausgabe"
                    description="Zugangsdaten-Generator: Passwörter und PIN für Schüler-Logins (iPad und Microsoft 365), Zugangskärtchen und PDF je Schüler:in."
                />

                <div className="flex items-start gap-2 rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Lokal im Browser – keine Daten werden versendet oder im
                        Intranet gespeichert. Nach dem Schliessen der Seite sind
                        Liste und Passwörter weg; vorher CSV exportieren.
                    </span>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>1 · Schülerliste einfügen</CardTitle>
                        <CardDescription>
                            Ein Schüler pro Zeile. Trennzeichen Komma, Semikolon
                            oder Tabulator werden automatisch erkannt –
                            Reihenfolge Nachname / Vorname / Benutzername
                            (E-Mail) / iPad-Inventarnummer / Lehrperson /
                            Klasse. Alle Spalten ab Benutzername sind optional –
                            fehlt eine, einfach leer lassen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Button onClick={() => setImportOpen(true)}>
                                <Users /> Schüler:innen importieren
                            </Button>
                            <span className="text-xs text-muted-foreground">
                                Klassenliste (Excel oder CSV) – Jahrgang und
                                Klasse wählen, z. B. alle 1. Sek-Klassen.
                            </span>
                        </div>
                        <ClassImportDialog
                            open={importOpen}
                            onOpenChange={setImportOpen}
                            mailDomain={mailDomain}
                            onImport={importRows}
                        />
                        <Textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder={DEMO_INPUT.split('\n')
                                .slice(0, 3)
                                .join('\n')}
                            className="min-h-40 font-mono text-sm"
                            translate="no"
                            spellCheck={false}
                        />

                        <div className="flex flex-wrap items-end gap-6">
                            <label className="grid gap-1 text-sm">
                                Passwortlänge
                                <select
                                    className={selectClass}
                                    value={length}
                                    onChange={(e) =>
                                        setLength(Number(e.target.value))
                                    }
                                >
                                    <option value={8}>8 Zeichen</option>
                                    <option value={10}>10 Zeichen</option>
                                    <option value={12}>12 Zeichen</option>
                                </select>
                            </label>
                            <label className="grid gap-1 text-sm">
                                Passwort-Stil
                                <select
                                    className={selectClass}
                                    value={style}
                                    onChange={(e) =>
                                        setStyle(
                                            e.target.value as PasswordStyle,
                                        )
                                    }
                                >
                                    <option value="animalcolor">
                                        Farbe + Tier + Zeichen + Zahl
                                    </option>
                                    <option value="word">
                                        Kindgerecht (Wort + Zahl + Zeichen)
                                    </option>
                                    <option value="random">
                                        Zufällig (voll random)
                                    </option>
                                </select>
                            </label>
                            <label className="flex items-center gap-2 pb-2 text-sm">
                                <Checkbox
                                    checked={avoidAmbig}
                                    onCheckedChange={(
                                        v: boolean | 'indeterminate',
                                    ) => setAvoidAmbig(v === true)}
                                />
                                Verwechselbare Zeichen vermeiden (I, l, 1, O, 0)
                            </label>
                        </div>

                        <div className="grid gap-2">
                            <span className="text-sm">
                                Sonderzeichen (auswählen, welche verwendet
                                werden dürfen)
                            </span>
                            <div
                                className="flex flex-wrap gap-4"
                                translate="no"
                            >
                                {SAFE_SYMBOLS.map((symbol) => (
                                    <label
                                        key={symbol}
                                        className="flex cursor-pointer items-center gap-1.5 font-mono"
                                    >
                                        <Checkbox
                                            checked={symbols.includes(symbol)}
                                            onCheckedChange={(
                                                v: boolean | 'indeterminate',
                                            ) =>
                                                toggleSymbol(symbol, v === true)
                                            }
                                        />
                                        {symbol}
                                    </label>
                                ))}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Alle vorausgewählten Zeichen liegen auf
                                DE/CH/US/UK-Tastaturen an derselben Position.
                                Ohne Auswahl werden alle verwendet.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button
                                onClick={generateAll}
                                disabled={!input.trim()}
                            >
                                <KeyRound /> Passwörter & PINs generieren
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setInput(DEMO_INPUT)}
                            >
                                Beispiel einfügen
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            2 · Ergebnis
                            {data.length > 0 && (
                                <Badge>
                                    {data.length === 1
                                        ? '1 Schüler/in'
                                        : `${data.length} Schüler/innen`}
                                </Badge>
                            )}
                        </CardTitle>
                        <CardDescription>
                            Neu generiert? Oder eine frühere zugangsdaten.csv
                            wieder einlesen, um z. B. das PDF erneut zu
                            erzeugen, ohne Passwörter/PINs neu zu würfeln.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div>
                            <Button variant="outline" asChild>
                                <label className="cursor-pointer">
                                    <FileUp /> CSV importieren
                                    <input
                                        type="file"
                                        accept=".csv"
                                        className="hidden"
                                        onChange={importCsv}
                                    />
                                </label>
                            </Button>
                        </div>

                        {data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Noch keine Daten generiert. Liste oben einfügen
                                und auf «Generieren» klicken.
                            </p>
                        ) : (
                            <>
                                {invalidCount > 0 && (
                                    <div className="flex items-center gap-2 rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                                        <TriangleAlert className="size-4 shrink-0" />
                                        {invalidCount === 1
                                            ? '1 E-Mail-Adresse sieht ungültig aus oder fehlt – rot markiert unten.'
                                            : `${invalidCount} E-Mail-Adressen sehen ungültig aus oder fehlen – rot markiert unten.`}
                                    </div>
                                )}
                                <div className="overflow-x-auto rounded-md border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50 text-left text-xs text-muted-foreground uppercase">
                                            <tr>
                                                {[
                                                    'Nachname',
                                                    'Vorname',
                                                    'Klasse',
                                                    'Lehrperson',
                                                    'Benutzername',
                                                    'iPad-Nr.',
                                                    'Passwort',
                                                    'PIN',
                                                    '',
                                                ].map((h) => (
                                                    <th
                                                        key={h}
                                                        className="px-3 py-2 font-medium"
                                                    >
                                                        {h}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody translate="no">
                                            {data.map((d, i) => {
                                                const issue = issues[i];

                                                return (
                                                    <tr
                                                        key={i}
                                                        className={cn(
                                                            'border-t',
                                                            issue
                                                                ? 'bg-rose-50 dark:bg-rose-950/40'
                                                                : 'hover:bg-muted/40',
                                                        )}
                                                    >
                                                        <td className="px-3 py-2">
                                                            {d.nachname}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {d.vorname}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {d.klasse}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {d.lehrperson}
                                                        </td>
                                                        <td
                                                            className={cn(
                                                                'px-3 py-2 font-mono text-xs',
                                                                issue &&
                                                                    'text-rose-700 dark:text-rose-300',
                                                            )}
                                                            title={
                                                                issue ??
                                                                undefined
                                                            }
                                                        >
                                                            {d.benutzer}
                                                            {issue && ' ⚠'}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {d.inventar}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono font-semibold">
                                                            {d.pw}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono font-semibold tracking-wider text-emerald-700 dark:text-emerald-400">
                                                            {d.pin}
                                                        </td>
                                                        <td className="px-2 py-1 text-right">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() =>
                                                                    regenerateOne(
                                                                        i,
                                                                    )
                                                                }
                                                                title="Passwort und PIN neu"
                                                            >
                                                                <RefreshCw />{' '}
                                                                neu
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        onClick={() =>
                                            downloadBlob(
                                                toCsv(data),
                                                'text/csv;charset=utf-8;',
                                                'zugangsdaten.csv',
                                            )
                                        }
                                    >
                                        <FileSpreadsheet /> CSV exportieren
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            downloadBlob(
                                                slipsHtml(data),
                                                'text/html;charset=utf-8;',
                                                'zugangskaertchen.html',
                                            )
                                        }
                                    >
                                        <Download /> Zugangskärtchen als Datei
                                        herunterladen
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={regenerateAll}
                                    >
                                        <RefreshCw /> Alle neu generieren
                                    </Button>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>3 · PDF pro Schüler</CardTitle>
                            <CardDescription>
                                Erstellt ein PDF mit einer eigenen Seite pro
                                Schüler – inkl. Klasse, Lehrperson, iPad-Nummer,
                                Mail-Adresse, persönlichem Passwort und
                                Entsperrcode. Je Klasse zuerst eine Übersicht.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <label className="grid max-w-xs gap-1 text-sm">
                                Ort (für Ort/Datum im PDF)
                                <Input
                                    value={ort}
                                    onChange={(e) => setOrt(e.target.value)}
                                />
                            </label>
                            <div>
                                <Button
                                    onClick={() => void downloadPdf()}
                                    disabled={data.length === 0 || busy}
                                >
                                    <FileDown /> PDF generieren & herunterladen
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>4 · O365-Import-Skript</CardTitle>
                            <CardDescription>
                                PowerShell-Skript, das die Passwörter aus der
                                exportierten CSV über Microsoft Graph direkt im
                                Tenant setzt (der PIN wird nicht mit
                                hochgeladen). Benötigt das Modul
                                Microsoft.Graph.Users und eine Rolle mit
                                Passwort-Reset-Berechtigung.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    downloadBlob(
                                        PS_SCRIPT,
                                        'text/plain;charset=utf-8;',
                                        'Set-O365Passwords.ps1',
                                    )
                                }
                            >
                                <Download /> PowerShell-Skript herunterladen
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    Passwörter/PINs nur zur Erstverteilung – Schüler sollten sie
                    beim ersten Login ändern.
                </p>

                {planned.features.length > 0 && (
                    <Card className="border-dashed">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Geplant: Anbindung Jamf
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid list-disc gap-1 pl-5 text-sm text-muted-foreground">
                                {planned.features.map((f) => (
                                    <li key={f}>{f}</li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

DeviceHandout.layout = {
    breadcrumbs: [
        { title: 'Fachstelle ICT', href: '/geraeteausgabe' },
        { title: 'Geräteausgabe', href: '/geraeteausgabe' },
    ],
};
