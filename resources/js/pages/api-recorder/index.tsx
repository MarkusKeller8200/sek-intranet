import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    Download,
    FileJson,
    Plus,
    Puzzle,
    Radio,
    Trash2,
} from 'lucide-react';
import { toast } from 'sonner';
import EmptyState from '@/components/empty-state';
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
import { buildOpenApi } from '@/lib/api-recorder/openapi';
import type { ApiDoc } from '@/lib/api-recorder/types';
import { formatDateTime } from '@/lib/format';
import { t } from '@/lib/i18n';

type ListedDoc = ApiDoc & {
    id: string;
    plugin_name: string | null;
    missing: number;
};

function hostOf(url: string | undefined): string {
    try {
        return url ? new URL(url).host : '';
    } catch {
        return url ?? '';
    }
}

/** Prototyp → API-Recorder: alle aufgezeichneten APIs. */
export default function ApiRecorderIndex({ docs }: { docs: ListedDoc[] }) {
    const remove = async (doc: ListedDoc) => {
        if (!window.confirm(t('Diese API und ihre Dokumentation löschen?'))) {
            return;
        }
        const token = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.slice(11) ?? '',
        );
        await fetch(`/api-recorder/${doc.id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'X-XSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });
        toast.success(t('API gelöscht.'));
        router.reload({ only: ['docs'] });
    };

    const downloadSpec = (doc: ListedDoc) => {
        const blob = new Blob([JSON.stringify(buildOpenApi(doc), null, 2)], {
            type: 'application/json',
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${doc.title.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'api'}-openapi.json`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return (
        <>
            <Head title="API-Recorder" />
            <div className="flex max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="API-Recorder"
                    description="Schnittstellen von Websites aufzeichnen, dokumentieren und als OpenAPI-Datei (Swagger) speichern. Jede API lässt sich wieder öffnen und weiter aufzeichnen, wenn Befehle fehlen."
                    actions={
                        <Button asChild>
                            <Link href="/api-recorder/neu">
                                <Plus /> Neue API aufzeichnen
                            </Link>
                        </Button>
                    }
                />

                {docs.length === 0 ? (
                    <EmptyState
                        icon={Radio}
                        title="Noch keine API aufgezeichnet"
                    >
                        Mit «Neue API aufzeichnen» eine Website öffnen und ihre
                        Anfragen mitschneiden.
                    </EmptyState>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Aufgezeichnete APIs</CardTitle>
                            <CardDescription>
                                Öffnen lädt die gespeicherten Endpunkte. Weitere
                                Aufzeichnungen ergänzen sie.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table
                                className="w-full text-sm"
                                data-testid="api-list"
                            >
                                <thead className="text-left text-xs text-muted-foreground">
                                    <tr className="border-b">
                                        <th className="py-2 pr-3 font-medium">
                                            Name
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            URL
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Endpunkte
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Plugin
                                        </th>
                                        <th className="py-2 pr-3 font-medium">
                                            Zuletzt aufgezeichnet
                                        </th>
                                        <th className="py-2" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {docs.map((doc) => (
                                        <tr
                                            key={doc.id}
                                            className="border-b align-middle last:border-0"
                                        >
                                            <td className="py-2 pr-3">
                                                <Link
                                                    href={`/api-recorder/${doc.id}`}
                                                    className="font-medium text-primary hover:underline"
                                                    translate="no"
                                                >
                                                    {doc.title}
                                                </Link>
                                                {doc.description && (
                                                    <div
                                                        className="max-w-xs truncate text-xs text-muted-foreground"
                                                        translate="no"
                                                    >
                                                        {doc.description}
                                                    </div>
                                                )}
                                            </td>
                                            <td
                                                className="py-2 pr-3 font-mono text-xs"
                                                translate="no"
                                            >
                                                {hostOf(
                                                    doc.start_url ||
                                                        doc.base_url,
                                                ) || '–'}
                                            </td>
                                            <td className="py-2 pr-3">
                                                {doc.operations.length}
                                            </td>
                                            <td className="py-2 pr-3">
                                                {doc.plugin_name ? (
                                                    <span className="flex flex-wrap items-center gap-1.5">
                                                        <Puzzle className="size-4 text-muted-foreground" />
                                                        <Link
                                                            href={`/plugins/${doc.plugin_id}`}
                                                            className="hover:underline"
                                                        >
                                                            {doc.plugin_name}
                                                        </Link>
                                                        {doc.missing > 0 ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-amber-300 text-amber-800 dark:text-amber-300"
                                                            >
                                                                {`${doc.missing} fehlen`}
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline">
                                                                vollständig
                                                            </Badge>
                                                        )}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        –
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2 pr-3 text-xs whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    doc.recorded_at ??
                                                        doc.updated_at,
                                                )}
                                            </td>
                                            <td className="py-2 text-right whitespace-nowrap">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/api-recorder/${doc.id}`}
                                                    >
                                                        <Radio /> Öffnen &
                                                        weiter aufzeichnen
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    title="Ansicht (Scalar)"
                                                    aria-label="Ansicht (Scalar)"
                                                    disabled={
                                                        doc.operations
                                                            .length === 0
                                                    }
                                                    asChild={
                                                        doc.operations.length >
                                                        0
                                                    }
                                                >
                                                    {doc.operations.length >
                                                    0 ? (
                                                        <Link
                                                            href={`/api-recorder/${doc.id}/ansicht`}
                                                        >
                                                            <BookOpen />
                                                        </Link>
                                                    ) : (
                                                        <BookOpen />
                                                    )}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    title="OpenAPI-Datei (JSON)"
                                                    aria-label="OpenAPI-Datei (JSON)"
                                                    disabled={
                                                        doc.operations
                                                            .length === 0
                                                    }
                                                    onClick={() =>
                                                        downloadSpec(doc)
                                                    }
                                                >
                                                    <Download />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    title="Plugin-Entwurf (fehlende Endpunkte)"
                                                    aria-label="Plugin-Entwurf (fehlende Endpunkte)"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/api-recorder/${doc.id}/plugin-entwurf`}
                                                    >
                                                        <FileJson />
                                                    </a>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    title="Löschen"
                                                    aria-label="Löschen"
                                                    onClick={() =>
                                                        void remove(doc)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                )}

                <p className="text-xs text-muted-foreground">
                    Wie aus einer aufgezeichneten API ein Plugin wird: Konzept
                    «OpenAPI zu Plugin» (docs/KONZEPT-API-ZU-PLUGIN.md).
                </p>
            </div>
        </>
    );
}

ApiRecorderIndex.layout = {
    breadcrumbs: [{ title: 'API-Recorder', href: '/api-recorder' }],
};
