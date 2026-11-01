import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';

/**
 * Vorschaukarte (Hover Card): zeigt beim Überfahren oder beim Fokus (Tastatur) eine kleine Vorschau,
 * z. B. einer Person. Nur Zusatzinformation – der Link führt immer auch zur ganzen Seite.
 * GUI-Prototyp «Vorschaukarte».
 */
function HoverCard({ trigger, children, width = 280, delay = 300 }: { trigger: ReactNode; children: ReactNode; width?: number; delay?: number }) {
    const anchor = useRef<HTMLSpanElement>(null);
    const timer = useRef<number | null>(null);
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState({ top: 0, left: 0 });
    const id = useId();

    const show = () => {
        if (timer.current) window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setOpen(true), delay);
    };
    const hide = () => {
        if (timer.current) window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => setOpen(false), 150);
    };

    useLayoutEffect(() => {
        if (open && anchor.current) {
            const r = anchor.current.getBoundingClientRect();
            setPos({ top: r.bottom + 6, left: Math.min(Math.max(8, r.left), window.innerWidth - width - 8) });
        }
    }, [open, width]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, []);

    return (
        <>
            <span ref={anchor} onMouseEnter={show} onMouseLeave={hide} onFocus={show} onBlur={hide} aria-describedby={open ? id : undefined} className="inline-flex">
                {trigger}
            </span>
            {open &&
                createPortal(
                    <div
                        id={id}
                        role="tooltip"
                        onMouseEnter={show}
                        onMouseLeave={hide}
                        style={{ top: pos.top, left: pos.left, width }}
                        className="fixed z-50 rounded-md border bg-popover p-3 text-sm text-popover-foreground shadow-md"
                    >
                        {children}
                    </div>,
                    document.body,
                )}
        </>
    );
}

export { HoverCard };
