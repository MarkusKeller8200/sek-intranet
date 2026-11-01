import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent, MouseEvent, ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

export type ContextAction = { label: string; icon?: ReactNode; onSelect: () => void; destructive?: boolean; separator?: boolean };

/**
 * Kontextmenü: öffnet mit Rechtsklick, langem Drücken (Touch), Shift+F10 oder der Menütaste.
 * Jede Aktion muss es zusätzlich sichtbar geben (Knopf «…»), weil viele das Kontextmenü nicht kennen.
 * GUI-Prototyp «Kontextmenü».
 */
function ContextMenu({ actions, children, label }: { actions: ContextAction[]; children: ReactNode; label: string }) {
    const [pos, setPos] = useState<{ x: number; y: number } | null>(null);
    const menu = useRef<HTMLDivElement>(null);
    const area = useRef<HTMLDivElement>(null);
    const touch = useRef<number | null>(null);

    const open = (x: number, y: number) => setPos({ x: Math.min(x, window.innerWidth - 220), y: Math.min(y, window.innerHeight - actions.length * 36 - 16) });
    const close = () => {
        setPos(null);
        area.current?.focus();
    };

    useEffect(() => {
        if (!pos) {
            return;
        }
        menu.current?.querySelector<HTMLButtonElement>('[role=menuitem]')?.focus();
        const onDown = (e: globalThis.MouseEvent) => {
            if (!menu.current?.contains(e.target as Node)) {
                setPos(null);
            }
        };
        document.addEventListener('mousedown', onDown);

        return () => document.removeEventListener('mousedown', onDown);
    }, [pos]);

    const onMenuKey = (e: KeyboardEvent) => {
        const items = Array.from(menu.current?.querySelectorAll<HTMLButtonElement>('[role=menuitem]') ?? []);
        const i = items.indexOf(document.activeElement as HTMLButtonElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length]?.focus();
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            e.preventDefault();
            close();
        }
    };

    return (
        <>
            <div
                ref={area}
                tabIndex={0}
                aria-label={label}
                aria-haspopup="menu"
                onContextMenu={(e: MouseEvent) => {
                    e.preventDefault();
                    open(e.clientX, e.clientY);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) {
                        e.preventDefault();
                        const r = e.currentTarget.getBoundingClientRect();
                        open(r.left + 16, r.top + 16);
                    }
                }}
                onTouchStart={(e) => {
                    const t = e.touches[0];
                    touch.current = window.setTimeout(() => open(t.clientX, t.clientY), 550);
                }}
                onTouchEnd={() => touch.current && window.clearTimeout(touch.current)}
                onTouchMove={() => touch.current && window.clearTimeout(touch.current)}
                className="rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                {children}
            </div>
            {pos &&
                createPortal(
                    <div
                        ref={menu}
                        role="menu"
                        aria-label={label}
                        onKeyDown={onMenuKey}
                        style={{ top: pos.y, left: pos.x }}
                        className="fixed z-50 min-w-48 rounded-md border bg-popover p-1 text-sm text-popover-foreground shadow-md"
                    >
                        {actions.map((a) => (
                            <div key={a.label}>
                                {a.separator && <div className="-mx-1 my-1 h-px bg-border" role="separator" />}
                                <button
                                    type="button"
                                    role="menuitem"
                                    onClick={() => {
                                        setPos(null);
                                        a.onSelect();
                                    }}
                                    className={cn(
                                        'flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-accent focus:bg-accent [&_svg]:size-4',
                                        a.destructive && 'text-destructive-foreground',
                                    )}
                                >
                                    {a.icon}
                                    <span>{a.label}</span>
                                </button>
                            </div>
                        ))}
                    </div>,
                    document.body,
                )}
        </>
    );
}

export { ContextMenu };
