import {
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type {
    Column,
    ColumnDef,
    ColumnFiltersState,
    ColumnOrderState,
    ColumnSizingState,
    FilterFn,
    Row,
    RowSelectionState,
    SortingFn,
    SortingState,
    VisibilityState,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronDown,
    ChevronUp,
    Columns3,
    GripVertical,
    ListChecks,
    Loader2,
    MoveHorizontal,
    Pencil,
    RotateCcw,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Bearbeitbare Tabelle: Filter je Spalte, Sortierung, Spalten verschieben (ziehen),
 * ein-/ausblenden, Auswahl (alle / nur sichtbare) und direktes Bearbeiten in der Zelle.
 * Ansicht (Reihenfolge, Sichtbarkeit, Sortierung) wird pro Browser gemerkt.
 */

export type GridColumn<T> = {
    id: keyof T & string;
    title: string;
    type?: 'text' | 'email' | 'tel' | 'select' | 'bool' | 'date';
    /** Nur «select»: Schlüssel → Bezeichnung */
    options?: Record<string, string>;
    editable?: boolean;
    /** Anzeige ausserhalb des Bearbeitens */
    render?: (row: T) => ReactNode;
    hidden?: boolean;
    className?: string;
};

type GridRow = { id: string };

type ViewState = {
    order: ColumnOrderState;
    visibility: VisibilityState;
    sorting: SortingState;
    sizing: ColumnSizingState;
};

const SELECT_ID = '_select';
const ACTIONS_ID = '_actions';

/** JJJJ-MM-TT → TT.MM.JJJJ */
export function formatDate(value: unknown): string {
    const raw = text(value);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(raw);

    return match ? `${match[3]}.${match[2]}.${match[1]}` : raw;
}

let measureContext: CanvasRenderingContext2D | null = null;

/** Textbreite in Pixeln (für automatische Spaltenbreite). */
function measure(value: string, weight = 400): number {
    if (typeof document === 'undefined') {
        return value.length * 8;
    }
    measureContext ??= document.createElement('canvas').getContext('2d');
    if (!measureContext) {
        return value.length * 8;
    }
    const family = getComputedStyle(document.body).fontFamily || 'sans-serif';
    measureContext.font = `${weight} 14px ${family}`;

    return measureContext.measureText(value).width;
}

export function text(value: unknown): string {
    if (typeof value === 'string') {
        return value;
    }
    if (typeof value === 'number' || typeof value === 'bigint') {
        return String(value);
    }
    if (typeof value === 'boolean') {
        return value ? 'ja' : 'nein';
    }

    return '';
}

function loadView(key: string): Partial<ViewState> {
    try {
        const raw = window.localStorage.getItem(key);
        const parsed: unknown = raw ? JSON.parse(raw) : {};

        return parsed && typeof parsed === 'object'
            ? (parsed as Partial<ViewState>)
            : {};
    } catch {
        return {};
    }
}

function saveView(key: string, view: ViewState) {
    try {
        window.localStorage.setItem(key, JSON.stringify(view));
    } catch {
        // Ohne Speicher gilt die Ansicht nur bis zum Neuladen.
    }
}

export default function DataGrid<T extends GridRow>({
    data,
    columns,
    storageKey,
    canEdit = false,
    onSave,
    selectable = true,
    bulkActions,
    rowActions,
    rowClassName,
    searchPlaceholder = 'Suchen …',
    emptyText = 'Keine Einträge gefunden.',
}: {
    data: T[];
    columns: GridColumn<T>[];
    storageKey: string;
    canEdit?: boolean;
    /** Speichert ein Feld; liefert den gespeicherten Datensatz oder wirft einen Fehler mit Meldung. */
    onSave?: (row: T, field: keyof T & string, value: unknown) => Promise<T>;
    selectable?: boolean;
    bulkActions?: (selected: T[], clear: () => void) => ReactNode;
    rowActions?: (row: T) => ReactNode;
    rowClassName?: (row: T) => string;
    searchPlaceholder?: string;
    emptyText?: string;
}) {
    // TanStack Table arbeitet mit veränderlichen Objekten: React Compiler hier aus.
    'use no memo';

    const dataIds = columns.map((c) => c.id as string);
    const defaultOrder = [
        ...(selectable ? [SELECT_ID] : []),
        ...dataIds,
        ...(rowActions ? [ACTIONS_ID] : []),
    ];
    const defaultVisibility: VisibilityState = Object.fromEntries(
        columns.filter((c) => c.hidden).map((c) => [c.id, false]),
    );

    const [stored] = useState(() => loadView(storageKey));
    const [rows, setRows] = useState<T[]>(data);
    const [sorting, setSorting] = useState<SortingState>(stored.sorting ?? []);
    const [columnOrder, setColumnOrder] = useState<ColumnOrderState>(() =>
        mergeOrder(stored.order, defaultOrder),
    );
    const [columnVisibility, setColumnVisibility] = useState<VisibilityState>(
        stored.visibility ?? defaultVisibility,
    );
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = useState('');
    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [dragging, setDragging] = useState<string | null>(null);
    const [columnSizing, setColumnSizing] = useState<ColumnSizingState>(
        stored.sizing ?? {},
    );
    const autoSized = useRef(Object.keys(stored.sizing ?? {}).length > 0);

    // Neue Daten vom Server (z. B. nach Löschen) übernehmen
    useEffect(() => {
        setRows(data);
        setRowSelection((current) =>
            Object.fromEntries(
                Object.entries(current).filter(([id]) =>
                    data.some((row) => row.id === id),
                ),
            ),
        );
    }, [data]);

    useEffect(() => {
        saveView(storageKey, {
            order: columnOrder,
            visibility: columnVisibility,
            sorting,
            sizing: columnSizing,
        });
    }, [storageKey, columnOrder, columnVisibility, sorting, columnSizing]);

    const byId = new Map(columns.map((c) => [c.id as string, c]));

    const display = (column: GridColumn<T>, value: unknown): string =>
        column.type === 'select'
            ? (column.options?.[text(value)] ?? text(value))
            : column.type === 'date'
              ? formatDate(value)
              : text(value);

    const filterFn: FilterFn<T> = (row, columnId, filter) => {
        const column = byId.get(columnId);
        const value = row.original[columnId as keyof T];
        const wanted = text(filter);

        if (wanted === '') {
            return true;
        }
        if (column?.type === 'select') {
            return text(value) === wanted;
        }
        if (column?.type === 'bool') {
            return (value === true ? 'ja' : 'nein') === wanted;
        }

        return text(value).toLowerCase().includes(wanted.toLowerCase());
    };

    const sortingFn: SortingFn<T> = (a, b, columnId) => {
        const column = byId.get(columnId);
        if (!column) {
            return 0;
        }

        return display(column, a.original[column.id]).localeCompare(
            display(column, b.original[column.id]),
            'de-CH',
            { numeric: true, sensitivity: 'base' },
        );
    };

    const globalFilterFn: FilterFn<T> = (row, _columnId, filter) => {
        const wanted = text(filter).toLowerCase().trim();
        if (wanted === '') {
            return true;
        }

        return columns.some(
            (column) =>
                columnVisibility[column.id] !== false &&
                display(column, row.original[column.id])
                    .toLowerCase()
                    .includes(wanted),
        );
    };

    const save = async (row: T, field: keyof T & string, value: unknown) => {
        if (!onSave) {
            return;
        }
        const saved = await onSave(row, field, value);
        setRows((current) =>
            current.map((r) => (r.id === saved.id ? saved : r)),
        );
    };

    const columnDefs: ColumnDef<T>[] = [
        ...(selectable
            ? [
                  {
                      id: SELECT_ID,
                      enableSorting: false,
                      enableHiding: false,
                      enableResizing: false,
                      size: 40,
                      header: () => null,
                      cell: ({ row }: { row: Row<T> }) => (
                          <Checkbox
                              checked={row.getIsSelected()}
                              onCheckedChange={(v: boolean | 'indeterminate') =>
                                  row.toggleSelected(v === true)
                              }
                              aria-label="Zeile auswählen"
                          />
                      ),
                  } satisfies ColumnDef<T>,
              ]
            : []),
        ...columns.map(
            (column): ColumnDef<T> => ({
                id: column.id,
                accessorFn: (row) => row[column.id],
                header: column.title,
                size: 160,
                minSize: 60,
                maxSize: 800,
                filterFn,
                sortingFn,
                cell: ({ row }) => (
                    <EditableCell
                        row={row.original}
                        column={column}
                        canEdit={canEdit && column.editable === true}
                        onSave={save}
                    />
                ),
            }),
        ),
        ...(rowActions
            ? [
                  {
                      id: ACTIONS_ID,
                      enableSorting: false,
                      enableHiding: false,
                      enableResizing: false,
                      size: 96,
                      header: () => null,
                      cell: ({ row }: { row: Row<T> }) =>
                          rowActions(row.original),
                  } satisfies ColumnDef<T>,
              ]
            : []),
    ];

    const table = useReactTable({
        data: rows,
        columns: columnDefs,
        state: {
            sorting,
            columnOrder,
            columnVisibility,
            columnFilters,
            globalFilter,
            rowSelection,
            columnSizing,
        },
        getRowId: (row) => row.id,
        enableColumnResizing: true,
        columnResizeMode: 'onChange',
        onColumnSizingChange: setColumnSizing,
        enableRowSelection: selectable,
        onSortingChange: setSorting,
        onColumnOrderChange: setColumnOrder,
        onColumnVisibilityChange: setColumnVisibility,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        onRowSelectionChange: setRowSelection,
        globalFilterFn,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
    });

    const visibleRows = table.getRowModel().rows;
    const selectedRows = rows.filter((row) => rowSelection[row.id]);
    const visibleSelected = visibleRows.filter((r) => r.getIsSelected()).length;
    const hiddenSelected = selectedRows.length - visibleSelected;
    const filtersActive = columnFilters.length > 0 || globalFilter !== '';

    const selectVisible = (value: boolean) =>
        setRowSelection((current) => {
            const next = { ...current };
            for (const row of visibleRows) {
                next[row.id] = value;
            }

            return Object.fromEntries(
                Object.entries(next).filter(([, selected]) => selected),
            );
        });
    const selectAll = (value: boolean) =>
        setRowSelection(
            value ? Object.fromEntries(rows.map((r) => [r.id, true])) : {},
        );

    /** Spalte an die Position «index» der vollständigen Reihenfolge setzen. */
    const moveColumn = (id: string, index: number) => {
        setColumnOrder((current) => {
            const order = mergeOrder(current, defaultOrder).filter(
                (c) => c !== id,
            );
            order.splice(Math.max(0, index), 0, id);

            return fixedEdges(order, selectable, Boolean(rowActions));
        });
    };

    /** Breite aus dem längsten Inhalt (sichtbare Zeilen) und dem Titel. */
    const fitWidth = (id: string): number => {
        const column = byId.get(id);
        if (!column) {
            return 160;
        }
        if (column.type === 'bool') {
            return Math.max(70, measure(column.title, 500) + 56);
        }
        const extra = column.type === 'select' ? 44 : 28;
        let widest = measure(column.title, 500) + 56;
        for (const row of table.getRowModel().rows) {
            widest = Math.max(
                widest,
                measure(display(column, row.original[column.id])) + extra,
            );
        }

        return Math.round(Math.min(480, Math.max(60, widest)));
    };

    const autoFit = (ids?: string[]) => {
        const targets =
            ids ??
            table
                .getVisibleLeafColumns()
                .map((c) => c.id)
                .filter((id) => byId.has(id));
        setColumnSizing((current) => ({
            ...current,
            ...Object.fromEntries(targets.map((id) => [id, fitWidth(id)])),
        }));
    };

    // Erste Anzeige ohne gemerkte Breiten: automatisch anpassen
    useEffect(() => {
        if (!autoSized.current && rows.length > 0) {
            autoSized.current = true;
            autoFit();
        }
    });

    const resetView = () => {
        setColumnSizing({});
        autoSized.current = false;
        setColumnOrder(defaultOrder);
        setColumnVisibility(defaultVisibility);
        setSorting([]);
        setColumnFilters([]);
        setGlobalFilter('');
    };

    const orderedDataColumns = table
        .getAllLeafColumns()
        .filter((c) => byId.has(c.id));

    return (
        <div className="grid gap-3">
            {/* Werkzeugleiste */}
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full sm:w-72">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={globalFilter}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        placeholder={searchPlaceholder}
                        className="pl-8"
                        aria-label="Tabelle durchsuchen"
                    />
                </div>

                {selectable && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <ListChecks /> Auswahl
                                {selectedRows.length > 0 &&
                                    ` (${selectedRows.length})`}
                                <ChevronDown className="opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuLabel>
                                Sichtbar: {visibleRows.length} von {rows.length}
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                onSelect={() => selectVisible(true)}
                            >
                                Sichtbare auswählen ({visibleRows.length})
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => selectVisible(false)}
                                disabled={visibleSelected === 0}
                            >
                                Sichtbare abwählen ({visibleSelected})
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onSelect={() => selectAll(true)}>
                                Alle auswählen ({rows.length})
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                onSelect={() => selectAll(false)}
                                disabled={selectedRows.length === 0}
                            >
                                Alle abwählen ({selectedRows.length})
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            <Columns3 /> Spalten
                            <ChevronDown className="opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-64">
                        <DropdownMenuLabel>
                            Ein-/ausblenden und Reihenfolge
                        </DropdownMenuLabel>
                        {orderedDataColumns.map((column, index) => (
                            <ColumnMenuRow
                                key={column.id}
                                column={column}
                                title={byId.get(column.id)?.title ?? column.id}
                                first={index === 0}
                                last={index === orderedDataColumns.length - 1}
                                onMove={(delta) => {
                                    const neighbour =
                                        orderedDataColumns[index + delta];
                                    if (neighbour) {
                                        const order = mergeOrder(
                                            columnOrder,
                                            defaultOrder,
                                        );
                                        const target = order.indexOf(
                                            neighbour.id,
                                        );
                                        moveColumn(column.id, target);
                                    }
                                }}
                            />
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => autoFit()}>
                            <MoveHorizontal /> Spaltenbreiten automatisch
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={resetView}>
                            <RotateCcw /> Ansicht zurücksetzen
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                {filtersActive && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setColumnFilters([]);
                            setGlobalFilter('');
                        }}
                    >
                        <X /> Filter löschen
                    </Button>
                )}

                <span className="ml-auto text-sm text-muted-foreground">
                    {visibleRows.length === rows.length
                        ? `${rows.length} Einträge`
                        : `${visibleRows.length} von ${rows.length} Einträgen`}
                </span>
            </div>

            {selectable && selectedRows.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 rounded-lg border border-brand/30 bg-muted/50 px-3 py-2 text-sm">
                    <span className="font-medium">
                        {selectedRows.length} ausgewählt
                    </span>
                    {hiddenSelected > 0 && (
                        <span className="text-muted-foreground">
                            (davon {hiddenSelected} durch Filter ausgeblendet)
                        </span>
                    )}
                    <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
                        {bulkActions?.(selectedRows, () => selectAll(false))}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => selectAll(false)}
                        >
                            <X /> Auswahl aufheben
                        </Button>
                    </div>
                </div>
            )}

            <div className="overflow-x-auto rounded-lg border">
                <table
                    className="caption-bottom border-collapse text-sm"
                    style={{
                        tableLayout: 'fixed',
                        width: table.getTotalSize(),
                        minWidth: '100%',
                    }}
                >
                    <thead className="bg-muted/40">
                        {table.getHeaderGroups().map((group) => (
                            <tr key={group.id} className="border-b">
                                {group.headers.map((header) => {
                                    const id = header.column.id;
                                    const isData = byId.has(id);
                                    const sorted = header.column.getIsSorted();

                                    if (id === SELECT_ID) {
                                        return (
                                            <th
                                                key={header.id}
                                                style={{
                                                    width: header.getSize(),
                                                }}
                                                className="border-r px-3"
                                            >
                                                <Checkbox
                                                    checked={
                                                        visibleRows.length >
                                                            0 &&
                                                        visibleSelected ===
                                                            visibleRows.length
                                                            ? true
                                                            : visibleSelected >
                                                                0
                                                              ? 'indeterminate'
                                                              : false
                                                    }
                                                    onCheckedChange={() =>
                                                        selectVisible(
                                                            visibleSelected !==
                                                                visibleRows.length,
                                                        )
                                                    }
                                                    aria-label="Sichtbare Zeilen auswählen"
                                                    title="Sichtbare Zeilen aus- oder abwählen"
                                                />
                                            </th>
                                        );
                                    }

                                    if (!isData) {
                                        return (
                                            <th
                                                key={header.id}
                                                style={{
                                                    width: header.getSize(),
                                                }}
                                            />
                                        );
                                    }

                                    return (
                                        <th
                                            key={header.id}
                                            style={{ width: header.getSize() }}
                                            onDragOver={(e) => {
                                                if (
                                                    dragging &&
                                                    dragging !== id
                                                ) {
                                                    e.preventDefault();
                                                }
                                            }}
                                            onDrop={(e) => {
                                                e.preventDefault();
                                                if (
                                                    dragging &&
                                                    dragging !== id
                                                ) {
                                                    const order = mergeOrder(
                                                        columnOrder,
                                                        defaultOrder,
                                                    );
                                                    moveColumn(
                                                        dragging,
                                                        order.indexOf(id),
                                                    );
                                                }
                                                setDragging(null);
                                            }}
                                            onDragEnd={() => setDragging(null)}
                                            className={cn(
                                                'relative h-9 border-r px-2 text-left align-middle font-semibold whitespace-nowrap text-foreground',
                                                dragging === id && 'opacity-40',
                                            )}
                                        >
                                            <div
                                                className="flex min-w-0 items-center gap-1"
                                                draggable
                                                onDragStart={(e) => {
                                                    setDragging(id);
                                                    e.dataTransfer.effectAllowed =
                                                        'move';
                                                }}
                                                title="Ziehen zum Verschieben"
                                            >
                                                <GripVertical
                                                    className="size-3.5 shrink-0 cursor-grab text-muted-foreground/60"
                                                    aria-hidden
                                                />
                                                <button
                                                    type="button"
                                                    className="inline-flex min-w-0 items-center gap-1 truncate rounded px-1 py-0.5 hover:bg-accent"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    title="Sortieren"
                                                >
                                                    {flexRender(
                                                        header.column.columnDef
                                                            .header,
                                                        header.getContext(),
                                                    )}
                                                    {sorted === 'asc' ? (
                                                        <ArrowUp className="size-3.5" />
                                                    ) : sorted === 'desc' ? (
                                                        <ArrowDown className="size-3.5" />
                                                    ) : (
                                                        <ArrowUpDown className="size-3.5 opacity-40" />
                                                    )}
                                                </button>
                                            </div>
                                            {header.column.getCanResize() && (
                                                <div
                                                    role="separator"
                                                    aria-orientation="vertical"
                                                    aria-label={`Breite ${byId.get(id)?.title ?? id}`}
                                                    title="Ziehen = Breite ändern, Doppelklick = automatisch"
                                                    onMouseDown={header.getResizeHandler()}
                                                    onTouchStart={header.getResizeHandler()}
                                                    onDoubleClick={() =>
                                                        autoFit([id])
                                                    }
                                                    className={cn(
                                                        'absolute top-0 right-0 h-full w-1.5 cursor-col-resize touch-none select-none hover:bg-brand/60',
                                                        header.column.getIsResizing() &&
                                                            'bg-brand',
                                                    )}
                                                />
                                            )}
                                        </th>
                                    );
                                })}
                            </tr>
                        ))}
                        <tr className="border-b">
                            {table.getVisibleLeafColumns().map((column) => {
                                const def = byId.get(column.id);

                                return (
                                    <th
                                        key={column.id}
                                        className="border-r px-1.5 pb-1.5 font-normal last:border-r-0"
                                    >
                                        {def && (
                                            <ColumnFilter
                                                column={column}
                                                def={def}
                                            />
                                        )}
                                    </th>
                                );
                            })}
                        </tr>
                    </thead>
                    <tbody>
                        {visibleRows.map((row, index) => (
                            <tr
                                key={row.id}
                                data-state={
                                    row.getIsSelected() ? 'selected' : undefined
                                }
                                className={cn(
                                    'border-b transition-colors last:border-0 hover:bg-accent/50 data-[state=selected]:bg-brand/10',
                                    index % 2 === 1 && 'bg-muted/40',
                                    rowClassName?.(row.original),
                                )}
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <td
                                        key={cell.id}
                                        className={cn(
                                            'overflow-hidden border-r px-2 py-1 align-middle last:border-r-0',
                                            byId.get(cell.column.id)?.className,
                                        )}
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {visibleRows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={
                                        table.getVisibleLeafColumns().length
                                    }
                                    className="px-3 py-10 text-center text-muted-foreground"
                                >
                                    {emptyText}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            {canEdit && (
                <p className="text-xs text-muted-foreground">
                    Zelle anklicken oder mit Enter öffnen, um zu bearbeiten.
                    Enter speichert, Esc bricht ab. Spaltenköpfe lassen sich
                    verschieben; Rand ziehen ändert die Breite, Doppelklick
                    passt sie automatisch an.
                </p>
            )}
        </div>
    );
}

/** Gespeicherte Reihenfolge mit den aktuellen Spalten abgleichen (neue anhängen, alte weglassen). */
function mergeOrder(
    stored: ColumnOrderState | undefined,
    defaults: string[],
): ColumnOrderState {
    const known = (stored ?? []).filter((id) => defaults.includes(id));
    const missing = defaults.filter((id) => !known.includes(id));

    return fixedEdges(
        [...known, ...missing],
        defaults.includes(SELECT_ID),
        defaults.includes(ACTIONS_ID),
    );
}

/** Auswahl bleibt vorne, Aktionen hinten. */
function fixedEdges(
    order: string[],
    select: boolean,
    actions: boolean,
): string[] {
    const middle = order.filter((id) => id !== SELECT_ID && id !== ACTIONS_ID);

    return [
        ...(select ? [SELECT_ID] : []),
        ...middle,
        ...(actions ? [ACTIONS_ID] : []),
    ];
}

function ColumnMenuRow<T>({
    column,
    title,
    first,
    last,
    onMove,
}: {
    column: Column<T>;
    title: string;
    first: boolean;
    last: boolean;
    onMove: (delta: number) => void;
}) {
    const id = `grid-col-${column.id}`;

    return (
        <div className="flex items-center gap-2 px-2 py-1 text-sm">
            <Checkbox
                id={id}
                checked={column.getIsVisible()}
                onCheckedChange={(v: boolean | 'indeterminate') =>
                    column.toggleVisibility(v === true)
                }
            />
            <label htmlFor={id} className="flex-1 cursor-pointer">
                {title}
            </label>
            <Button
                variant="ghost"
                size="icon"
                className="size-6"
                disabled={first}
                onClick={() => onMove(-1)}
                aria-label={`${title} nach links`}
            >
                <ChevronUp />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-6"
                disabled={last}
                onClick={() => onMove(1)}
                aria-label={`${title} nach rechts`}
            >
                <ChevronDown />
            </Button>
        </div>
    );
}

function ColumnFilter<T>({
    column,
    def,
}: {
    column: Column<T>;
    def: GridColumn<T>;
}) {
    const value = text(column.getFilterValue());
    const set = (v: string) => column.setFilterValue(v === '' ? undefined : v);
    const base =
        'h-7 w-full min-w-20 rounded-md border border-input bg-background px-2 text-xs';

    if (def.type === 'select' || def.type === 'bool') {
        const options =
            def.type === 'bool'
                ? { ja: 'Ja', nein: 'Nein' }
                : (def.options ?? {});

        return (
            <select
                className={cn(base, value !== '' && 'border-brand')}
                value={value}
                onChange={(e) => set(e.target.value)}
                aria-label={`Filter ${def.title}`}
            >
                <option value="">Alle</option>
                {Object.entries(options).map(([key, label]) => (
                    <option key={key} value={key}>
                        {label}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <input
            className={cn(base, value !== '' && 'border-brand')}
            value={value}
            onChange={(e) => set(e.target.value)}
            placeholder="Filter …"
            aria-label={`Filter ${def.title}`}
        />
    );
}

function EditableCell<T extends GridRow>({
    row,
    column,
    canEdit,
    onSave,
}: {
    row: T;
    column: GridColumn<T>;
    canEdit: boolean;
    onSave: (row: T, field: keyof T & string, value: unknown) => Promise<void>;
}) {
    const value = row[column.id];
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const committing = useRef(false);

    const shown = column.render
        ? column.render(row)
        : column.type === 'select'
          ? (column.options?.[text(value)] ?? text(value))
          : column.type === 'date'
            ? formatDate(value)
            : text(value);

    const commit = async (next: unknown) => {
        if (committing.current) {
            return;
        }
        if (
            next === value ||
            (next === '' && (value === null || value === ''))
        ) {
            setEditing(false);
            setError(null);

            return;
        }
        committing.current = true;
        setSaving(true);
        try {
            await onSave(row, column.id, next);
            setEditing(false);
            setError(null);
        } catch (e) {
            const message =
                e instanceof Error ? e.message : 'Speichern fehlgeschlagen.';
            setError(message);
            toast.error(`${column.title}: ${message}`);
        } finally {
            committing.current = false;
            setSaving(false);
        }
    };

    if (column.type === 'bool') {
        return (
            <div className="flex items-center gap-2">
                <Checkbox
                    checked={value === true}
                    disabled={!canEdit || saving}
                    onCheckedChange={(v: boolean | 'indeterminate') =>
                        void commit(v === true)
                    }
                    aria-label={column.title}
                />
                {saving && <Loader2 className="size-3.5 animate-spin" />}
            </div>
        );
    }

    if (!canEdit) {
        return <div className="truncate">{shown}</div>;
    }

    if (editing) {
        const onKeyDown = (
            e: KeyboardEvent<HTMLInputElement | HTMLSelectElement>,
        ) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                setEditing(false);
                setError(null);
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                void commit(draft);
            }
        };
        const cls = cn(
            'h-8 w-full min-w-32 rounded-md border bg-background px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring',
            error ? 'border-destructive' : 'border-brand',
        );

        return (
            <div className="relative" title={error ?? undefined}>
                {column.type === 'select' ? (
                    <select
                        autoFocus
                        className={cls}
                        value={draft}
                        disabled={saving}
                        aria-invalid={error !== null}
                        onChange={(e) => {
                            setDraft(e.target.value);
                            void commit(e.target.value);
                        }}
                        onBlur={() => !saving && setEditing(false)}
                        onKeyDown={onKeyDown}
                    >
                        {Object.entries(column.options ?? {}).map(
                            ([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ),
                        )}
                    </select>
                ) : (
                    <input
                        autoFocus
                        type={
                            column.type === 'email' ||
                            column.type === 'tel' ||
                            column.type === 'date'
                                ? column.type
                                : 'text'
                        }
                        className={cls}
                        value={draft}
                        disabled={saving}
                        aria-invalid={error !== null}
                        onChange={(e) => setDraft(e.target.value)}
                        onBlur={() => void commit(draft)}
                        onKeyDown={onKeyDown}
                    />
                )}
                {saving && (
                    <Loader2 className="absolute top-2 right-2 size-4 animate-spin text-muted-foreground" />
                )}
            </div>
        );
    }

    const open = () => {
        setDraft(text(value));
        setEditing(true);
    };

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={open}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === 'F2') {
                    e.preventDefault();
                    open();
                }
            }}
            className="group/cell -mx-1 flex min-h-8 cursor-text items-center gap-1 rounded px-1 hover:bg-accent/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            title="Bearbeiten"
        >
            <span className="min-w-0 flex-1 truncate">
                {shown === '' ? (
                    <span className="text-muted-foreground/50">–</span>
                ) : (
                    shown
                )}
            </span>
            <Pencil className="size-3 shrink-0 opacity-0 group-hover/cell:opacity-40" />
        </div>
    );
}
