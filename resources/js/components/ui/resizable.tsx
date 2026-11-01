import { useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Geteilte Ansicht: zwei Bereiche mit verschiebbarer Trennlinie (Maus, Touch, Pfeiltasten; Pos1/Ende
 * für Minimum/Maximum). GUI-Prototyp «Geteilte Ansicht».
 */
function ResizablePanels({
    first,
    second,
    initial = 40,
    min = 20,
    max = 80,
    label = 'Breite der Bereiche',
    className,
    onResize,
}: {
    first: ReactNode;
    second: ReactNode;
    initial?: number;
    min?: number;
    max?: number;
    label?: string;
    className?: string;
    onResize?: (percent: number) => void;
}) {
    const [size, setSize] = useState(initial);
    const box = useRef<HTMLDivElement>(null);
    const set = (v: number) => {
        const next = Math.round(Math.min(max, Math.max(min, v)));
        setSize(next);
        onResize?.(next);
    };
    const onPointerDown = (e: PointerEvent<HTMLDivElement>) => {
        e.currentTarget.setPointerCapture(e.pointerId);
    };
    const onPointerMove = (e: PointerEvent<HTMLDivElement>) => {
        if (!e.currentTarget.hasPointerCapture(e.pointerId) || !box.current) {
            return;
        }
        const r = box.current.getBoundingClientRect();
        set(((e.clientX - r.left) / r.width) * 100);
    };
    const onKeyDown = (e: KeyboardEvent) => {
        const step = e.shiftKey ? 10 : 2;
        const map: Record<string, number> = { ArrowLeft: size - step, ArrowRight: size + step, Home: min, End: max };
        if (e.key in map) {
            e.preventDefault();
            set(map[e.key]);
        }
    };

    return (
        <div ref={box} className={cn('flex min-h-40 overflow-hidden rounded-lg border', className)}>
            <div style={{ width: `${size}%` }} className="min-w-0 overflow-auto">
                {first}
            </div>
            <div
                role="separator"
                tabIndex={0}
                aria-orientation="vertical"
                aria-label={label}
                aria-valuenow={size}
                aria-valuemin={min}
                aria-valuemax={max}
                onPointerDown={onPointerDown}
                onPointerMove={onPointerMove}
                onKeyDown={onKeyDown}
                onDoubleClick={() => set(initial)}
                className="group relative w-1.5 shrink-0 cursor-col-resize touch-none bg-border hover:bg-primary/40 focus-visible:bg-primary focus-visible:outline-none"
            >
                <span className="absolute top-1/2 left-1/2 h-8 w-1 -translate-x-1/2 -translate-y-1/2 rounded bg-muted-foreground/40 group-hover:bg-primary" />
            </div>
            <div className="min-w-0 flex-1 overflow-auto">{second}</div>
        </div>
    );
}

export { ResizablePanels };
