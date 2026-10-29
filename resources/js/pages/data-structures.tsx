import { Head } from '@inertiajs/react';
import {
    AlertTriangle,
    Database,
    FileJson,
    KeyRound,
    Link2,
} from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import type { SchemaCollection } from '@/types';

export default function DataStructures({
    store,
    collections,
}: {
    store: string;
    collections: SchemaCollection[];
}) {
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

                {collections.map((collection) => {
                    const unknown = Object.entries(collection.unknown_fields);

                    return (
                        <Card key={collection.name}>
                            <CardHeader>
                                <CardTitle className="flex flex-wrap items-center gap-2">
                                    {collection.label}
                                    <Badge
                                        variant="secondary"
                                        className="font-mono"
                                    >
                                        {collection.name}
                                    </Badge>
                                    <Badge variant="outline">
                                        {collection.count} Datensätze
                                    </Badge>
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
                            <CardContent className="grid gap-4">
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
                                            – im Schema ergänzen oder aus den
                                            Daten entfernen.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Feld</TableHead>
                                            <TableHead>Bezeichnung</TableHead>
                                            <TableHead>Typ</TableHead>
                                            <TableHead>
                                                PostgreSQL (Vorschlag)
                                            </TableHead>
                                            <TableHead>Pflicht</TableHead>
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
                        </Card>
                    );
                })}
            </div>
        </>
    );
}

DataStructures.layout = {
    breadcrumbs: [{ title: 'Datenstrukturen', href: '/datenstrukturen' }],
};
