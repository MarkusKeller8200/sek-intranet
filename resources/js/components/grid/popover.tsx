import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import { createPortal } from 'react-dom';

/**
 * Einfaches Popover über einem Auslöser (per Portal, damit es nicht vom
 * Scrollbereich der Tabelle abgeschnitten wird). Schliesst bei Klick ausserhalb und Esc.
 */
export default function Popover({
    anchor,
    open,
    onClose,
    children,
    width = 280,
    onEscape,
}: {
    anchor: RefObject<HTMLElement | null>;
    open: boolean;
    onClose: () => void;
    /** Esc anders als Klick daneben behandeln (z. B. Abbrechen statt Übernehmen) */
    onEscape?: () => void;
    children: ReactNode;
    width?: number;
}) {
    const panel = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState({ top: 0, left: 0 });

    useLayoutEffect(() => {
        if (!open || !anchor.current) {
            return;
        }
        const rect = anchor.current.getBoundingClientRect();
        const left = Math.min(
            Math.max(8, rect.left),
            window.innerWidth - width - 8,
        );
        setPosition({ top: rect.bottom + 4, left });
    }, [open, anchor, width]);

    useEffect(() => {
        if (!open) {
            return;
        }
        const onDown = (e: MouseEvent) => {
            const target = e.target as Node;
            if (
                !panel.current?.contains(target) &&
                !anchor.current?.contains(target)
            ) {
                onClose();
            }
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.stopPropagation();
                (onEscape ?? onClose)();
                anchor.current?.focus();
            }
        };
        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey, true);

        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey, true);
        };
    }, [open, onClose, onEscape, anchor]);

    if (!open || typeof document === 'undefined') {
        return null;
    }

    return createPortal(
        <div
            ref={panel}
            role="dialog"
            className="fixed z-50 rounded-md border bg-popover p-3 text-sm text-popover-foreground shadow-md"
            style={{ top: position.top, left: position.left, width }}
        >
            {children}
        </div>,
        document.body,
    );
}
