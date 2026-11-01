import { closestCorners, DndContext, DragOverlay, useDraggable, useDroppable } from '@dnd-kit/core';
import type { DragEndEvent, DragStartEvent } from '@dnd-kit/core';
import { GripVertical } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { dndAnnouncements, dndInstructions, useDndSensors } from '@/components/ui/sortable-list';
import { cn } from '@/lib/utils';

export type BoardColumn = { id: string; title: string; hint?: string };
export type BoardCard = { id: string; title: string; column: string; meta?: ReactNode };

type Handle = {
    ref?: (el: HTMLElement | null) => void;
    props?: Record<string, unknown>;
};

function CardView({
    card,
    columns,
    onMove,
    handle,
    nodeRef,
    dragging = false,
    overlay = false,
}: {
    card: BoardCard;
    columns: BoardColumn[];
    onMove?: (card: string, column: string) => void;
    handle?: Handle;
    nodeRef?: (el: HTMLElement | null) => void;
    dragging?: boolean;
    overlay?: boolean;
}) {
    return (
        <li
            ref={nodeRef}
            className={cn('grid gap-1.5 rounded-md border bg-background p-2 text-sm shadow-xs', dragging && 'opacity-40', overlay && 'list-none shadow-lg ring-2 ring-primary/40')}
        >
            <div className="flex items-start gap-1.5">
                <button
                    ref={handle?.ref}
                    type="button"
                    {...(handle?.props ?? {})}
                    aria-label={`${card.title} verschieben`}
                    className="cursor-grab touch-none rounded p-0.5 text-muted-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <GripVertical className="size-4" />
                </button>
                <span className="min-w-0 flex-1 font-medium" translate="no">
                    {card.title}
                </span>
            </div>
            {card.meta && <div className="pl-6 text-xs text-muted-foreground">{card.meta}</div>}
            {onMove && (
                <label className="flex items-center gap-1.5 pl-6 text-xs text-muted-foreground">
                    <span>Nach</span>
                    <select
                        value={card.column}
                        onChange={(e) => onMove(card.id, e.target.value)}
                        className="h-6 rounded border border-input bg-transparent px-1 text-xs"
                        aria-label={`${card.title} verschieben nach`}
                    >
                        {columns.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.title}
                            </option>
                        ))}
                    </select>
                </label>
            )}
        </li>
    );
}

function DraggableCard({ card, columns, onMove }: { card: BoardCard; columns: BoardColumn[]; onMove: (card: string, column: string) => void }) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, isDragging } = useDraggable({ id: card.id });

    return (
        <CardView
            card={card}
            columns={columns}
            onMove={onMove}
            nodeRef={setNodeRef}
            dragging={isDragging}
            handle={{ ref: setActivatorNodeRef, props: { ...attributes, ...listeners } }}
        />
    );
}

function Column({ column, children, count }: { column: BoardColumn; children: ReactNode; count: number }) {
    const { setNodeRef, isOver } = useDroppable({ id: column.id });

    return (
        <section
            ref={setNodeRef}
            aria-label={column.title}
            className={cn('grid min-h-40 content-start gap-2 rounded-lg border bg-muted/40 p-2 transition-colors', isOver && 'border-primary bg-primary/5')}
        >
            <header className="flex items-center justify-between px-1">
                <h4 className="text-sm font-semibold">{column.title}</h4>
                <span className="text-xs text-muted-foreground tabular-nums">{count}</span>
            </header>
            {column.hint && <p className="px-1 text-xs text-muted-foreground">{column.hint}</p>}
            <ul className="grid gap-2">{children}</ul>
        </section>
    );
}

/**
 * Tafel mit Spalten: Karten zwischen Spalten ziehen (Zuordnen, Kanban). Jede Karte hat zusätzlich die
 * Auswahl «Nach …» – Verschieben geht also immer auch ohne Ziehen. GUI-Prototyp «Drag & Drop».
 */
function DragBoard({
    columns,
    cards,
    onMove,
}: {
    columns: BoardColumn[];
    cards: BoardCard[];
    onMove: (card: string, column: string) => void;
}) {
    const sensors = useDndSensors();
    const [active, setActive] = useState<string | null>(null);
    const label = (id: string) => cards.find((c) => c.id === id)?.title ?? id;
    const place = (id: string) => columns.find((c) => c.id === id)?.title ?? id;
    const onDragEnd = ({ active: a, over }: DragEndEvent) => {
        setActive(null);
        if (over && columns.some((c) => c.id === over.id)) {
            onMove(String(a.id), String(over.id));
        }
    };
    const activeCard = cards.find((c) => c.id === active);

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={({ active: a }: DragStartEvent) => setActive(String(a.id))}
            onDragEnd={onDragEnd}
            onDragCancel={() => setActive(null)}
            accessibility={{ announcements: dndAnnouncements(label, place), screenReaderInstructions: dndInstructions }}
        >
            <div className="grid gap-3" style={{ gridTemplateColumns: `repeat(auto-fit, minmax(12rem, 1fr))` }}>
                {columns.map((column) => {
                    const own = cards.filter((c) => c.column === column.id);

                    return (
                        <Column key={column.id} column={column} count={own.length}>
                            {own.map((card) => (
                                <DraggableCard key={card.id} card={card} columns={columns} onMove={onMove} />
                            ))}
                        </Column>
                    );
                })}
            </div>
            <DragOverlay>{activeCard ? <CardView card={activeCard} columns={columns} overlay /> : null}</DragOverlay>
        </DndContext>
    );
}

export { DragBoard };
