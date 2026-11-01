import { useRef, useState } from 'react';
import type { ReactNode } from 'react';
import PopoverPanel from '@/components/grid/popover';
import { Button } from '@/components/ui/button';

/**
 * Popover: kleines Fenster an einem Auslöser (Filter, Hilfe, Auswahl). Schliesst bei Klick ausserhalb
 * und mit Esc; der Fokus geht zurück zum Auslöser. GUI-Prototyp «Popover».
 *
 * - `Popover`: gesteuert über anchor/open/onClose (für eigene Bausteine wie Combobox)
 * - `PopoverButton`: Knopf mit Popover in einem
 */
export { PopoverPanel as Popover };

function PopoverButton({
    label,
    children,
    width = 280,
    variant = 'outline',
    size = 'default',
    title,
}: {
    label: ReactNode;
    children: ReactNode | ((close: () => void) => ReactNode);
    width?: number;
    variant?: 'outline' | 'ghost' | 'secondary' | 'default';
    size?: 'default' | 'sm' | 'icon';
    title?: string;
}) {
    const anchor = useRef<HTMLButtonElement>(null);
    const [open, setOpen] = useState(false);
    const close = () => setOpen(false);

    return (
        <>
            <Button
                ref={anchor}
                type="button"
                variant={variant}
                size={size}
                title={title}
                aria-expanded={open}
                aria-haspopup="dialog"
                onClick={() => setOpen((o) => !o)}
            >
                {label}
            </Button>
            <PopoverPanel anchor={anchor} open={open} onClose={close} width={width}>
                <div className="grid gap-2">
                    {typeof children === 'function' ? children(close) : children}
                </div>
            </PopoverPanel>
        </>
    );
}

export { PopoverButton };
