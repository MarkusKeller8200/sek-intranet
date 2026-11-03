import { Head, Link, router } from '@inertiajs/react';
import { Copy, FileUp, ListTree, Plus } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import DataGrid from '@/components/data-grid';
import GlossaryPdfDialog from '@/components/glossary-pdf-dialog';
import type {
    CellChange,
    GridColumn,
    SavedView,
} from '@/components/grid/types';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { GlossaryRow } from '@/lib/glossary';
import { downloadMarkdown, printGlossary } from '@/lib/glossary';
import { postJson } from '@/lib/http';

type Props = {
    rows: GlossaryRow[];
    kategorien: Record<string, string>;
    kategorienNamen: Record<string, string>;
    herkunft: Record<string, string>;
    bereiche: Record<string, string>;
    status: Record<string, string>;
    felder: Record<string, string>;
    views: SavedView[];
    rechte: { edit: boolean; official: boolean };
    doppelte: number;
};

type CellsResponse = { rows?: GlossaryRow[]; message?: string };

async function download(ids: string[] | null, vorlage = false) {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );
    const response = await fetch('/glossar/export', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(vorlage ? { vorlage: true } : { ids }),
    });
    if (!response.ok) {
        toast.error(`Export nicht möglich (HTTP ${response.status}).`);

        return;
    }
    const url = URL.createObjectURL(await response.blob());
    const a = document.createElement('a');
    a.href = url;
    a.download = vorlage
        ? 'glossar-vorlage.xlsx'
        : `glossar-${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
}

/** Mehrfachwerte («a; b») als Tags. */
function Tags({ value }: { value: string }) {
    const items = value
        .split(';')
        .map((s) => s.trim())
        .filter(Boolean);

    return (
        <span className="flex min-w-0 gap-1 overflow-hidden" translate="no">
            {items.map((t) => (
                <Badge key={t} variant="secondary" className="shrink-0">
                    {t}
                </Badge>
            ))}
        </span>
    );
}

/** Glossar als bearbeitbare Tabelle (GUI-Baustein «Tabellen»), ganze Breite. */
export default function GlossaryIndex({
    rows,
    kategorienNamen,
    status,
    felder,
    views,
    rechte,
    doppelte,
}: Props) {
    const datei = useRef<HTMLInputElement>(null);
    const [pdfIds, setPdfIds] = useState<string[] | null>(null);
    const byId = useMemo(() => new Map(rows.map((r) => [r.id, r])), [rows]);

    const columns = useMemo<GridColumn<GlossaryRow>[]>(() => {
        const text = (
            id: keyof GlossaryRow,
            opts: Partial<GridColumn<GlossaryRow>> = {},
        ): GridColumn<GlossaryRow> => ({
            id,
            title: felder[id] ?? id,
            type: 'text',
            editable: rechte.edit,
            ...opts,
        });

        return [
            text('anzeige', {
                title: 'Abkürzung',
                editable: false,
                render: (r) => (
                    <Link
                        href={`/glossar/${r.id}`}
                        className="inline-flex items-center gap-1 font-semibold text-primary underline-offset-2 hover:underline"
                    >
                        <span translate="no">
                            {r.anzeige || '(neu)'}
                            {r.mehrdeutig && <sup>{r.bedeutungen}</sup>}
                        </span>
                        {r.mehrdeutig && (
                            <Badge
                                variant="outline"
                                title="Diese Abkürzung hat mehrere verschiedene Bedeutungen"
                            >
                                mehrdeutig
                            </Badge>
                        )}
                    </Link>
                ),
            }),
            text('kategorien', {
                separator: ';',
                render: (r) => <Tags value={r.kategorien} />,
            }),
            text('abk_offiziell', { editable: rechte.official, hidden: true }),
            text('aliase_offiziell', {
                editable: rechte.official,
                hidden: true,
            }),
            text('abk_eigen', { hidden: true }),
            text('alias_eigen', { hidden: true }),
            text('bez_offiziell', { editable: rechte.official }),
            text('bez_eigen', { hidden: true }),
            text('kurzbeschreibung'),
            text('beschreibung', { hidden: true }),
            text('url', { hidden: true }),
            text('notiz', { hidden: true }),
            text('herkunft', {
                separator: ';',
                render: (r) => <Tags value={r.herkunft} />,
            }),
            text('genutzt_in', {
                separator: ';',
                hidden: true,
                render: (r) => <Tags value={r.genutzt_in} />,
            }),
            {
                id: 'status',
                title: felder.status ?? 'Status',
                type: 'select',
                options: status,
                editable: rechte.edit,
                required: true,
                hidden: true,
            },
            text('suchbegriffe', { hidden: true }),
            text('gueltig_ab', { hidden: true }),
            text('gueltig_bis', { hidden: true }),
        ];
    }, [felder, rechte, status]);

    const saveCells = async (changes: CellChange<GlossaryRow>[]) => {
        const res = await postJson<CellsResponse>('/glossar/zellen', {
            changes: changes.map((c) => ({
                id: c.id,
                field: c.field,
                value: c.value,
            })),
        });
        if (!res.rows) {
            throw new Error(res.message ?? 'Speichern fehlgeschlagen.');
        }

        return res.rows;
    };

    const neueZeile = async () => {
        const res = await postJson<{ id?: string; message?: string }>(
            '/glossar/zeile',
        );
        if (!res.id) {
            toast.error(res.message ?? 'Neue Zeile nicht möglich.');

            return null;
        }
        const id = res.id;
        await new Promise<void>((resolve) =>
            router.reload({
                only: ['rows', 'doppelte'],
                onFinish: () => resolve(),
            }),
        );

        return id;
    };

    const ausgewaehlt = (ids: string[]) =>
        ids
            .map((id) => byId.get(id))
            .filter((r): r is GlossaryRow => r !== undefined);

    return (
        <>
            <Head title="Glossar" />
            <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <PageHeader
                    title="Glossar"
                    description="Abkürzungen und Begriffe – offiziell und wie wir sie verwenden. Bearbeiten direkt in der Tabelle oder als Einzeldatensatz."
                    actions={
                        rechte.edit && (
                            <>
                                <Button asChild variant="outline">
                                    <Link href="/glossar/doppelte">
                                        <Copy /> Doppelte Abkürzungen oder
                                        Begriffe
                                        {doppelte > 0 && (
                                            <Badge variant="secondary">
                                                {doppelte}
                                            </Badge>
                                        )}
                                    </Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href="/glossar/listen">
                                        <ListTree /> Kategorien & Herkunft
                                    </Link>
                                </Button>
                                <Button asChild>
                                    <Link href="/glossar/neu">
                                        <Plus /> Neuer Eintrag
                                    </Link>
                                </Button>
                            </>
                        )
                    }
                />

                <DataGrid
                    data={rows}
                    columns={columns}
                    storageKey={`intranet.grid.glossary.v2.${rechte.edit ? 'edit' : 'view'}`}
                    gridId="glossary"
                    views={views}
                    canShareViews={rechte.edit}
                    canEdit={rechte.edit}
                    onSaveCells={rechte.edit ? saveCells : undefined}
                    onAddRow={rechte.edit ? neueZeile : undefined}
                    onExport={(_format, ids) => void download(ids)}
                    extraExports={[
                        {
                            label: 'PDF …',
                            // erst nach dem Schliessen des Menüs öffnen (Fokus)
                            run: (ids) => window.setTimeout(() => setPdfIds(ids), 0),
                        },
                        {
                            label: 'Markdown',
                            run: (ids) =>
                                downloadMarkdown(
                                    ausgewaehlt(ids),
                                    kategorienNamen,
                                ),
                        },
                    ]}
                    selectable={false}
                    searchPlaceholder="Abkürzung, Bezeichnung, Suchbegriff …"
                    emptyText="Keine Einträge gefunden."
                    rowClassName={(r) =>
                        r.status === 'historisch' ? 'opacity-60' : ''
                    }
                    toolbar={
                        rechte.edit && (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => datei.current?.click()}
                                >
                                    <FileUp /> Excel-Import
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => void download(null, true)}
                                >
                                    Vorlage
                                </Button>
                                <input
                                    ref={datei}
                                    type="file"
                                    accept=".xlsx,.csv"
                                    className="hidden"
                                    aria-label="Excel-Datei für den Import wählen"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (file) {
                                            router.post(
                                                '/glossar/import',
                                                { datei: file },
                                                { forceFormData: true },
                                            );
                                        }
                                        e.target.value = '';
                                    }}
                                />
                            </>
                        )
                    }
                />
                <GlossaryPdfDialog
                    count={pdfIds?.length ?? 0}
                    open={pdfIds !== null}
                    onOpenChange={(open) => !open && setPdfIds(null)}
                    onCreate={(options) =>
                        printGlossary(
                            ausgewaehlt(pdfIds ?? []),
                            options,
                            status,
                            kategorienNamen,
                        )
                    }
                />
            </div>
        </>
    );
}

GlossaryIndex.layout = {
    breadcrumbs: [{ title: 'Glossar', href: '/glossar' }],
};
