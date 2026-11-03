import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronRight,
    ChevronsDownUp,
    ChevronsUpDown,
    Database,
    FileJson,
    KeyRound,
    Link2,
    Lock,
} from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { cn } from '@/lib/utils';
import type { SchemaCollection } from '@/types';

const OPEN_KEY = 'intranet.datastructures.open';

function readOpen(): string[] {
    try {
        const parsed: unknown = JSON.parse(
            window.localStorage.getItem(OPEN_KEY) ?? '[]',
        );

        return Array.isArray(parsed)
            ? parsed.filter((v): v is string => typeof v === 'string')
            : [];
    } catch {
        return [];
    }
}

export default function DataStructures({
    store,
    collections,
    unknownCollections,
    catalogs = [],
}: {
    store: string;
    collections: SchemaCollection[];
    unknownCollections: string[];
    catalogs?: CodeCatalog[];
}) {
    // Aufgeklappte Sammlungen (pro Browser gemerkt); Sammlungen mit Problemen immer offen
    const [open, setOpenState] = useState<string[]>(readOpen);
    const setOpen = (next: string[]) => {
        setOpenState(next);
        try {
            window.localStorage.setItem(OPEN_KEY, JSON.stringify(next));
        } catch {
            // ohne Speicher nur bis zum Neuladen
        }
    };
    const toggle = (name: string) =>
        setOpen(
            open.includes(name)
                ? open.filter((n) => n !== name)
                : [...open, name],
        );
    const fieldCount = collections.reduce((n, c) => n + c.fields.length, 0);
    const recordCount = collections.reduce((n, c) => n + c.count, 0);

    return (
        <>
            <Head title="Datenstrukturen" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Datenstrukturen"
                    description="Was der Prototyp speichert – Grundlage für das spätere PostgreSQL-Datenmodell"
                />

                <Alert>
                    <Database />
                    <AlertTitle>
                        Speicher: {store === 'json' ? 'JSON-Dateien' : store}
                    </AlertTitle>
                    <AlertDescription>
                        Das Schema steht in config/intranet.php (Abschnitt
                        «schema»). Jede Sammlung erhält zusätzlich id (UUID v7),
                        created_at und updated_at. Felder, die in den Daten
                        vorkommen, aber nicht im Schema stehen, werden unten
                        markiert.
                    </AlertDescription>
                </Alert>

                {unknownCollections.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Sammlungen ohne Schema</AlertTitle>
                        <AlertDescription>
                            {unknownCollections.join(', ')} – gespeichert, aber
                            nicht in config/intranet.php → schema beschrieben.
                            Bitte nachführen.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        {collections.length} Sammlungen · {fieldCount} Felder ·{' '}
                        {recordCount} Datensätze
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        className="ml-auto"
                        onClick={() => setOpen(collections.map((c) => c.name))}
                    >
                        <ChevronsUpDown /> Alle aufklappen
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setOpen([])}
                    >
                        <ChevronsDownUp /> Alle zuklappen
                    </Button>
                </div>

                {collections.map((collection) => {
                    const unknown = Object.entries(collection.unknown_fields);
                    const isOpen =
                        open.includes(collection.name) || unknown.length > 0;

                    return (
                        <Card key={collection.name} className="gap-0 py-0">
                            <CardHeader
                                className="cursor-pointer py-4 select-none hover:bg-accent/50"
                                onClick={() => toggle(collection.name)}
                                role="button"
                                tabIndex={0}
                                aria-expanded={isOpen}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter' || e.key === ' ') {
                                        e.preventDefault();
                                        toggle(collection.name);
                                    }
                                }}
                            >
                                <CardTitle className="flex flex-wrap items-center gap-2">
                                    <ChevronRight
                                        className={cn(
                                            'size-4 shrink-0 transition-transform',
                                            isOpen && 'rotate-90',
                                        )}
                                    />
                                    {collection.label}
                                    <Badge
                                        variant="secondary"
                                        className="font-mono"
                                    >
                                        {collection.name}
                                    </Badge>
                                    {collection.module && (
                                        <Badge variant="outline">
                                            {`Modul ${collection.module}`}
                                        </Badge>
                                    )}
                                    <Badge variant="outline">
                                        {collection.count} Datensätze
                                    </Badge>
                                    <Badge variant="outline">
                                        {collection.fields.length} Felder
                                    </Badge>
                                    {unknown.length > 0 && (
                                        <Badge variant="destructive">
                                            {unknown.length} unbekannt
                                        </Badge>
                                    )}
                                </CardTitle>
                                <CardDescription className="flex flex-wrap items-center gap-x-4 gap-y-1">
                                    {collection.description && (
                                        <span>{collection.description}</span>
                                    )}
                                    <span className="flex items-center gap-1 font-mono text-xs">
                                        <FileJson className="size-3.5" />
                                        {collection.file}
                                        {!collection.exists &&
                                            ' (noch nicht angelegt)'}
                                    </span>
                                </CardDescription>
                            </CardHeader>
                            {isOpen && (
                                <CardContent className="grid gap-4 pb-6">
                                    {unknown.length > 0 && (
                                        <Alert variant="destructive">
                                            <AlertTriangle />
                                            <AlertTitle>
                                                Felder ausserhalb des Schemas
                                            </AlertTitle>
                                            <AlertDescription>
                                                {unknown
                                                    .map(
                                                        ([field, count]) =>
                                                            `${field} (${count}×)`,
                                                    )
                                                    .join(', ')}{' '}
                                                – im Schema ergänzen oder aus
                                                den Daten entfernen.
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Feld</TableHead>
                                                <TableHead>
                                                    Bezeichnung
                                                </TableHead>
                                                <TableHead>Typ</TableHead>
                                                <TableHead>
                                                    PostgreSQL (Vorschlag)
                                                </TableHead>
                                                <TableHead>Pflicht</TableHead>
                                                {collection.fields.some(
                                                    (f) => f.group,
                                                ) && (
                                                    <TableHead>
                                                        Gruppe
                                                    </TableHead>
                                                )}
                                                <TableHead className="text-right">
                                                    Gefüllt
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {collection.fields.map((field) => (
                                                <TableRow key={field.name}>
                                                    <TableCell className="font-mono text-xs">
                                                        <span className="flex items-center gap-1">
                                                            {field.name}
                                                            {field.unique && (
                                                                <KeyRound
                                                                    className="size-3.5 text-muted-foreground"
                                                                    aria-label="eindeutig"
                                                                />
                                                            )}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        {field.label}
                                                    </TableCell>
                                                    <TableCell>
                                                        <span className="flex flex-wrap items-center gap-1">
                                                            <Badge variant="outline">
                                                                {field.type}
                                                            </Badge>
                                                            {field.references && (
                                                                <span className="flex items-center gap-0.5 text-xs text-muted-foreground">
                                                                    <Link2 className="size-3" />{' '}
                                                                    {
                                                                        field.references
                                                                    }
                                                                </span>
                                                            )}
                                                        </span>
                                                        {field.values.length >
                                                            0 && (
                                                            <span
                                                                className="block max-w-64 truncate text-xs text-muted-foreground"
                                                                title={field.values.join(
                                                                    ', ',
                                                                )}
                                                            >
                                                                {field.values.join(
                                                                    ', ',
                                                                )}
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-mono text-xs">
                                                        {field.postgres}
                                                    </TableCell>
                                                    <TableCell>
                                                        {field.required
                                                            ? 'ja'
                                                            : 'nein'}
                                                    </TableCell>
                                                    {collection.fields.some(
                                                        (f) => f.group,
                                                    ) && (
                                                        <TableCell className="text-xs">
                                                            {field.group}
                                                            {field.admin_only && (
                                                                <Lock
                                                                    className="ml-1 inline size-3 text-muted-foreground"
                                                                    aria-label="nur Administration"
                                                                />
                                                            )}
                                                            {field.computed &&
                                                                ' · berechnet'}
                                                        </TableCell>
                                                    )}
                                                    <TableCell className="text-right tabular-nums">
                                                        {collection.count === 0
                                                            ? '–'
                                                            : `${Math.round((field.filled / collection.count) * 100)} %`}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            )}
                        </Card>
                    );
                })}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center gap-2">
                            Kataloge im Code: Sprachkonzept
                            <Badge variant="outline">
                                Dateien, nicht in der Datenablage
                            </Badge>
                        </CardTitle>
                        <CardDescription>
                            Die Übersetzungen liegen als JSON-Dateien im Code
                            und werden mit jedem Release ausgeliefert. Aufbau je
                            Eintrag: Deutsch (Text, Schlüssel) → English (Text),
                            Züritüütsch (Text). Später in PostgreSQL als eigene
                            Tabelle.
                        </CardDescription>
                        <Link
                            href="/sprachkonzept"
                            className="text-sm text-primary underline-offset-2 hover:underline"
                        >
                            Zum Sprachkonzept
                        </Link>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Katalog</TableHead>
                                    <TableHead>Datei</TableHead>
                                    <TableHead className="text-right">
                                        Einträge
                                    </TableHead>
                                    <TableHead className="text-right">
                                        English
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Züritüütsch
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {catalogs.map((c) => (
                                    <TableRow key={c.file}>
                                        <TableCell className="font-medium">
                                            {c.label}
                                        </TableCell>
                                        <TableCell
                                            className="font-mono text-xs"
                                            translate="no"
                                        >
                                            {c.file}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {c.count}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {c.kind === 'ignore'
                                                ? '–'
                                                : `${Math.round((c.translated.en / Math.max(1, c.count)) * 100)} %`}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {c.kind === 'ignore'
                                                ? '–'
                                                : `${Math.round((c.translated.gsw / Math.max(1, c.count)) * 100)} %`}
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

/** Tabelle im Code statt in der Datenablage (#79). */
type CodeCatalog = {
    name: string;
    label: string;
    file: string;
    count: number;
    translated: { en: number; gsw: number };
    kind: 'catalog' | 'ignore';
};

DataStructures.layout = {
    breadcrumbs: [{ title: 'Datenstrukturen', href: '/datenstrukturen' }],
};
