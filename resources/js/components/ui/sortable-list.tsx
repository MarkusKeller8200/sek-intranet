import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    TouchSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import type { Announcements, DragEndEvent, ScreenReaderInstructions } from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { ArrowDown, ArrowUp, GripVertical } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type SortableItem = { id: string; label: string; hint?: string };

/** Hinweise für Bildschirmleser (Deutsch). */
export const dndInstructions: ScreenReaderInstructions = {
    draggable:
        'Zum Aufnehmen Leertaste drücken. Mit den Pfeiltasten verschieben, mit Leertaste ablegen, mit Esc abbrechen.',
};

export function dndAnnouncements(label: (id: string) => string, place: (id: string) => string): Announcements {
    return {
        onDragStart: ({ active }) => `${label(String(active.id))} aufgenommen.`,
        onDragOver: ({ active, over }) => (over ? `${label(String(active.id))} über ${place(String(over.id))}.` : `${label(String(active.id))} ausserhalb.`),
        onDragEnd: ({ active, over }) => (over ? `${label(String(active.id))} abgelegt bei ${place(String(over.id))}.` : `${label(String(active.id))} abgelegt.`),
        onDragCancel: ({ active }) => `Verschieben von ${label(String(active.id))} abgebrochen.`,
    };
}

/** Sensoren: Maus/Stift (ab 4 px Bewegung), Touch (iPad, kurz halten) und Tastatur. */
export function useDndSensors(withSortableKeys = false) {
    return useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 4 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 150, tolerance: 6 } }),
        useSensor(KeyboardSensor, withSortableKeys ? { coordinateGetter: sortableKeyboardCoordinates } : undefined),
    );
}

function Row({
    item,
    index,
    count,
    onMove,
    render,
}: {
    item: SortableItem;
    index: number;
    count: number;
    onMove: (from: number, to: number) => void;
    render?: (item: SortableItem) => ReactNode;
}) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: item.id });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn('flex items-center gap-2 rounded-md border bg-background px-2 py-1.5 text-sm', isDragging && 'relative z-10 shadow-lg ring-2 ring-primary/40')}
        >
            <button
                ref={setActivatorNodeRef}
                type="button"
                {...attributes}
                {...listeners}
                aria-label={`${item.label} verschieben`}
                className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
            >
                <GripVertical className="size-4" />
            </button>
            <span className="w-6 text-right text-xs text-muted-foreground tabular-nums">{`${index + 1}.`}</span>
            <span className="min-w-0 flex-1">{render ? render(item) : item.label}</span>
            <Button type="button" variant="ghost" size="icon" className="size-7" disabled={index === 0} onClick={() => onMove(index, index - 1)} title="Nach oben">
                <ArrowUp />
                <span className="sr-only">{`${item.label} nach oben`}</span>
            </Button>
            <Button type="button" variant="ghost" size="icon" className="size-7" disabled={index === count - 1} onClick={() => onMove(index, index + 1)} title="Nach unten">
                <ArrowDown />
                <span className="sr-only">{`${item.label} nach unten`}</span>
            </Button>
        </li>
    );
}

/**
 * Liste umsortieren: ziehen am Griff (Maus, Touch) oder mit der Tastatur (Leertaste, Pfeile) sowie
 * mit «Nach oben/unten». Erfüllt WCAG 2.2 «Ziehbewegungen» (2.5.7). GUI-Prototyp «Drag & Drop».
 */
function SortableList({
    items,
    onChange,
    render,
}: {
    items: SortableItem[];
    onChange: (items: SortableItem[]) => void;
    render?: (item: SortableItem) => ReactNode;
}) {
    const sensors = useDndSensors(true);
    const label = (id: string) => items.find((i) => i.id === id)?.label ?? id;
    const place = (id: string) => `Platz ${items.findIndex((i) => i.id === id) + 1}`;
    const move = (from: number, to: number) => onChange(arrayMove(items, from, to));
    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (over && active.id !== over.id) {
            move(items.findIndex((i) => i.id === active.id), items.findIndex((i) => i.id === over.id));
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={onDragEnd}
            accessibility={{ announcements: dndAnnouncements(label, place), screenReaderInstructions: dndInstructions }}
        >
            <SortableContext items={items.map((i) => i.id)} strategy={verticalListSortingStrategy}>
                <ol className="grid gap-1.5">
                    {items.map((item, index) => (
                        <Row key={item.id} item={item} index={index} count={items.length} onMove={move} render={render} />
                    ))}
                </ol>
            </SortableContext>
        </DndContext>
    );
}

export { SortableList };
