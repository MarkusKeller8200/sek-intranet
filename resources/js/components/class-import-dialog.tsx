import { FileUp, ShieldCheck, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { ChangeEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    guessMapping,
    IMPORT_FIELDS,
    readTable,
    usernameFor,
    yearOf,
} from '@/lib/access-codes/class-import';
import type { Mapping, Table } from '@/lib/access-codes/class-import';

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-2 text-sm';

export type ImportedRow = {
    nachname: string;
    vorname: string;
    benutzer: string;
    inventar: string;
    lehrperson: string;
    klasse: string;
};

const ALL = '*';

const cell = (row: string[], index: number) =>
    index >= 0 ? (row[index] ?? '').trim() : '';

/**
 * Schüler:innen einer Klasse aus einer Klassenliste (Excel/CSV) übernehmen.
 * Standard: Jahrgang 1 (Geräteausgabe an die neuen 1. Sek-Klassen).
 */
export default function ClassImportDialog({
    open,
    onOpenChange,
    mailDomain,
    onImport,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mailDomain: string;
    onImport: (rows: ImportedRow[], replace: boolean) => void;
}) {
    const [fileName, setFileName] = useState<string | null>(null);
    const [table, setTable] = useState<Table | null>(null);
    const [mapping, setMapping] = useState<Mapping | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [year, setYear] = useState('1');
    const [klasse, setKlasse] = useState(ALL);
    const [teacher, setTeacher] = useState('');
    const [buildNames, setBuildNames] = useState(true);
    const [replace, setReplace] = useState(true);

    const load = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) {
            return;
        }
        setError(null);
        try {
            const result = await readTable(file);
            if (result.rows.length === 0) {
                throw new Error('Die Datei enthält keine Datenzeilen.');
            }
            setTable(result);
            setMapping(guessMapping(result.headers));
            setFileName(file.name);
            setKlasse(ALL);
        } catch (e) {
            setTable(null);
            setMapping(null);
            setError(
                e instanceof Error
                    ? e.message
                    : 'Datei konnte nicht gelesen werden.',
            );
        }
    };

    const classes = useMemo(() => {
        if (!table || !mapping || mapping.klasse < 0) {
            return [] as string[];
        }

        return Array.from(
            new Set(
                table.rows.map((r) => cell(r, mapping.klasse)).filter(Boolean),
            ),
        ).sort((a, b) => a.localeCompare(b, 'de-CH', { numeric: true }));
    }, [table, mapping]);

    const yearClasses = classes.filter(
        (c) => year === ALL || String(yearOf(c)) === year,
    );

    const selected = useMemo((): ImportedRow[] => {
        if (!table || !mapping) {
            return [];
        }

        return table.rows
            .filter((r) => {
                if (mapping.klasse < 0) {
                    return true;
                }
                const k = cell(r, mapping.klasse);
                if (klasse !== ALL) {
                    return k === klasse;
                }

                return year === ALL || String(yearOf(k)) === year;
            })
            .map((r) => {
                const vorname = cell(r, mapping.vorname);
                const nachname = cell(r, mapping.nachname);
                const benutzer =
                    cell(r, mapping.benutzer).toLowerCase() ||
                    (buildNames
                        ? usernameFor(vorname, nachname, mailDomain)
                        : '');

                return {
                    nachname,
                    vorname,
                    benutzer,
                    inventar: cell(r, mapping.inventar),
                    lehrperson: cell(r, mapping.lehrperson) || teacher.trim(),
                    klasse: cell(r, mapping.klasse),
                };
            })
            .filter((r) => r.nachname || r.vorname)
            .sort(
                (a, b) =>
                    a.klasse.localeCompare(b.klasse, 'de-CH', {
                        numeric: true,
                    }) ||
                    a.nachname.localeCompare(b.nachname, 'de-CH') ||
                    a.vorname.localeCompare(b.vorname, 'de-CH'),
            );
    }, [table, mapping, klasse, year, teacher, buildNames, mailDomain]);

    const ready =
        mapping !== null && (mapping.nachname >= 0 || mapping.vorname >= 0);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Users className="size-5 text-brand" />
                        Schüler:innen importieren
                    </DialogTitle>
                    <DialogDescription>
                        Klassenliste aus Escola oder Excel (.xlsx oder CSV)
                        wählen, dann Jahrgang und Klasse auswählen.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-start gap-2 rounded-md border border-emerald-300 bg-emerald-50 p-2.5 text-xs text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                    <ShieldCheck className="mt-0.5 size-4 shrink-0" />
                    Die Datei wird nur in diesem Browser gelesen und nicht
                    hochgeladen.
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <Button variant="outline" asChild>
                        <label className="cursor-pointer">
                            <FileUp /> Klassenliste wählen
                            <input
                                type="file"
                                accept=".xlsx,.csv,.txt"
                                className="hidden"
                                onChange={(e) => void load(e)}
                                data-testid="class-file"
                            />
                        </label>
                    </Button>
                    {fileName && table && (
                        <span className="text-sm text-muted-foreground">
                            <span translate="no">{fileName}</span>
                            {' · '}
                            {`${table.rows.length} Zeilen`}
                        </span>
                    )}
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}

                {table && mapping && (
                    <div className="grid gap-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <label className="grid gap-1 text-sm">
                                Jahrgang
                                <select
                                    className={selectClass}
                                    value={year}
                                    onChange={(e) => {
                                        setYear(e.target.value);
                                        setKlasse(ALL);
                                    }}
                                    disabled={mapping.klasse < 0}
                                >
                                    <option value="1">1. Sek</option>
                                    <option value="2">2. Sek</option>
                                    <option value="3">3. Sek</option>
                                    <option value={ALL}>Alle Jahrgänge</option>
                                </select>
                            </label>
                            <label className="grid gap-1 text-sm">
                                Klasse
                                <select
                                    className={selectClass}
                                    value={klasse}
                                    onChange={(e) => setKlasse(e.target.value)}
                                    disabled={mapping.klasse < 0}
                                    data-testid="class-select"
                                >
                                    <option value={ALL}>
                                        {`Alle Klassen (${yearClasses.length})`}
                                    </option>
                                    {yearClasses.map((c) => (
                                        <option
                                            key={c}
                                            value={c}
                                            translate="no"
                                        >
                                            {c}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>
                        {mapping.klasse < 0 && (
                            <p className="text-xs text-muted-foreground">
                                Keine Spalte «Klasse» erkannt – es werden alle
                                Zeilen übernommen. Spalte unten zuordnen, um
                                nach Klasse zu wählen.
                            </p>
                        )}

                        <details className="rounded-md border p-3 text-sm">
                            <summary className="cursor-pointer font-medium">
                                Spalten zuordnen
                            </summary>
                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                {IMPORT_FIELDS.map((field) => (
                                    <label
                                        key={field.key}
                                        className="grid gap-1 text-xs"
                                    >
                                        {field.label}
                                        <select
                                            className={selectClass}
                                            value={mapping[field.key]}
                                            onChange={(e) =>
                                                setMapping({
                                                    ...mapping,
                                                    [field.key]: Number(
                                                        e.target.value,
                                                    ),
                                                })
                                            }
                                        >
                                            <option value={-1}>
                                                – keine –
                                            </option>
                                            {table.headers.map((h, i) => (
                                                <option
                                                    key={i}
                                                    value={i}
                                                    translate="no"
                                                >
                                                    {h || `Spalte ${i + 1}`}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                ))}
                            </div>
                        </details>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {mapping.lehrperson < 0 && (
                                <label className="grid gap-1 text-sm">
                                    Lehrperson (für alle übernommenen)
                                    <Input
                                        value={teacher}
                                        onChange={(e) =>
                                            setTeacher(e.target.value)
                                        }
                                        placeholder="z. B. Klassenlehrperson"
                                    />
                                </label>
                            )}
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox
                                    checked={buildNames}
                                    onCheckedChange={(
                                        v: boolean | 'indeterminate',
                                    ) => setBuildNames(v === true)}
                                    className="mt-0.5"
                                />
                                <span>
                                    Fehlenden Benutzernamen bilden:{' '}
                                    <code translate="no">{`vorname.nachname@${mailDomain}`}</code>{' '}
                                    <span className="text-muted-foreground">
                                        (Umlaute ersetzt – bitte prüfen)
                                    </span>
                                </span>
                            </label>
                        </div>

                        <div className="rounded-md border">
                            <div className="border-b bg-muted/50 px-3 py-2 text-xs font-medium">
                                {`Vorschau: ${selected.length} Schüler:innen`}
                            </div>
                            <div className="max-h-48 overflow-auto">
                                <table
                                    className="w-full text-xs"
                                    translate="no"
                                >
                                    <tbody>
                                        {selected.slice(0, 50).map((r, i) => (
                                            <tr
                                                key={i}
                                                className="border-b last:border-0"
                                            >
                                                <td className="px-3 py-1">
                                                    {r.klasse}
                                                </td>
                                                <td className="px-3 py-1">{`${r.nachname} ${r.vorname}`}</td>
                                                <td className="px-3 py-1 font-mono">
                                                    {r.benutzer}
                                                </td>
                                                <td className="px-3 py-1 font-mono">
                                                    {r.inventar}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={replace}
                                onCheckedChange={(
                                    v: boolean | 'indeterminate',
                                ) => setReplace(v === true)}
                            />
                            Bisherige Liste ersetzen (sonst anhängen)
                        </label>
                    </div>
                )}

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Abbrechen
                    </Button>
                    <Button
                        disabled={!ready || selected.length === 0}
                        onClick={() => {
                            onImport(selected, replace);
                            onOpenChange(false);
                        }}
                    >
                        <Users />
                        {`${selected.length} übernehmen`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
