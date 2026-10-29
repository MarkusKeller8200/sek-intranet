import { Head, Link, usePage } from '@inertiajs/react';
import {
    Download,
    ExternalLink,
    FileText,
    Pencil,
    Plus,
    Search,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DeleteButton from '@/components/delete-button';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useOptions } from '@/hooks/use-options';
import { formatFileSize } from '@/lib/format';
import type { DocumentItem } from '@/types';

export default function DocumentsIndex({
    documents,
}: {
    documents: DocumentItem[];
}) {
    const { auth } = usePage().props;
    const options = useOptions();
    const [query, setQuery] = useState('');

    const groups = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const filtered = documents.filter(
            (doc) =>
                needle === '' ||
                doc.title.toLowerCase().includes(needle) ||
                (doc.description ?? '').toLowerCase().includes(needle),
        );

        // Reihenfolge der Kategorien wie in config/intranet.php
        return Object.entries(options.documentCategories)
            .map(([key, text]) => ({
                key,
                text,
                items: filtered.filter((doc) => doc.category === key),
            }))
            .filter((group) => group.items.length > 0);
    }, [documents, options.documentCategories, query]);

    return (
        <>
            <Head title="Dokumente & Links" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dokumente & Links"
                    description="Reglemente, Formulare, Anleitungen und wichtige Webseiten"
                    actions={
                        auth.can.manageContent && (
                            <Button asChild>
                                <Link href="/dokumente/neu">
                                    <Plus /> Neuer Eintrag
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="relative md:w-72">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Suchen …"
                        className="pl-8"
                        aria-label="Dokumente durchsuchen"
                    />
                </div>

                {groups.length === 0 && (
                    <EmptyState
                        icon={FileText}
                        title="Keine Einträge gefunden"
                    />
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    {groups.map((group) => (
                        <Card key={group.key}>
                            <CardHeader>
                                <CardTitle>{group.text}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-1">
                                {group.items.map((doc) => (
                                    <div
                                        key={doc.id}
                                        className="-mx-2 flex items-start gap-3 rounded-lg p-2 hover:bg-accent"
                                    >
                                        <a
                                            href={
                                                doc.kind === 'link'
                                                    ? (doc.url ?? '#')
                                                    : `/dokumente/${doc.id}/download`
                                            }
                                            target={
                                                doc.kind === 'link'
                                                    ? '_blank'
                                                    : undefined
                                            }
                                            rel="noreferrer"
                                            className="flex flex-1 items-start gap-3"
                                        >
                                            {doc.kind === 'link' ? (
                                                <ExternalLink className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            ) : (
                                                <Download className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                            )}
                                            <span className="grid gap-0.5">
                                                <span className="text-sm font-medium">
                                                    {doc.title}
                                                </span>
                                                {doc.description && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {doc.description}
                                                    </span>
                                                )}
                                                {doc.kind === 'file' &&
                                                    doc.file_name && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {doc.file_name} ·{' '}
                                                            {formatFileSize(
                                                                doc.file_size,
                                                            )}
                                                        </span>
                                                    )}
                                            </span>
                                        </a>
                                        {auth.can.manageContent && (
                                            <div className="flex shrink-0 gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/dokumente/${doc.id}/bearbeiten`}
                                                        aria-label="Bearbeiten"
                                                    >
                                                        <Pencil />
                                                    </Link>
                                                </Button>
                                                <DeleteButton
                                                    url={`/dokumente/${doc.id}`}
                                                    title="Eintrag löschen?"
                                                    description={
                                                        doc.kind === 'file'
                                                            ? 'Der Eintrag und die hochgeladene Datei werden endgültig gelöscht.'
                                                            : undefined
                                                    }
                                                    size="icon"
                                                />
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

DocumentsIndex.layout = {
    breadcrumbs: [{ title: 'Dokumente & Links', href: '/dokumente' }],
};
