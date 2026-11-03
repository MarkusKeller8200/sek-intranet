import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useRef,
    useState,
} from 'react';
import type { ReactNode, RefObject } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

const GAP = 4;
const MARGIN = 8;

/**
 * Einfaches Popover über einem Auslöser (per Portal, damit es nicht vom
 * Scrollbereich der Tabelle abgeschnitten wird). Schliesst bei Klick ausserhalb und Esc.
 * Passt es unten nicht auf den Bildschirm, erscheint es oberhalb (#77); sonst auf der Seite mit mehr Platz,
 * begrenzt auf die verfügbare Höhe. Mit «resizable» lässt es sich an der Ecke rechts unten grösser ziehen.
 */
export default function Popover({
    anchor,
    open,
    onClose,
    onEscape,
    children,
    width = 280,
    height,
    resizable = false,
}: {
    anchor: RefObject<HTMLElement | null>;
    open: boolean;
    onClose: () => void;
    /** Esc anders als Klick daneben behandeln (z. B. Abbrechen statt Übernehmen) */
    onEscape?: () => void;
    children: ReactNode;
    width?: number;
    /** Anfangshöhe (nur mit «resizable»); der Inhalt füllt sie aus */
    height?: number;
    resizable?: boolean;
}) {
    const panel = useRef<HTMLDivElement>(null);
    const [position, setPosition] = useState<{
        top: number;
        left: number;
        maxHeight?: number;
    }>({ top: -9999, left: 0 });

    const place = useCallback(() => {
        if (!anchor.current || !panel.current) {
            return;
        }
        const rect = anchor.current.getBoundingClientRect();
        const h = panel.current.offsetHeight;
        const w = panel.current.offsetWidth;
        const left = Math.min(
            Math.max(MARGIN, rect.left),
            window.innerWidth - w - MARGIN,
        );
        const below = window.innerHeight - rect.bottom - GAP - MARGIN;
        const above = rect.top - GAP - MARGIN;
        if (h <= below || below >= above) {
            setPosition({
                top: rect.bottom + GAP,
                left,
                maxHeight: h <= below ? undefined : below,
            });
        } else {
            const fit = Math.min(h, above);
            setPosition({
                top: rect.top - GAP - fit,
                left,
                maxHeight: h <= above ? undefined : above,
            });
        }
    }, [anchor]);

    useLayoutEffect(() => {
        if (open) {
            place();
        }
    }, [open, place, width, height]);

    // Inhalt oder Grösse ändert sich (z. B. Kalender wechselt Ansicht, Ecke gezogen): neu ausrichten
    useEffect(() => {
        if (!open || !panel.current || typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver(() => place());
        observer.observe(panel.current);

        return () => observer.disconnect();
    }, [open, place]);

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
            className={cn(
                'fixed z-50 rounded-md border bg-popover p-3 text-sm text-popover-foreground shadow-md',
                resizable
                    ? 'flex resize flex-col overflow-hidden'
                    : 'overflow-y-auto',
            )}
            style={{
                top: position.top,
                left: position.left,
                width,
                height: resizable ? height : undefined,
                maxHeight: position.maxHeight,
                minWidth: resizable ? 200 : undefined,
                minHeight: resizable ? 160 : undefined,
            }}
        >
            {children}
        </div>,
        document.body,
    );
}
