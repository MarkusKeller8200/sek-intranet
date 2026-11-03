import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronDown,
    ChevronRight,
    Columns3,
    Download,
    GripVertical,
    Layers,
    ListChecks,
    Loader2,
    MoveHorizontal,
    Pin,
    PinOff,
    Plus,
    Redo2,
    RotateCcw,
    Search,
    Undo2,
    X,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { createPortal } from 'react-dom';
import type {
    ClipboardEvent,
    CSSProperties,
    KeyboardEvent,
    MouseEvent as ReactMouseEvent,
    ReactNode,
} from 'react';
import { toast } from 'sonner';
import FilterCell from '@/components/grid/filter-cell';
import OptionLabel from '@/components/grid/option-label';
import Popover from '@/components/grid/popover';
import type {
    CellChange,
    GridColumn,
    GridFilter,
    GridRow,
    GridViewState,
    SaveSource,
    SavedView,
    SortRule,
} from '@/components/grid/types';
import {
    coerce,
    display,
    isActive,
    matches,
    measure,
    parseTsv,
    splitMulti,
    text,
    toTsv,
} from '@/components/grid/utils';
import ViewsMenu from '@/components/grid/views-menu';
import { Button } from '@/components/ui/button';
import { Calendar, isoDate } from '@/components/ui/calendar';
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

export type {
    CellChange,
    GridColumn,
    SavedView,
} from '@/components/grid/types';
export { formatDate, text } from '@/components/grid/utils';

/**
 * Bearbeitbare Tabelle (Excel-ähnlich):
 * - Zellen mit Maus oder Pfeiltasten wählen, Bereiche mit Umschalt, direkt lostippen,
 *   Enter/Tab speichern und weiterspringen, Esc bricht ab
 * - Kopieren/Einfügen (auch aus Excel), Entf leert, Ctrl+Z / Ctrl+Y
 * - Filter nach Datentyp mit UND/ODER, Suche, mehrstufige Sortierung (Umschalt+Klick)
 * - Spalten verschieben, ein-/ausblenden, fixieren, Breite ziehen oder automatisch
 * - Gruppieren, Auswahl (alle / nur sichtbare), Export, gespeicherte Ansichten
 * Kopfzeile und fixierte Spalten bleiben beim Scrollen stehen.
 */

const SELECT_W = 40;
const ACTIONS_W = 104;
const DEFAULT_W = 160;

/** Sortierstufen (1, 2, 3). */
const MAX_SORT = 3;

type Cell = { row: number; col: number };

type Item<T> =
    | { kind: 'group'; key: string; label: string; count: number }
    | { kind: 'row'; row: T; index: number };

function loadState(key: string): GridViewState {
    try {
        const raw = window.localStorage.getItem(key);
        const parsed: unknown = raw ? JSON.parse(raw) : {};

        return parsed && typeof parsed === 'object'
            ? (parsed as GridViewState)
            : {};
    } catch {
        return {};
    }
}

function saveState(key: string, state: GridViewState) {
    try {
        window.localStorage.setItem(key, JSON.stringify(state));
    } catch {
        // Ohne Speicher gilt die Ansicht nur bis zum Neuladen.
    }
}

function isEditableTarget(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'SELECT' ||
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable)
    );
}

export default function DataGrid<T extends GridRow>({
    data,
    columns,
    storageKey,
    gridId,
    views: initialViews = [],
    canShareViews = false,
    canEdit = false,
    onSaveCells,
    selectable = true,
    bulkActions,
    rowActions,
    rowMenu,
    rowClassName,
    onExport,
    searchPlaceholder = 'Suchen …',
    emptyText = 'Keine Einträge gefunden.',
    onAddRow,
    toolbar,
    extraExports = [],
}: {
    data: T[];
    columns: GridColumn<T>[];
    storageKey: string;
    /** Kennung für gespeicherte Ansichten auf dem Server (ohne: keine Ansichten) */
    gridId?: string;
    views?: SavedView[];
    canShareViews?: boolean;
    canEdit?: boolean;
    /** Speichert Zellen (alles oder nichts); liefert die gespeicherten Datensätze oder wirft einen Fehler. */
    onSaveCells?: (
        changes: CellChange<T>[],
        source: SaveSource,
    ) => Promise<T[]>;
    selectable?: boolean;
    bulkActions?: (selected: T[], clear: () => void) => ReactNode;
    rowActions?: (row: T) => ReactNode;
    /** Zusätzliche Einträge im Kontextmenü einer Zeile (z. B. Öffnen, Löschen) */
    rowMenu?: (row: T) => GridMenuItem[];
    rowClassName?: (row: T) => string;
    onExport?: (
        format: 'csv' | 'xlsx',
        ids: string[],
        columns: string[],
    ) => void;
    searchPlaceholder?: string;
    emptyText?: string;
    /** «Neue Zeile»: legt einen Datensatz an und liefert dessen ID (die Zeile wird danach zum Bearbeiten gewählt). */
    onAddRow?: () => Promise<string | null>;
    /** Zusätzliche Knöpfe in der Werkzeugleiste */
    toolbar?: ReactNode;
    /** Weitere Formate im Menü «Export» (z. B. PDF, Markdown) – erhalten die sichtbaren Zeilen und Spalten */
    extraExports?: {
        label: string;
        run: (ids: string[], columns: string[]) => void;
    }[];
}) {
    const byId = useMemo(
        () => new Map(columns.map((c) => [c.id as string, c])),
        [columns],
    );
    const dataIds = columns.map((c) => c.id as string);
    const defaultVisibility = Object.fromEntries(
        columns.filter((c) => c.hidden).map((c) => [c.id, false]),
    );
    const defaultState: GridViewState = {
        order: dataIds,
        visibility: defaultVisibility,
        sizing: {},
        pinned: dataIds.slice(0, 1),
        sorting: [],
        filters: {},
        logic: 'and',
        search: '',
        grouping: null,
        viewId: null,
    };

    const [state, setState] = useState<GridViewState>(() => ({
        ...defaultState,
        ...loadState(storageKey),
    }));
    const [rows, setRows] = useState<T[]>(data);
    const [views, setViews] = useState<SavedView[]>(initialViews);
    const [selected, setSelected] = useState<Set<string>>(() => new Set());
    const [collapsed, setCollapsed] = useState<Set<string>>(() => new Set());
    const [active, setActive] = useState<Cell | null>(null);
    const [anchor, setAnchor] = useState<Cell | null>(null);
    const [editing, setEditing] = useState<{
        cell: Cell;
        initial: string;
    } | null>(null);
    const [status, setStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>(
        'idle',
    );
    const [dragging, setDragging] = useState<string | null>(null);
    // Kontextmenü (#78): Zelle/Zeile/Tabelle oder Spaltenkopf
    const [menu, setMenu] = useState<{
        x: number;
        y: number;
        kind: 'cell' | 'header';
        row: number;
        col: number;
    } | null>(null);
    // Einrastpunkt beim Spalten-Ziehen (#77): Zielspalte, die Spalte landet links oder rechts davon
    const [dropTarget, setDropTarget] = useState<string | null>(null);
    const undoStack = useRef<CellChange<T>[][]>([]);
    const redoStack = useRef<CellChange<T>[][]>([]);
    const [, setHistoryTick] = useState(0);
    const gridRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const autoSized = useRef(Object.keys(state.sizing ?? {}).length > 0);

    const patch = useCallback(
        (next: Partial<GridViewState>) =>
            setState((current) => ({ ...current, ...next })),
        [],
    );

    // Neue Daten vom Server übernehmen (z. B. nach Löschen)
    useEffect(() => {
        setRows(data);
        setSelected(
            (current) =>
                new Set(
                    [...current].filter((id) => data.some((r) => r.id === id)),
                ),
        );
    }, [data]);

    useEffect(() => saveState(storageKey, state), [storageKey, state]);

    // ---------- Spalten ----------
    const order = [
        ...(state.order ?? []).filter((id) => byId.has(id)),
        ...dataIds.filter((id) => !(state.order ?? []).includes(id)),
    ];
    const visibility = state.visibility ?? {};
    const pinned = (state.pinned ?? []).filter(
        (id) => byId.has(id) && visibility[id] !== false,
    );
    const visibleIds = [
        ...order.filter((id) => pinned.includes(id)),
        ...order.filter(
            (id) => !pinned.includes(id) && visibility[id] !== false,
        ),
    ];
    const visibleCols = visibleIds.map((id) => byId.get(id) as GridColumn<T>);
    const width = (id: string) => state.sizing?.[id] ?? DEFAULT_W;

    const leftOffsets: Record<string, number> = {};
    {
        let left = selectable ? SELECT_W : 0;
        for (const id of visibleIds) {
            if (pinned.includes(id)) {
                leftOffsets[id] = left;
                left += width(id);
            }
        }
    }
    const lastPinned = pinned.length > 0 ? visibleIds[pinned.length - 1] : null;

    // ---------- Filtern, Sortieren, Gruppieren ----------
    const filters = Object.entries(state.filters ?? {}).filter(
        ([id, f]) => byId.has(id) && isActive(f),
    );
    const search = (state.search ?? '').toLowerCase().trim();

    const filtered = rows.filter((row) => {
        if (filters.length > 0) {
            const results = filters.map(([id, f]) =>
                matches(byId.get(id) as GridColumn<T>, row[id as keyof T], f),
            );
            const ok =
                state.logic === 'or'
                    ? results.some(Boolean)
                    : results.every(Boolean);
            if (!ok) {
                return false;
            }
        }

        return (
            search === '' ||
            visibleCols.some((c) =>
                display(c, row[c.id]).toLowerCase().includes(search),
            )
        );
    });

    const sorting = (state.sorting ?? []).filter((s) => byId.has(s.id));
    const sorted = [...filtered].sort((a, b) => {
        for (const rule of sorting) {
            const column = byId.get(rule.id) as GridColumn<T>;
            const va =
                column.type === 'date'
                    ? text(a[column.id])
                    : display(column, a[column.id]);
            const vb =
                column.type === 'date'
                    ? text(b[column.id])
                    : display(column, b[column.id]);
            // Leere Werte immer ans Ende
            if (va === '' && vb !== '') {
                return 1;
            }
            if (vb === '' && va !== '') {
                return -1;
            }
            const result = va.localeCompare(vb, 'de-CH', {
                numeric: true,
                sensitivity: 'base',
            });
            if (result !== 0) {
                return rule.desc ? -result : result;
            }
        }

        return 0;
    });

    const grouping =
        state.grouping && byId.has(state.grouping) ? state.grouping : null;
    const items: Item<T>[] = [];
    const navRows: T[] = [];
    if (grouping) {
        const column = byId.get(grouping) as GridColumn<T>;
        const groups = new Map<string, T[]>();
        for (const row of sorted) {
            const shown = display(column, row[column.id]);
            const keys = column.separator
                ? [
                      ...new Set(
                          shown
                              .split(column.separator)
                              .map((k) => k.trim())
                              .filter((k) => k !== ''),
                      ),
                  ]
                : [shown];
            for (const key of keys.length > 0 ? keys : ['']) {
                groups.set(key, [...(groups.get(key) ?? []), row]);
            }
        }
        const keys = [...groups.keys()].sort((a, b) =>
            a === '' ? 1 : b === '' ? -1 : a.localeCompare(b, 'de-CH'),
        );
        for (const key of keys) {
            const members = groups.get(key) ?? [];
            items.push({
                kind: 'group',
                key,
                label: key === '' ? '(leer)' : key,
                count: members.length,
            });
            if (!collapsed.has(key)) {
                for (const row of members) {
                    items.push({ kind: 'row', row, index: navRows.length });
                    navRows.push(row);
                }
            }
        }
    } else {
        for (const row of sorted) {
            items.push({ kind: 'row', row, index: navRows.length });
            navRows.push(row);
        }
    }

    // ---------- Auswahl ----------
    const selectedRows = rows.filter((r) => selected.has(r.id));
    const visibleSelected = filtered.filter((r) => selected.has(r.id)).length;
    const hiddenSelected = selectedRows.length - visibleSelected;
    const selectVisible = (value: boolean) =>
        setSelected((current) => {
            const next = new Set(current);
            for (const row of filtered) {
                if (value) {
                    next.add(row.id);
                } else {
                    next.delete(row.id);
                }
            }

            return next;
        });
    const selectAll = (value: boolean) =>
        setSelected(value ? new Set(rows.map((r) => r.id)) : new Set());
    const toggleRow = (id: string) =>
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    // ---------- Zellbereich ----------
    const clamp = (cell: Cell): Cell => ({
        row: Math.max(0, Math.min(navRows.length - 1, cell.row)),
        col: Math.max(0, Math.min(visibleCols.length - 1, cell.col)),
    });
    const range = active
        ? {
              top: Math.min(active.row, (anchor ?? active).row),
              bottom: Math.max(active.row, (anchor ?? active).row),
              left: Math.min(active.col, (anchor ?? active).col),
              right: Math.max(active.col, (anchor ?? active).col),
          }
        : null;
    const inRange = (r: number, c: number) =>
        range !== null &&
        r >= range.top &&
        r <= range.bottom &&
        c >= range.left &&
        c <= range.right;
    const rangeSize = range
        ? (range.bottom - range.top + 1) * (range.right - range.left + 1)
        : 0;

    // Markierung ausserhalb der sichtbaren Zeilen (nach Filtern) zurücksetzen
    useEffect(() => {
        if (
            active &&
            (active.row >= navRows.length || active.col >= visibleCols.length)
        ) {
            setActive(null);
            setAnchor(null);
            setEditing(null);
        }
    }, [active, navRows.length, visibleCols.length]);

    const focusGrid = () => gridRef.current?.focus({ preventScroll: true });

    // Neue Zeile: sobald sie in den Daten ist, erste bearbeitbare Zelle wählen
    const [pendingRow, setPendingRow] = useState<string | null>(null);
    useEffect(() => {
        if (pendingRow === null) {
            return;
        }
        const index = navRows.findIndex((r) => r.id === pendingRow);
        if (index < 0) {
            return;
        }
        const col = Math.max(
            0,
            visibleCols.findIndex((c) => c.editable === true),
        );
        setActive({ row: index, col });
        setAnchor({ row: index, col });
        setPendingRow(null);
        requestAnimationFrame(() => {
            gridRef.current
                ?.querySelector(`[data-cell="${index}:${col}"]`)
                ?.scrollIntoView({ block: 'center', inline: 'nearest' });
            gridRef.current?.focus({ preventScroll: true });
        });
    }, [pendingRow, navRows, visibleCols]);

    const scrollToCell = (cell: Cell) => {
        requestAnimationFrame(() => {
            gridRef.current
                ?.querySelector(`[data-cell="${cell.row}:${cell.col}"]`)
                ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
        });
    };

    const moveTo = (cell: Cell, extend = false) => {
        if (navRows.length === 0 || visibleCols.length === 0) {
            return;
        }
        const next = clamp(cell);
        if (!extend) {
            setAnchor(next);
        } else if (!anchor) {
            setAnchor(active ?? next);
        }
        setActive(next);
        scrollToCell(next);
    };

    // ---------- Speichern, Rückgängig ----------
    const applySaved = (saved: T[]) =>
        setRows((current) =>
            current.map((r) => saved.find((s) => s.id === r.id) ?? r),
        );

    const save = async (
        changes: CellChange<T>[],
        source: SaveSource,
        record = true,
    ): Promise<boolean> => {
        const real = changes.filter((c) => c.old !== c.value);
        if (!onSaveCells || real.length === 0) {
            return true;
        }
        setStatus('saving');
        try {
            applySaved(await onSaveCells(real, source));
            if (record) {
                undoStack.current = [...undoStack.current, real].slice(-100);
                redoStack.current = [];
            }
            setHistoryTick((t) => t + 1);
            setStatus('saved');

            return true;
        } catch (e) {
            toast.error(
                e instanceof Error ? e.message : 'Speichern fehlgeschlagen.',
            );
            setStatus('error');

            return false;
        }
    };

    const undo = async () => {
        const last = undoStack.current.at(-1);
        if (!last) {
            return;
        }
        const reverse = last.map((c) => ({ ...c, old: c.value, value: c.old }));
        if (await save(reverse, 'Rückgängig', false)) {
            undoStack.current = undoStack.current.slice(0, -1);
            redoStack.current = [...redoStack.current, last];
            setHistoryTick((t) => t + 1);
            toast.info(`${last.length} Änderung(en) rückgängig gemacht.`);
        }
    };

    const redo = async () => {
        const last = redoStack.current.at(-1);
        if (!last) {
            return;
        }
        if (await save(last, 'Wiederholen', false)) {
            redoStack.current = redoStack.current.slice(0, -1);
            undoStack.current = [...undoStack.current, last];
            setHistoryTick((t) => t + 1);
        }
    };

    const isEditable = (column: GridColumn<T>) =>
        canEdit && column.editable === true && Boolean(onSaveCells);

    const change = (
        row: T,
        column: GridColumn<T>,
        value: unknown,
    ): CellChange<T> => ({
        id: row.id,
        field: column.id,
        old: row[column.id] ?? null,
        value,
    });

    // ---------- Kopieren, Einfügen, Leeren ----------
    const rangeCells = (): { row: T; column: GridColumn<T> }[][] => {
        if (!range) {
            return [];
        }
        const result = [];
        for (let r = range.top; r <= range.bottom; r++) {
            const line = [];
            for (let c = range.left; c <= range.right; c++) {
                line.push({ row: navRows[r], column: visibleCols[c] });
            }
            result.push(line);
        }

        return result;
    };

    const onCopy = (e: ClipboardEvent<HTMLDivElement>) => {
        if (editing || !range || isEditableTarget(e.target)) {
            return;
        }
        e.preventDefault();
        const values = rangeCells().map((line) =>
            line.map(({ row, column }) => display(column, row[column.id])),
        );
        e.clipboardData.setData('text/plain', toTsv(values));
        toast.success(`${rangeSize} Zelle(n) kopiert.`);
    };

    const clearRange = async (source: SaveSource = 'Löschen') => {
        const changes = rangeCells()
            .flat()
            .filter(
                ({ column }) =>
                    isEditable(column) &&
                    !column.required &&
                    column.type !== 'bool',
            )
            .map(({ row, column }) => change(row, column, null));
        await save(changes, source);
    };

    const onCut = (e: ClipboardEvent<HTMLDivElement>) => {
        if (editing || !range || isEditableTarget(e.target)) {
            return;
        }
        onCopy(e);
        void clearRange();
    };

    const onPaste = (e: ClipboardEvent<HTMLDivElement>) => {
        if (editing || !active || isEditableTarget(e.target)) {
            return;
        }
        e.preventDefault();
        pasteText(e.clipboardData.getData('text/plain'));
    };

    /** Text (Tabulator/Zeilen wie aus Excel) in den markierten Bereich einfügen. */
    const pasteText = (raw: string) => {
        const table = parseTsv(raw);
        if (table.length === 0 || !range) {
            return;
        }

        // Ein einzelner Wert füllt den ganzen markierten Bereich (wie in Excel)
        const single = table.length === 1 && table[0].length === 1;
        const height = single ? range.bottom - range.top + 1 : table.length;
        const widthCells = single
            ? range.right - range.left + 1
            : Math.max(...table.map((r) => r.length));

        const changes: CellChange<T>[] = [];
        const invalid: string[] = [];
        let skipped = 0;
        for (let r = 0; r < height; r++) {
            const row = navRows[range.top + r];
            if (!row) {
                skipped++;
                continue;
            }
            for (let c = 0; c < widthCells; c++) {
                const column = visibleCols[range.left + c];
                const input = single ? table[0][0] : table[r]?.[c];
                if (!column || input === undefined) {
                    continue;
                }
                if (!isEditable(column)) {
                    continue;
                }
                const value = coerce(column, input);
                if (
                    value === undefined ||
                    (value === null && column.required)
                ) {
                    invalid.push(`${column.title}: «${input}»`);
                    continue;
                }
                changes.push(change(row, column, value));
            }
        }

        if (invalid.length > 0) {
            toast.error(
                `Nicht eingefügt – ${invalid.length} ungültige Werte, z. B. ${invalid.slice(0, 3).join('; ')}`,
            );

            return;
        }
        if (skipped > 0) {
            toast.info(
                `${skipped} Zeile(n) ohne Ziel ignoriert (es werden keine Zeilen angelegt).`,
            );
        }
        void save(changes, 'Einfügen').then((ok) => {
            if (ok && changes.length > 0) {
                toast.success(`${changes.length} Zelle(n) eingefügt.`);
            }
        });
    };

    // ---------- Bearbeiten ----------
    const startEdit = (cell: Cell, initial?: string) => {
        const row = navRows[cell.row];
        const column = visibleCols[cell.col];
        if (!row || !column || !isEditable(column)) {
            return;
        }
        if (column.type === 'bool') {
            void save(
                [change(row, column, row[column.id] !== true)],
                'Tabelle',
            );

            return;
        }
        setEditing({
            cell,
            initial:
                initial ??
                (column.type === 'select' ||
                column.type === 'multi' ||
                column.type === 'date'
                    ? text(row[column.id])
                    : display(column, row[column.id])),
        });
    };

    const commitEdit = async (
        value: string,
        move?: 'down' | 'right' | 'left',
    ) => {
        if (!editing) {
            return;
        }
        const { cell } = editing;
        const row = navRows[cell.row];
        const column = visibleCols[cell.col];
        if (row && column) {
            const parsed =
                column.type === 'select'
                    ? value === ''
                        ? null
                        : value
                    : coerce(column, value);
            if (parsed === undefined) {
                toast.error(
                    `${column.title}: «${value}» ist kein gültiger Wert.`,
                );

                return;
            }
            const ok = await save([change(row, column, parsed)], 'Tabelle');
            if (!ok) {
                return;
            }
        }
        setEditing(null);
        focusGrid();
        if (move === 'down') {
            moveTo({ row: cell.row + 1, col: cell.col });
        } else if (move === 'right') {
            moveTo({ row: cell.row, col: cell.col + 1 });
        } else if (move === 'left') {
            moveTo({ row: cell.row, col: cell.col - 1 });
        }
    };

    const cancelEdit = () => {
        setEditing(null);
        focusGrid();
    };

    // ---------- Tastatur ----------
    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        if (editing || isEditableTarget(e.target)) {
            return;
        }
        const mod = e.ctrlKey || e.metaKey;

        if (mod && e.key.toLowerCase() === 'z' && !e.shiftKey) {
            e.preventDefault();
            void undo();

            return;
        }
        if (
            mod &&
            (e.key.toLowerCase() === 'y' ||
                (e.key.toLowerCase() === 'z' && e.shiftKey))
        ) {
            e.preventDefault();
            void redo();

            return;
        }
        if (mod && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            searchRef.current?.focus();

            return;
        }
        if (mod && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            setAnchor({ row: 0, col: 0 });
            setActive({ row: navRows.length - 1, col: visibleCols.length - 1 });

            return;
        }
        if (!active) {
            if (e.key.startsWith('Arrow') || e.key === 'Tab') {
                e.preventDefault();
                moveTo({ row: 0, col: 0 });
            }

            return;
        }

        const page = 10;
        const extend = e.shiftKey;
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                moveTo(
                    {
                        row: mod ? navRows.length - 1 : active.row + 1,
                        col: active.col,
                    },
                    extend,
                );

                return;
            case 'ArrowUp':
                e.preventDefault();
                moveTo(
                    { row: mod ? 0 : active.row - 1, col: active.col },
                    extend,
                );

                return;
            case 'ArrowRight':
                e.preventDefault();
                moveTo(
                    {
                        row: active.row,
                        col: mod ? visibleCols.length - 1 : active.col + 1,
                    },
                    extend,
                );

                return;
            case 'ArrowLeft':
                e.preventDefault();
                moveTo(
                    { row: active.row, col: mod ? 0 : active.col - 1 },
                    extend,
                );

                return;
            case 'PageDown':
                e.preventDefault();
                moveTo({ row: active.row + page, col: active.col }, extend);

                return;
            case 'PageUp':
                e.preventDefault();
                moveTo({ row: active.row - page, col: active.col }, extend);

                return;
            case 'Home':
                e.preventDefault();
                moveTo({ row: mod ? 0 : active.row, col: 0 }, extend);

                return;
            case 'End':
                e.preventDefault();
                moveTo(
                    {
                        row: mod ? navRows.length - 1 : active.row,
                        col: visibleCols.length - 1,
                    },
                    extend,
                );

                return;
            case 'Tab':
                e.preventDefault();
                moveTo({
                    row: active.row,
                    col: active.col + (e.shiftKey ? -1 : 1),
                });

                return;
            case 'Enter':
            case 'F2':
                e.preventDefault();
                if (e.key === 'Enter' && e.shiftKey) {
                    moveTo({ row: active.row - 1, col: active.col });
                } else {
                    startEdit(active);
                }

                return;
            case 'Escape':
                setAnchor(active);

                return;
            case 'ContextMenu':
            case 'F10': {
                if (e.key === 'F10' && !e.shiftKey) {
                    return;
                }
                e.preventDefault();
                const el = gridRef.current?.querySelector<HTMLElement>(
                    `[data-cell="${active.row}:${active.col}"]`,
                );
                const r = el?.getBoundingClientRect();
                setMenu({
                    x: (r?.left ?? 0) + 12,
                    y: (r?.bottom ?? 0) - 4,
                    kind: 'cell',
                    row: active.row,
                    col: active.col,
                });

                return;
            }
            case 'Delete':
            case 'Backspace':
                e.preventDefault();
                void clearRange();

                return;
            case ' ': {
                // Umschalt+Leertaste: Zeile auswählen; Leertaste: Häkchen umschalten oder lostippen
                const column = visibleCols[active.col];
                e.preventDefault();
                if (e.shiftKey && selectable) {
                    toggleRow(navRows[active.row].id);
                } else if (column?.type === 'bool') {
                    startEdit(active);
                } else if (
                    column &&
                    isEditable(column) &&
                    column.type !== 'select' &&
                    column.type !== 'multi' &&
                    column.type !== 'date'
                ) {
                    startEdit(active, ' ');
                } else if (
                    column &&
                    isEditable(column) &&
                    column.type === 'multi'
                ) {
                    startEdit(active);
                }

                return;
            }
            default:
                // Direkt lostippen ersetzt den Inhalt
                if (e.key.length === 1 && !mod && !e.altKey) {
                    const column = visibleCols[active.col];
                    if (
                        column &&
                        isEditable(column) &&
                        column.type !== 'bool'
                    ) {
                        e.preventDefault();
                        // Auswahl und Datum öffnen den passenden Editor, Text übernimmt das Zeichen
                        startEdit(
                            active,
                            column.type === 'select' ||
                                column.type === 'multi' ||
                                column.type === 'date'
                                ? undefined
                                : e.key,
                        );
                    }
                }
        }
    };

    // ---------- Spaltenbreite ----------
    const fitWidth = (id: string): number => {
        const column = byId.get(id);
        if (!column) {
            return DEFAULT_W;
        }
        // Titel + Griff, Sortierpfeil, Fixier-Symbol und Rand
        let widest =
            measure(column.title, 600) + 64 + (pinned.includes(id) ? 18 : 0);
        if (column.type !== 'bool') {
            const extra =
                column.type === 'select' || column.type === 'multi' ? 44 : 28;
            for (const row of filtered.slice(0, 2000)) {
                widest = Math.max(
                    widest,
                    measure(display(column, row[column.id])) + extra,
                );
            }
        }

        return Math.round(Math.min(480, Math.max(70, widest)));
    };

    /**
     * Breiten nach Inhalt; beim Einpassen aller Spalten werden breite Spalten so weit verkleinert,
     * dass die Tabelle in die sichtbare Breite (ohne vertikalen Scrollbalken) passt (#63).
     */
    const autoFit = (ids: string[] = visibleIds) => {
        const fitted = Object.fromEntries(ids.map((id) => [id, fitWidth(id)]));
        const available =
            (gridRef.current?.clientWidth ?? 0) -
            (selectable ? SELECT_W : 0) -
            (rowActions ? ACTIONS_W : 0);
        if (ids === visibleIds && available > 0) {
            const total = ids.reduce((sum, id) => sum + fitted[id], 0);
            const floor = 200;
            const excess = ids.reduce(
                (sum, id) => sum + Math.max(0, fitted[id] - floor),
                0,
            );
            if (total > available && excess > 0) {
                const cut = Math.min(1, (total - available) / excess);
                for (const id of ids) {
                    if (fitted[id] > floor) {
                        fitted[id] = Math.floor(
                            fitted[id] - (fitted[id] - floor) * cut,
                        );
                    }
                }
            }
        }
        setState((current) => ({
            ...current,
            sizing: { ...current.sizing, ...fitted },
        }));
    };

    useEffect(() => {
        if (!autoSized.current && rows.length > 0) {
            autoSized.current = true;
            autoFit();
        }
    });

    const startResize = (id: string, e: ReactMouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        const startX = e.clientX;
        const startW = width(id);
        const onMove = (ev: MouseEvent) =>
            setState((current) => ({
                ...current,
                sizing: {
                    ...current.sizing,
                    [id]: Math.max(
                        60,
                        Math.min(800, startW + ev.clientX - startX),
                    ),
                },
            }));
        const onUp = () => {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.body.style.cursor = '';
        };
        document.body.style.cursor = 'col-resize';
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    };

    // ---------- Spaltenreihenfolge, Fixieren, Sortieren ----------
    const moveColumn = (id: string, target: string) => {
        const next = order.filter((c) => c !== id);
        next.splice(
            next.indexOf(target) +
                (order.indexOf(id) < order.indexOf(target) ? 1 : 0),
            0,
            id,
        );
        patch({ order: next });
    };
    const shiftColumn = (id: string, delta: number) => {
        const visibleOrder = order.filter((c) => visibility[c] !== false);
        const index = visibleOrder.indexOf(id);
        const neighbour = visibleOrder[index + delta];
        if (neighbour) {
            moveColumn(id, neighbour);
        }
    };
    const togglePin = (id: string) =>
        patch({
            pinned: pinned.includes(id)
                ? pinned.filter((p) => p !== id)
                : [...pinned, id],
        });

    const toggleSort = (id: string, multi: boolean) => {
        const existing = sorting.find((s) => s.id === id);
        let next: SortRule[];
        if (!existing) {
            // Höchstens drei Stufen (#76): eine vierte ersetzt die letzte
            next = multi
                ? [...sorting.slice(0, MAX_SORT - 1), { id, desc: false }]
                : [{ id, desc: false }];
        } else if (!existing.desc) {
            next = sorting.map((s) => (s.id === id ? { id, desc: true } : s));
            next = multi ? next : next.filter((s) => s.id === id);
        } else {
            next = sorting.filter((s) => s.id !== id);
        }
        patch({ sorting: next });
    };

    const setFilter = (id: string, filter: GridFilter | undefined) => {
        const others = Object.entries(state.filters ?? {}).filter(
            ([key]) => key !== id,
        );
        patch({
            filters: Object.fromEntries(
                filter ? [...others, [id, filter]] : others,
            ),
        });
    };

    const resetView = () => {
        autoSized.current = false;
        setCollapsed(new Set());
        setState(defaultState);
    };

    const applyView = (view: SavedView) => {
        setCollapsed(new Set());
        setState({ ...defaultState, ...view.state, viewId: view.id });
    };

    const filtersActive = filters.length > 0 || search !== '';

    // ---------- Kontextmenü (#78) ----------
    const copyText = (value: string, what: string) =>
        void navigator.clipboard
            ?.writeText(value)
            .then(() => toast.success(`${what} kopiert.`))
            .catch(() =>
                toast.error(
                    'Kopieren nicht möglich (Zwischenablage gesperrt).',
                ),
            );
    const menuItems = (): GridMenuItem[] => {
        if (!menu) {
            return [];
        }
        const column = visibleCols[menu.col];
        if (!column) {
            return [];
        }
        const id = column.id as string;
        const rule = sorting.find((s) => s.id === id);
        const sortTo = (desc: boolean, add = false): SortRule[] =>
            add
                ? [
                      ...sorting
                          .filter((s) => s.id !== id)
                          .slice(0, MAX_SORT - 1),
                      { id, desc },
                  ]
                : [{ id, desc }];
        const canGroup = groupable.some((g) => g.id === column.id);
        const columnItems: GridMenuItem[] = [
            { heading: `Spalte «${column.title}»` },
            {
                label: 'Aufsteigend sortieren',
                icon: <ArrowUp />,
                onSelect: () => patch({ sorting: sortTo(false) }),
            },
            {
                label: 'Absteigend sortieren',
                icon: <ArrowDown />,
                onSelect: () => patch({ sorting: sortTo(true) }),
            },
            {
                label: 'Als weitere Sortierstufe',
                icon: <ArrowUpDown />,
                disabled: !!rule || sorting.length >= MAX_SORT,
                onSelect: () => patch({ sorting: sortTo(false, true) }),
            },
            {
                label: 'Sortierung dieser Spalte entfernen',
                disabled: !rule,
                onSelect: () =>
                    patch({ sorting: sorting.filter((s) => s.id !== id) }),
            },
            { separator: true },
            grouping === id
                ? {
                      label: 'Gruppierung aufheben',
                      icon: <Layers />,
                      onSelect: () => patch({ grouping: null }),
                  }
                : {
                      label: 'Nach dieser Spalte gruppieren',
                      icon: <Layers />,
                      disabled: !canGroup,
                      onSelect: () => {
                          setCollapsed(new Set());
                          patch({ grouping: id });
                      },
                  },
            {
                label: pinned.includes(id)
                    ? 'Fixierung lösen'
                    : 'Spalte fixieren',
                icon: pinned.includes(id) ? <PinOff /> : <Pin />,
                onSelect: () => togglePin(id),
            },
            {
                label: 'Nach links',
                disabled: menu.col === 0,
                onSelect: () => shiftColumn(id, -1),
            },
            {
                label: 'Nach rechts',
                disabled: menu.col === visibleCols.length - 1,
                onSelect: () => shiftColumn(id, 1),
            },
            {
                label: 'Breite automatisch',
                icon: <MoveHorizontal />,
                onSelect: () => autoFit([id]),
            },
            {
                label: 'Spalte ausblenden',
                icon: <Columns3 />,
                disabled: visibleCols.length === 1,
                onSelect: () =>
                    patch({ visibility: { ...visibility, [id]: false } }),
            },
            {
                label: 'Filter dieser Spalte entfernen',
                icon: <X />,
                disabled: !isActive(state.filters?.[id]),
                onSelect: () => setFilter(id, undefined),
            },
        ];
        if (menu.kind === 'header') {
            return columnItems;
        }
        const row = navRows[menu.row];
        if (!row) {
            return columnItems;
        }
        const value = row[column.id];
        const shown = display(column, value);
        const short = shown.length > 24 ? `${shown.slice(0, 24)}…` : shown;
        const raw = text(value);
        const values =
            column.type === 'multi' ? splitMulti(column, value) : [raw];
        const choice = column.type === 'select' || column.type === 'multi';
        const editable = isEditable(column);

        return [
            { heading: rangeSize > 1 ? `${rangeSize} Zellen` : 'Zelle' },
            {
                label: rangeSize > 1 ? 'Markierung kopieren' : 'Kopieren',
                shortcut: 'Ctrl+C',
                onSelect: () =>
                    copyText(
                        toTsv(
                            rangeCells().map((line) =>
                                line.map((x) =>
                                    display(x.column, x.row[x.column.id]),
                                ),
                            ),
                        ),
                        rangeSize > 1 ? `${rangeSize} Zellen` : 'Zelle',
                    ),
            },
            {
                label: 'Einfügen',
                shortcut: 'Ctrl+V',
                disabled: !canEdit,
                onSelect: () =>
                    void navigator.clipboard
                        ?.readText()
                        .then(pasteText)
                        .catch(() =>
                            toast.error(
                                'Einfügen über das Menü nicht möglich – bitte Ctrl+V verwenden.',
                            ),
                        ),
            },
            {
                label: 'Bearbeiten',
                shortcut: 'Enter',
                disabled: !editable || column.type === 'bool',
                onSelect: () => startEdit({ row: menu.row, col: menu.col }),
            },
            {
                label: rangeSize > 1 ? 'Markierung leeren' : 'Leeren',
                shortcut: 'Entf',
                disabled: !editable || column.required === true,
                onSelect: () => void clearRange(),
            },
            { separator: true },
            {
                label:
                    shown === ''
                        ? 'Nur leere anzeigen'
                        : `Nur «${short}» anzeigen`,
                onSelect: () =>
                    setFilter(
                        id,
                        shown === ''
                            ? { op: 'empty' }
                            : choice
                              ? { op: 'in', values }
                              : column.type === 'date'
                                ? { op: 'equals', value: raw }
                                : { op: 'equals', value: shown },
                    ),
            },
            {
                label:
                    shown === '' ? 'Leere ausblenden' : `«${short}» ausblenden`,
                disabled: !choice && shown !== '',
                onSelect: () =>
                    setFilter(
                        id,
                        shown === ''
                            ? { op: 'notEmpty' }
                            : { op: 'notIn', values },
                    ),
            },
            { separator: true },
            { heading: 'Zeile' },
            ...(selectable
                ? [
                      {
                          label: selected.has(row.id)
                              ? 'Zeile abwählen'
                              : 'Zeile auswählen',
                          shortcut: 'Umschalt+Leer',
                          icon: <ListChecks />,
                          onSelect: () => toggleRow(row.id),
                      },
                  ]
                : []),
            {
                label: 'Zeile kopieren',
                onSelect: () =>
                    copyText(
                        toTsv([
                            visibleCols.map((col) => display(col, row[col.id])),
                        ]),
                        'Zeile',
                    ),
            },
            ...(rowMenu ? rowMenu(row) : []),
            { separator: true },
            { heading: 'Tabelle' },
            ...(selectable
                ? [
                      {
                          label: 'Alle sichtbaren auswählen',
                          onSelect: () => selectVisible(true),
                      },
                  ]
                : []),
            {
                label: 'Filter und Suche löschen',
                icon: <X />,
                disabled: !filtersActive,
                onSelect: () => patch({ filters: {}, search: '' }),
            },
            {
                label: 'Sortierung entfernen',
                disabled: sorting.length === 0,
                onSelect: () => patch({ sorting: [] }),
            },
            {
                label: 'Spaltenbreiten automatisch',
                icon: <MoveHorizontal />,
                onSelect: () => autoFit(),
            },
            {
                label: 'Ansicht zurücksetzen',
                icon: <RotateCcw />,
                onSelect: resetView,
            },
            { separator: true },
            ...columnItems,
        ];
    };
    const totalWidth =
        (selectable ? SELECT_W : 0) +
        visibleIds.reduce((sum, id) => sum + width(id), 0) +
        (rowActions ? ACTIONS_W : 0);
    const colCount =
        visibleCols.length + (selectable ? 1 : 0) + (rowActions ? 1 : 0);

    const stickyStyle = (id: string): CSSProperties | undefined =>
        leftOffsets[id] !== undefined
            ? { position: 'sticky', left: leftOffsets[id] }
            : undefined;

    const groupable = columns.filter(
        (c) =>
            c.type === 'select' ||
            c.type === 'multi' ||
            c.type === 'bool' ||
            c.type === 'text',
    );

    return (
        <div className="grid min-w-0 gap-3">
            {/* Werkzeugleiste */}
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative w-full sm:w-64">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        ref={searchRef}
                        value={state.search ?? ''}
                        onChange={(e) => patch({ search: e.target.value })}
                        placeholder={searchPlaceholder}
                        className="pl-8"
                        aria-label="Tabelle durchsuchen"
                    />
                </div>

                {gridId && (
                    <ViewsMenu
                        grid={gridId}
                        views={views}
                        onViewsChange={setViews}
                        current={state}
                        canShare={canShareViews}
                        onApply={applyView}
                        onReset={resetView}
                    />
                )}

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
                                Sichtbar: {filtered.length} von {rows.length}
                            </DropdownMenuLabel>
                            <DropdownMenuItem
                                onSelect={() => selectVisible(true)}
                            >
                                Sichtbare auswählen ({filtered.length})
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
                    <DropdownMenuContent
                        align="start"
                        className="max-h-[70vh] w-72 overflow-y-auto"
                    >
                        <DropdownMenuLabel>
                            {visibleIds.length} sichtbar · {pinned.length}{' '}
                            fixiert
                        </DropdownMenuLabel>
                        {order.map((id, index) => {
                            const column = byId.get(id) as GridColumn<T>;
                            const shown = visibility[id] !== false;
                            const isPinned = pinned.includes(id);

                            return (
                                <div
                                    key={id}
                                    className="flex items-center gap-1.5 px-2 py-1 text-sm"
                                >
                                    <input
                                        id={`grid-col-${id}`}
                                        type="checkbox"
                                        checked={shown}
                                        onChange={() =>
                                            patch({
                                                visibility: {
                                                    ...visibility,
                                                    [id]: !shown,
                                                },
                                            })
                                        }
                                    />
                                    <label
                                        htmlFor={`grid-col-${id}`}
                                        className="flex-1 cursor-pointer truncate"
                                    >
                                        {column.title}
                                    </label>
                                    <button
                                        type="button"
                                        className={cn(
                                            'rounded p-1 hover:bg-accent',
                                            isPinned
                                                ? 'text-brand'
                                                : 'text-muted-foreground',
                                        )}
                                        onClick={() => togglePin(id)}
                                        disabled={!shown}
                                        title={
                                            isPinned
                                                ? 'Fixierung lösen'
                                                : 'Links fixieren'
                                        }
                                        aria-label={`${column.title} ${isPinned ? 'lösen' : 'fixieren'}`}
                                    >
                                        {isPinned ? (
                                            <PinOff className="size-3.5" />
                                        ) : (
                                            <Pin className="size-3.5" />
                                        )}
                                    </button>
                                    <button
                                        type="button"
                                        className="rounded p-1 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                        disabled={index === 0}
                                        onClick={() => shiftColumn(id, -1)}
                                        aria-label={`${column.title} nach vorne`}
                                    >
                                        <ArrowUp className="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        className="rounded p-1 text-muted-foreground hover:bg-accent disabled:opacity-30"
                                        disabled={index === order.length - 1}
                                        onClick={() => shiftColumn(id, 1)}
                                        aria-label={`${column.title} nach hinten`}
                                    >
                                        <ArrowDown className="size-3.5" />
                                    </button>
                                </div>
                            );
                        })}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={() => autoFit()}>
                            <MoveHorizontal /> Spaltenbreiten automatisch
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={resetView}>
                            <RotateCcw /> Ansicht zurücksetzen
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <SortMenu
                    columns={columns.filter((c) => byId.has(c.id))}
                    sorting={sorting}
                    onChange={(next) => patch({ sorting: next })}
                />

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm">
                            <Layers />{' '}
                            {grouping
                                ? `Gruppiert: ${byId.get(grouping)?.title ?? ''}`
                                : 'Gruppieren'}
                            <ChevronDown className="opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        className="max-h-[60vh] overflow-y-auto"
                    >
                        <DropdownMenuItem
                            onSelect={() => patch({ grouping: null })}
                        >
                            Keine Gruppierung
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        {groupable.map((column) => (
                            <DropdownMenuItem
                                key={column.id}
                                onSelect={() => {
                                    setCollapsed(new Set());
                                    patch({ grouping: column.id });
                                }}
                            >
                                {column.title}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {onExport && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Download /> Export
                                <ChevronDown className="opacity-60" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuLabel>
                                Sichtbare Spalten in dieser Reihenfolge
                            </DropdownMenuLabel>
                            {(['xlsx', 'csv'] as const).map((format) => (
                                <DropdownMenuItem
                                    key={format}
                                    onSelect={() =>
                                        onExport(
                                            format,
                                            sorted.map((r) => r.id),
                                            visibleIds,
                                        )
                                    }
                                >
                                    {format === 'xlsx' ? 'Excel' : 'CSV'} –
                                    sichtbare Zeilen ({sorted.length})
                                </DropdownMenuItem>
                            ))}
                            {extraExports.map((x) => (
                                <DropdownMenuItem
                                    key={x.label}
                                    onSelect={() =>
                                        x.run(
                                            sorted.map((r) => r.id),
                                            visibleIds,
                                        )
                                    }
                                >
                                    {`${x.label} – sichtbare Zeilen (${sorted.length})`}
                                </DropdownMenuItem>
                            ))}
                            {selectedRows.length > 0 &&
                                (['xlsx', 'csv'] as const).map((format) => (
                                    <DropdownMenuItem
                                        key={`sel-${format}`}
                                        onSelect={() =>
                                            onExport(
                                                format,
                                                sorted
                                                    .filter((r) =>
                                                        selected.has(r.id),
                                                    )
                                                    .map((r) => r.id),
                                                visibleIds,
                                            )
                                        }
                                    >
                                        {format === 'xlsx' ? 'Excel' : 'CSV'} –
                                        nur Auswahl ({visibleSelected})
                                    </DropdownMenuItem>
                                ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}

                {canEdit && onAddRow && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            void onAddRow().then((id) => {
                                if (id) {
                                    patch({ search: '', filters: {} });
                                    setPendingRow(id);
                                }
                            })
                        }
                    >
                        <Plus /> Neue Zeile
                    </Button>
                )}
                {toolbar}

                {canEdit && onSaveCells && (
                    <div className="flex items-center">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            disabled={undoStack.current.length === 0}
                            onClick={() => void undo()}
                            title="Rückgängig (Ctrl+Z)"
                            aria-label="Rückgängig"
                        >
                            <Undo2 />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8"
                            disabled={redoStack.current.length === 0}
                            onClick={() => void redo()}
                            title="Wiederholen (Ctrl+Y)"
                            aria-label="Wiederholen"
                        >
                            <Redo2 />
                        </Button>
                    </div>
                )}

                {filters.length > 1 && (
                    <div
                        className="flex items-center rounded-md border p-0.5 text-xs"
                        role="group"
                        aria-label="Filter verknüpfen"
                    >
                        {(['and', 'or'] as const).map((logic) => (
                            <button
                                key={logic}
                                type="button"
                                onClick={() => patch({ logic })}
                                className={cn(
                                    'rounded px-2 py-1',
                                    (state.logic ?? 'and') === logic
                                        ? 'bg-primary text-primary-foreground'
                                        : 'hover:bg-accent',
                                )}
                                title={
                                    logic === 'and'
                                        ? 'Alle Bedingungen müssen zutreffen'
                                        : 'Eine Bedingung genügt'
                                }
                            >
                                {logic === 'and' ? 'UND' : 'ODER'}
                            </button>
                        ))}
                    </div>
                )}

                {filtersActive && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => patch({ filters: {}, search: '' })}
                    >
                        <X /> Filter löschen
                    </Button>
                )}

                <span
                    className="ml-auto flex items-center gap-2 text-sm text-muted-foreground"
                    aria-live="polite"
                >
                    {status === 'saving' && (
                        <>
                            <Loader2 className="size-3.5 animate-spin" />{' '}
                            Speichert …
                        </>
                    )}
                    {status === 'saved' && (
                        <span className="text-success">Gespeichert</span>
                    )}
                    {status === 'error' && (
                        <span className="text-destructive">
                            Nicht gespeichert
                        </span>
                    )}
                    <span>
                        {filtered.length === rows.length
                            ? `${rows.length} Einträge`
                            : `${filtered.length} von ${rows.length}`}
                    </span>
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

            {menu && (
                <GridMenu
                    x={menu.x}
                    y={menu.y}
                    items={menuItems()}
                    onClose={() => {
                        setMenu(null);
                        requestAnimationFrame(focusGrid);
                    }}
                />
            )}

            {/* Tabelle: eigener Scrollbereich, Kopfzeile und fixierte Spalten bleiben stehen */}
            <div
                ref={gridRef}
                role="grid"
                aria-rowcount={navRows.length}
                aria-colcount={visibleCols.length}
                tabIndex={0}
                onKeyDown={onKeyDown}
                onCopy={onCopy}
                onCut={onCut}
                onPaste={onPaste}
                className="grid-scroll relative max-h-[calc(100vh-15rem)] min-h-64 [scrollbar-gutter:stable] overflow-auto rounded-lg border focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <table
                    className="border-separate border-spacing-0 text-sm"
                    style={{
                        tableLayout: 'fixed',
                        width: totalWidth,
                        minWidth: '100%',
                    }}
                >
                    <colgroup>
                        {selectable && <col style={{ width: SELECT_W }} />}
                        {visibleIds.map((id) => (
                            <col key={id} style={{ width: width(id) }} />
                        ))}
                        {rowActions && <col style={{ width: ACTIONS_W }} />}
                    </colgroup>
                    <thead className="sticky top-0 z-20">
                        <tr>
                            {selectable && (
                                <th className="sticky left-0 z-30 h-9 border-r border-b bg-muted px-3 text-left">
                                    <Checkbox
                                        checked={
                                            filtered.length > 0 &&
                                            visibleSelected === filtered.length
                                                ? true
                                                : visibleSelected > 0
                                                  ? 'indeterminate'
                                                  : false
                                        }
                                        onCheckedChange={() =>
                                            selectVisible(
                                                visibleSelected !==
                                                    filtered.length,
                                            )
                                        }
                                        aria-label="Sichtbare Zeilen auswählen"
                                        title="Sichtbare Zeilen aus- oder abwählen"
                                    />
                                </th>
                            )}
                            {visibleCols.map((column) => {
                                const id = column.id as string;
                                const rule = sorting.find((s) => s.id === id);
                                const level = sorting.findIndex(
                                    (s) => s.id === id,
                                );

                                return (
                                    <th
                                        key={id}
                                        style={stickyStyle(id)}
                                        onContextMenu={(e) => {
                                            e.preventDefault();
                                            setMenu({
                                                x: e.clientX,
                                                y: e.clientY,
                                                kind: 'header',
                                                row: -1,
                                                col: visibleIds.indexOf(id),
                                            });
                                        }}
                                        onDragOver={(e) => {
                                            if (dragging && dragging !== id) {
                                                e.preventDefault();
                                                if (dropTarget !== id) {
                                                    setDropTarget(id);
                                                }
                                            }
                                        }}
                                        onDragLeave={(e) => {
                                            if (
                                                !e.currentTarget.contains(
                                                    e.relatedTarget as Node | null,
                                                )
                                            ) {
                                                setDropTarget((t) =>
                                                    t === id ? null : t,
                                                );
                                            }
                                        }}
                                        onDrop={(e) => {
                                            e.preventDefault();
                                            if (dragging && dragging !== id) {
                                                moveColumn(dragging, id);
                                            }
                                            setDragging(null);
                                            setDropTarget(null);
                                        }}
                                        className={cn(
                                            'relative h-9 border-r border-b bg-muted px-2 text-left align-middle font-semibold whitespace-nowrap text-foreground',
                                            leftOffsets[id] !== undefined &&
                                                'z-30',
                                            id === lastPinned &&
                                                'border-r-2 border-r-brand/40',
                                            dragging === id && 'opacity-40',
                                            dropTarget === id &&
                                                dragging &&
                                                'bg-brand/10',
                                        )}
                                    >
                                        {dropTarget === id && dragging && (
                                            <span
                                                aria-hidden
                                                className={cn(
                                                    'pointer-events-none absolute inset-y-0 z-20 w-1 rounded-full bg-brand',
                                                    order.indexOf(dragging) <
                                                        order.indexOf(id)
                                                        ? '-right-0.5'
                                                        : '-left-0.5',
                                                )}
                                            />
                                        )}
                                        <div
                                            className="flex min-w-0 items-center gap-1"
                                            draggable
                                            onDragStart={(e) => {
                                                setDragging(id);
                                                e.dataTransfer.effectAllowed =
                                                    'move';
                                            }}
                                            onDragEnd={() => {
                                                setDragging(null);
                                                setDropTarget(null);
                                            }}
                                            title="Ziehen zum Verschieben"
                                        >
                                            <GripVertical
                                                className="size-3.5 shrink-0 cursor-grab text-muted-foreground/60"
                                                aria-hidden
                                            />
                                            <button
                                                type="button"
                                                className="inline-flex min-w-0 items-center gap-1 rounded px-1 py-0.5 hover:bg-accent"
                                                onClick={(e) =>
                                                    toggleSort(id, e.shiftKey)
                                                }
                                                title="Sortieren (Umschalt+Klick: weitere Stufe)"
                                            >
                                                <span className="truncate">
                                                    {column.title}
                                                </span>
                                                {rule ? (
                                                    rule.desc ? (
                                                        <ArrowDown className="size-3.5 shrink-0" />
                                                    ) : (
                                                        <ArrowUp className="size-3.5 shrink-0" />
                                                    )
                                                ) : (
                                                    <ArrowUpDown className="size-3.5 shrink-0 opacity-40" />
                                                )}
                                                {sorting.length > 1 &&
                                                    level >= 0 && (
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {level + 1}
                                                        </span>
                                                    )}
                                            </button>
                                            {pinned.includes(id) && (
                                                <Pin
                                                    className="size-3 shrink-0 text-brand"
                                                    aria-label="fixiert"
                                                />
                                            )}
                                        </div>
                                        <div
                                            role="separator"
                                            aria-orientation="vertical"
                                            aria-label={`Breite ${column.title}`}
                                            title="Ziehen = Breite ändern, Doppelklick = automatisch"
                                            onMouseDown={(e) =>
                                                startResize(id, e)
                                            }
                                            onDoubleClick={() => autoFit([id])}
                                            // letzte Spalte: Griff innerhalb, sonst entsteht ein unnötiger horizontaler Scrollbalken (#63)
                                            className={cn(
                                                'absolute top-0 z-10 h-full w-2 cursor-col-resize select-none hover:bg-brand/50',
                                                !rowActions &&
                                                    id ===
                                                        visibleIds[
                                                            visibleIds.length -
                                                                1
                                                        ]
                                                    ? 'right-0'
                                                    : '-right-1',
                                            )}
                                        />
                                    </th>
                                );
                            })}
                            {rowActions && (
                                <th className="h-9 border-b bg-muted" />
                            )}
                        </tr>
                        <tr>
                            {selectable && (
                                <th className="sticky left-0 z-30 border-r border-b bg-muted" />
                            )}
                            {visibleCols.map((column) => {
                                const id = column.id as string;

                                return (
                                    <th
                                        key={id}
                                        style={stickyStyle(id)}
                                        className={cn(
                                            'border-r border-b bg-muted px-1 pb-1 font-normal',
                                            leftOffsets[id] !== undefined &&
                                                'z-30',
                                            id === lastPinned &&
                                                'border-r-2 border-r-brand/40',
                                        )}
                                    >
                                        <FilterCell
                                            column={column}
                                            filter={state.filters?.[id]}
                                            onChange={(f) => setFilter(id, f)}
                                        />
                                    </th>
                                );
                            })}
                            {rowActions && <th className="border-b bg-muted" />}
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((item) => {
                            if (item.kind === 'group') {
                                const isCollapsed = collapsed.has(item.key);

                                return (
                                    <tr key={`g-${item.key}`}>
                                        <td
                                            colSpan={colCount}
                                            className="border-b bg-secondary p-0"
                                        >
                                            <button
                                                type="button"
                                                className="sticky left-0 flex items-center gap-2 px-3 py-1.5 font-medium"
                                                onClick={() =>
                                                    setCollapsed((current) => {
                                                        const next = new Set(
                                                            current,
                                                        );
                                                        if (
                                                            next.has(item.key)
                                                        ) {
                                                            next.delete(
                                                                item.key,
                                                            );
                                                        } else {
                                                            next.add(item.key);
                                                        }

                                                        return next;
                                                    })
                                                }
                                                aria-expanded={!isCollapsed}
                                            >
                                                {isCollapsed ? (
                                                    <ChevronRight className="size-4" />
                                                ) : (
                                                    <ChevronDown className="size-4" />
                                                )}
                                                {item.label}
                                                <span className="rounded-full bg-background px-2 text-xs text-muted-foreground">
                                                    {item.count}
                                                </span>
                                            </button>
                                        </td>
                                    </tr>
                                );
                            }

                            const { row, index } = item;
                            const isSelected = selected.has(row.id);
                            const stripe = index % 2 === 1;
                            const base = isSelected
                                ? 'bg-[color-mix(in_oklab,var(--brand)_12%,var(--background))]'
                                : stripe
                                  ? 'bg-muted'
                                  : 'bg-background';

                            return (
                                <tr
                                    key={row.id}
                                    className={cn(
                                        'group/row',
                                        rowClassName?.(row),
                                    )}
                                >
                                    {selectable && (
                                        <td
                                            className={cn(
                                                'sticky left-0 z-10 border-r border-b px-3 group-hover/row:bg-accent',
                                                base,
                                            )}
                                        >
                                            <Checkbox
                                                checked={isSelected}
                                                onCheckedChange={() =>
                                                    toggleRow(row.id)
                                                }
                                                aria-label="Zeile auswählen"
                                            />
                                        </td>
                                    )}
                                    {visibleCols.map((column, c) => {
                                        const id = column.id as string;
                                        const isActiveCell =
                                            active?.row === index &&
                                            active.col === c;
                                        const isEditing =
                                            editing?.cell.row === index &&
                                            editing.cell.col === c;
                                        const highlighted =
                                            rangeSize > 1 && inRange(index, c);
                                        const value = row[column.id];

                                        return (
                                            <td
                                                key={id}
                                                // Daten nicht übersetzen (Auswahlwerte und Ja/Nein schon)
                                                translate={
                                                    column.type === 'select' ||
                                                    column.type === 'bool'
                                                        ? undefined
                                                        : 'no'
                                                }
                                                data-cell={`${index}:${c}`}
                                                role="gridcell"
                                                aria-selected={
                                                    isActiveCell || highlighted
                                                }
                                                style={stickyStyle(id)}
                                                onContextMenu={(e) => {
                                                    if (isEditing) {
                                                        return;
                                                    }
                                                    e.preventDefault();
                                                    if (!inRange(index, c)) {
                                                        setAnchor({
                                                            row: index,
                                                            col: c,
                                                        });
                                                        setActive({
                                                            row: index,
                                                            col: c,
                                                        });
                                                    }
                                                    setMenu({
                                                        x: e.clientX,
                                                        y: e.clientY,
                                                        kind: 'cell',
                                                        row: index,
                                                        col: c,
                                                    });
                                                }}
                                                onMouseDown={(e) => {
                                                    if (
                                                        isEditing ||
                                                        isEditableTarget(
                                                            e.target,
                                                        ) ||
                                                        // Rechtsklick in der Markierung behält sie (Kontextmenü)
                                                        (e.button === 2 &&
                                                            inRange(index, c))
                                                    ) {
                                                        return;
                                                    }
                                                    if (e.shiftKey && active) {
                                                        e.preventDefault();
                                                        setActive({
                                                            row: index,
                                                            col: c,
                                                        });
                                                    } else {
                                                        setAnchor({
                                                            row: index,
                                                            col: c,
                                                        });
                                                        setActive({
                                                            row: index,
                                                            col: c,
                                                        });
                                                    }
                                                    if (editing) {
                                                        setEditing(null);
                                                    }
                                                    requestAnimationFrame(
                                                        focusGrid,
                                                    );
                                                }}
                                                onMouseEnter={(e) => {
                                                    if (
                                                        e.buttons === 1 &&
                                                        active &&
                                                        !editing
                                                    ) {
                                                        setActive({
                                                            row: index,
                                                            col: c,
                                                        });
                                                    }
                                                }}
                                                onDoubleClick={() =>
                                                    startEdit({
                                                        row: index,
                                                        col: c,
                                                    })
                                                }
                                                className={cn(
                                                    'relative h-8 overflow-hidden border-r border-b px-2 align-middle whitespace-nowrap group-hover/row:bg-accent',
                                                    base,
                                                    highlighted &&
                                                        'bg-[color-mix(in_oklab,var(--brand)_18%,var(--background))]',
                                                    leftOffsets[id] !==
                                                        undefined && 'z-10',
                                                    id === lastPinned &&
                                                        'border-r-2 border-r-brand/40',
                                                    isActiveCell &&
                                                        'outline-2 -outline-offset-2 outline-primary',
                                                    isEditable(column) &&
                                                        'cursor-cell',
                                                    column.className,
                                                )}
                                            >
                                                {isEditing && editing ? (
                                                    <CellEditor
                                                        column={column}
                                                        initial={
                                                            editing.initial
                                                        }
                                                        onCommit={(v, move) =>
                                                            void commitEdit(
                                                                v,
                                                                move,
                                                            )
                                                        }
                                                        onCancel={cancelEdit}
                                                    />
                                                ) : column.type === 'bool' ? (
                                                    <Checkbox
                                                        checked={value === true}
                                                        disabled={
                                                            !isEditable(column)
                                                        }
                                                        onCheckedChange={() =>
                                                            startEdit({
                                                                row: index,
                                                                col: c,
                                                            })
                                                        }
                                                        aria-label={
                                                            column.title
                                                        }
                                                    />
                                                ) : (
                                                    <div className="truncate">
                                                        {column.render
                                                            ? column.render(row)
                                                            : display(
                                                                  column,
                                                                  value,
                                                              )}
                                                    </div>
                                                )}
                                            </td>
                                        );
                                    })}
                                    {rowActions && (
                                        <td
                                            className={cn(
                                                'border-b px-1 group-hover/row:bg-accent',
                                                base,
                                            )}
                                        >
                                            {rowActions(row)}
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                        {navRows.length === 0 && items.length === 0 && (
                            <tr>
                                <td
                                    colSpan={colCount}
                                    className="px-3 py-10 text-center text-muted-foreground"
                                >
                                    {emptyText}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <p className="text-xs text-muted-foreground">
                {canEdit
                    ? 'Zelle anklicken, dann tippen oder Enter · Enter/Tab speichert und springt weiter · Esc bricht ab · Umschalt+Pfeil markiert · Ctrl+C/Ctrl+V (auch aus Excel) · Entf leert · Ctrl+Z/Ctrl+Y · '
                    : 'Pfeiltasten wählen Zellen · Ctrl+C kopiert · '}
                Spaltenkopf ziehen = verschieben, Rand ziehen = Breite,
                Doppelklick = automatisch.
            </p>
        </div>
    );
}

function CellEditor<T>({
    column,
    initial,
    onCommit,
    onCancel,
}: {
    column: GridColumn<T>;
    initial: string;
    onCommit: (value: string, move?: 'down' | 'right' | 'left') => void;
    onCancel: () => void;
}) {
    const [value, setValue] = useState(initial);
    const done = useRef(false);
    const commit = (move?: 'down' | 'right' | 'left', next = value) => {
        if (!done.current) {
            done.current = true;
            onCommit(next, move);
            // Bei ungültigem Wert bleibt der Editor offen
            requestAnimationFrame(() => {
                done.current = false;
            });
        }
    };
    const onKeyDown = (
        e: KeyboardEvent<HTMLInputElement | HTMLSelectElement>,
    ) => {
        e.stopPropagation();
        if (e.key === 'Escape') {
            e.preventDefault();
            done.current = true;
            onCancel();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            commit('down');
        } else if (e.key === 'Tab') {
            e.preventDefault();
            commit(e.shiftKey ? 'left' : 'right');
        }
    };
    const cls =
        'absolute inset-0 h-full w-full border-0 bg-background px-2 text-sm outline-2 -outline-offset-2 outline-primary';

    if (column.type === 'multi') {
        return (
            <MultiEditor
                column={column}
                initial={initial}
                onCommit={(v, move) => commit(move, v)}
                onCancel={() => {
                    done.current = true;
                    onCancel();
                }}
            />
        );
    }

    if (column.type === 'date') {
        return (
            <DateEditor
                column={column}
                initial={initial}
                onCommit={(v, move) => commit(move, v)}
                onCancel={() => {
                    done.current = true;
                    onCancel();
                }}
            />
        );
    }

    if (column.type === 'select') {
        return (
            <select
                autoFocus
                className={cls}
                value={value}
                onChange={(e) => {
                    setValue(e.target.value);
                    commit(undefined, e.target.value);
                }}
                onBlur={() => !done.current && onCancel()}
                onKeyDown={onKeyDown}
                aria-label={column.title}
            >
                {!column.required && <option value="">–</option>}
                {Object.entries(column.options ?? {}).map(([key, label]) => (
                    <option key={key} value={key}>
                        {label}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <input
            autoFocus
            className={cls}
            type={
                column.type === 'email'
                    ? 'email'
                    : column.type === 'tel'
                      ? 'tel'
                      : 'text'
            }
            value={value}
            onChange={(e) => setValue(e.target.value)}
            onBlur={() => commit()}
            onKeyDown={onKeyDown}
            onFocus={(e) => {
                // Beim Lostippen Cursor ans Ende, sonst alles markieren
                if (initial.length === 1) {
                    const end = e.currentTarget.value.length;
                    e.currentTarget.setSelectionRange?.(end, end);
                } else {
                    e.currentTarget.select();
                }
            }}
            aria-label={column.title}
        />
    );
}

/**
 * Editor für Mehrfachauswahl (#75): Liste mit Häkchen unter der Zelle (Portal, nicht abgeschnitten).
 * Pfeiltasten wählen die Zeile, Leertaste hakt an/ab, Enter übernimmt, Tab übernimmt und springt, Esc bricht ab.
 */
function MultiEditor<T>({
    column,
    initial,
    onCommit,
    onCancel,
}: {
    column: GridColumn<T>;
    initial: string;
    onCommit: (value: string, move?: 'down' | 'right' | 'left') => void;
    onCancel: () => void;
}) {
    const options = Object.entries(column.options ?? {});
    const sep = column.separator ?? ';';
    const [chosen, setChosen] = useState<string[]>(() =>
        splitMulti(column, initial).filter((v) => v in (column.options ?? {})),
    );
    const [focus, setFocus] = useState(0);
    const anchor = useRef<HTMLSpanElement>(null);
    const list = useRef<HTMLDivElement>(null);
    const value = () =>
        options
            .map(([key]) => key)
            .filter((key) => chosen.includes(key))
            .join(`${sep} `);
    const toggle = (key: string) =>
        setChosen((c) =>
            c.includes(key) ? c.filter((k) => k !== key) : [...c, key],
        );

    useEffect(() => {
        list.current?.focus();
    }, []);

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        e.stopPropagation();
        if (e.key === 'Escape') {
            e.preventDefault();
            onCancel();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            onCommit(value(), 'down');
        } else if (e.key === 'Tab') {
            e.preventDefault();
            onCommit(value(), e.shiftKey ? 'left' : 'right');
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            setFocus(
                (f) =>
                    (f + (e.key === 'ArrowDown' ? 1 : -1) + options.length) %
                    Math.max(1, options.length),
            );
        } else if (e.key === ' ') {
            e.preventDefault();
            const key = options[focus]?.[0];
            if (key !== undefined) {
                toggle(key);
            }
        }
    };

    return (
        <>
            <span
                ref={anchor}
                className="absolute inset-0 truncate px-2 py-1.5 text-sm outline-2 -outline-offset-2 outline-primary"
            >
                {value()}
            </span>
            <Popover
                anchor={anchor}
                open
                onClose={() => onCommit(value())}
                onEscape={onCancel}
                width={280}
                height={Math.min(380, 96 + options.length * 34)}
                resizable
            >
                <div
                    ref={list}
                    tabIndex={0}
                    role="listbox"
                    aria-multiselectable
                    aria-label={column.title}
                    onKeyDown={onKeyDown}
                    className="flex min-h-0 flex-1 flex-col gap-2 outline-none"
                >
                    <div className="grid min-h-0 flex-1 content-start gap-0.5 overflow-y-auto">
                        {options.map(([key, label], i) => (
                            <label
                                key={key}
                                role="option"
                                aria-selected={chosen.includes(key)}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 text-sm',
                                    i === focus && 'bg-accent',
                                )}
                                onMouseEnter={() => setFocus(i)}
                            >
                                <input
                                    type="checkbox"
                                    tabIndex={-1}
                                    checked={chosen.includes(key)}
                                    onChange={() => toggle(key)}
                                />
                                <OptionLabel
                                    label={label}
                                    color={column.colors?.[key]}
                                />
                            </label>
                        ))}
                    </div>
                    <div className="flex justify-between gap-2 border-t pt-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={onCancel}
                        >
                            Abbrechen
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => onCommit(value(), 'down')}
                        >
                            Übernehmen
                        </Button>
                    </div>
                </div>
            </Popover>
        </>
    );
}

/**
 * Editor für Datumszellen (#76): Kalender unter der Zelle, oben das Datum zum Eintippen (TT.MM.JJJJ).
 * Im Kalender wählen Pfeiltasten den Tag, Bild auf/ab den Monat, Enter oder Klick übernimmt, Esc bricht ab.
 */
function DateEditor<T>({
    column,
    initial,
    onCommit,
    onCancel,
}: {
    column: GridColumn<T>;
    initial: string;
    onCommit: (value: string, move?: 'down' | 'right' | 'left') => void;
    onCancel: () => void;
}) {
    const iso = /^\d{4}-\d{2}-\d{2}$/.test(initial) ? initial : '';
    const [typed, setTyped] = useState(iso ? display(column, iso) : initial);
    const anchor = useRef<HTMLInputElement>(null);
    const stop = (e: KeyboardEvent<HTMLElement>) => e.stopPropagation();

    return (
        <>
            <input
                ref={anchor}
                className="absolute inset-0 h-full w-full border-0 bg-background px-2 text-sm outline-2 -outline-offset-2 outline-primary"
                value={typed}
                placeholder="TT.MM.JJJJ"
                aria-label={column.title}
                onChange={(e) => setTyped(e.target.value)}
                onKeyDown={(e) => {
                    e.stopPropagation();
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        onCommit(typed, 'down');
                    } else if (e.key === 'Tab') {
                        e.preventDefault();
                        onCommit(typed, e.shiftKey ? 'left' : 'right');
                    } else if (e.key === 'Escape') {
                        e.preventDefault();
                        onCancel();
                    }
                }}
            />
            <Popover
                anchor={anchor}
                open
                onClose={() => onCommit(typed)}
                onEscape={onCancel}
                width={264}
            >
                <div className="grid gap-2" onKeyDown={stop}>
                    <Calendar
                        value={iso}
                        onChange={(v) => onCommit(v, 'down')}
                    />
                    <div className="flex justify-between gap-2 border-t pt-2">
                        {!column.required ? (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => onCommit('', 'down')}
                            >
                                Leeren
                            </Button>
                        ) : (
                            <span />
                        )}
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                onCommit(isoDate(new Date()), 'down')
                            }
                        >
                            Heute
                        </Button>
                    </div>
                </div>
            </Popover>
        </>
    );
}

/** Sortierung in bis zu drei Stufen (#76): Spalte und Richtung je Stufe. */
function SortMenu<T>({
    columns,
    sorting,
    onChange,
}: {
    columns: GridColumn<T>[];
    sorting: SortRule[];
    onChange: (next: SortRule[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const anchor = useRef<HTMLButtonElement>(null);
    const field =
        'h-8 rounded-md border border-input bg-background px-2 text-sm';
    const setLevel = (level: number, rule: SortRule | null) => {
        const next = [...sorting];
        if (rule === null) {
            next.splice(level);
        } else {
            next[level] = rule;
        }
        // Keine Spalte doppelt, keine Lücken
        onChange(
            next
                .filter(
                    (r, i) => r && next.findIndex((x) => x?.id === r.id) === i,
                )
                .slice(0, MAX_SORT),
        );
    };

    return (
        <>
            <Button
                ref={anchor}
                variant="outline"
                size="sm"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
            >
                <ArrowUpDown />{' '}
                {sorting.length > 0
                    ? `Sortiert (${sorting.length})`
                    : 'Sortieren'}
                <ChevronDown className="opacity-60" />
            </Button>
            <Popover
                anchor={anchor}
                open={open}
                onClose={() => setOpen(false)}
                width={380}
            >
                <div className="grid gap-2">
                    <div className="font-medium">Sortieren</div>
                    {Array.from({ length: MAX_SORT }, (_, level) => {
                        const rule = sorting[level];
                        const disabled = level > sorting.length;

                        return (
                            <div
                                key={level}
                                className="grid grid-cols-[1.5rem_minmax(0,1fr)_8rem] items-center gap-2"
                            >
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {level + 1}.
                                </span>
                                <select
                                    className={field}
                                    disabled={disabled}
                                    value={rule?.id ?? ''}
                                    aria-label={`Stufe ${level + 1}: Spalte`}
                                    onChange={(e) =>
                                        setLevel(
                                            level,
                                            e.target.value === ''
                                                ? null
                                                : {
                                                      id: e.target.value,
                                                      desc: rule?.desc ?? false,
                                                  },
                                        )
                                    }
                                >
                                    <option value="">–</option>
                                    {columns
                                        .filter(
                                            (c) =>
                                                c.id === rule?.id ||
                                                !sorting.some(
                                                    (s) => s.id === c.id,
                                                ),
                                        )
                                        .map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.title}
                                            </option>
                                        ))}
                                </select>
                                <select
                                    className={field}
                                    disabled={!rule}
                                    value={rule?.desc ? 'desc' : 'asc'}
                                    aria-label={`Stufe ${level + 1}: Richtung`}
                                    onChange={(e) =>
                                        rule &&
                                        setLevel(level, {
                                            id: rule.id,
                                            desc: e.target.value === 'desc',
                                        })
                                    }
                                >
                                    <option value="asc">aufsteigend</option>
                                    <option value="desc">absteigend</option>
                                </select>
                            </div>
                        );
                    })}
                    <p className="text-xs text-muted-foreground">
                        Auch im Spaltenkopf: Klick sortiert, Umschalt+Klick fügt
                        eine Stufe hinzu.
                    </p>
                    <div className="flex justify-between gap-2 pt-1">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            disabled={sorting.length === 0}
                            onClick={() => onChange([])}
                        >
                            Sortierung entfernen
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setOpen(false)}
                        >
                            Fertig
                        </Button>
                    </div>
                </div>
            </Popover>
        </>
    );
}

/** Eintrag im Kontextmenü der Tabelle (#78). */
export type GridMenuItem = {
    label?: string;
    heading?: string;
    separator?: boolean;
    icon?: ReactNode;
    shortcut?: string;
    disabled?: boolean;
    destructive?: boolean;
    onSelect?: () => void;
};

/**
 * Kontextmenü der Tabelle (Rechtsklick, Menütaste oder Umschalt+F10): Pfeiltasten wählen, Enter führt aus,
 * Esc schliesst. Passt es nicht nach unten bzw. rechts, öffnet es nach oben bzw. links.
 */
function GridMenu({
    x,
    y,
    items,
    onClose,
}: {
    x: number;
    y: number;
    items: GridMenuItem[];
    onClose: () => void;
}) {
    const panel = useRef<HTMLDivElement>(null);
    const [pos, setPos] = useState({ left: x, top: y, maxHeight: 0 });

    useLayoutEffect(() => {
        const el = panel.current;
        if (!el) {
            return;
        }
        const w = el.offsetWidth;
        const h = el.scrollHeight;
        const left = x + w > window.innerWidth - 8 ? Math.max(8, x - w) : x;
        const below = window.innerHeight - y - 8;
        const top =
            h <= below
                ? y
                : Math.max(8, y - h > 8 ? y - h : window.innerHeight - h - 8);
        setPos({ left, top, maxHeight: window.innerHeight - 16 });
        el.querySelector<HTMLButtonElement>(
            '[role=menuitem]:not(:disabled)',
        )?.focus({ preventScroll: true });
    }, [x, y]);

    useEffect(() => {
        const onDown = (e: MouseEvent) => {
            if (!panel.current?.contains(e.target as Node)) {
                onClose();
            }
        };
        const onScroll = (e: Event) => {
            if (!panel.current?.contains(e.target as Node)) {
                onClose();
            }
        };
        document.addEventListener('mousedown', onDown);
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onClose);

        return () => {
            document.removeEventListener('mousedown', onDown);
            window.removeEventListener('scroll', onScroll, true);
            window.removeEventListener('resize', onClose);
        };
    }, [onClose]);

    const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        e.stopPropagation();
        const buttons = Array.from(
            panel.current?.querySelectorAll<HTMLButtonElement>(
                '[role=menuitem]:not(:disabled)',
            ) ?? [],
        );
        const i = buttons.indexOf(document.activeElement as HTMLButtonElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            buttons[
                (i + (e.key === 'ArrowDown' ? 1 : -1) + buttons.length) %
                    buttons.length
            ]?.focus();
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault();
            buttons[e.key === 'Home' ? 0 : buttons.length - 1]?.focus();
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            e.preventDefault();
            onClose();
        }
    };

    // Doppelte und abschliessende Trennlinien entfernen
    const clean = items.filter(
        (item, i) =>
            !item.separator ||
            (i > 0 && i < items.length - 1 && !items[i - 1].separator),
    );

    return createPortal(
        <div
            ref={panel}
            role="menu"
            aria-label="Kontextmenü"
            onKeyDown={onKeyDown}
            onContextMenu={(e) => e.preventDefault()}
            className="fixed z-50 min-w-60 overflow-y-auto rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
            style={{
                left: pos.left,
                top: pos.top,
                maxHeight: pos.maxHeight || undefined,
            }}
        >
            {clean.map((item, i) => {
                if (item.separator) {
                    return (
                        <div
                            key={i}
                            role="separator"
                            className="my-1 h-px bg-border"
                        />
                    );
                }
                if (item.heading) {
                    return (
                        <div
                            key={i}
                            className="px-2 pt-1.5 pb-0.5 text-xs font-medium text-muted-foreground"
                        >
                            {item.heading}
                        </div>
                    );
                }

                return (
                    <button
                        key={i}
                        type="button"
                        role="menuitem"
                        disabled={item.disabled}
                        onClick={() => {
                            onClose();
                            item.onSelect?.();
                        }}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left outline-none hover:bg-accent focus-visible:bg-accent disabled:pointer-events-none disabled:opacity-40 [&_svg]:size-4 [&_svg]:shrink-0',
                            item.destructive && 'text-destructive',
                        )}
                    >
                        <span className="flex w-4 justify-center text-muted-foreground">
                            {item.icon}
                        </span>
                        <span className="flex-1 truncate">{item.label}</span>
                        {item.shortcut && (
                            <span className="text-xs text-muted-foreground">
                                {item.shortcut}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>,
        document.body,
    );
}
