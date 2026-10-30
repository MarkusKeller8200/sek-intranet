import type { MouseEvent as ReactMouseEvent } from 'react';
import { useSidebar } from '@/components/ui/sidebar';

export const SIDEBAR_WIDTH_KEY = 'intranet.sidebar.width';
const MIN = 200;
const MAX = 480;

/** Gemerkte Breite der Seitenleiste (px) oder null für die Standardbreite. */
export function storedSidebarWidth(): number | null {
    try {
        const value = Number(window.localStorage.getItem(SIDEBAR_WIDTH_KEY));

        return value >= MIN && value <= MAX ? value : null;
    } catch {
        return null;
    }
}

function apply(width: number | null) {
    const wrapper = document.querySelector<HTMLElement>(
        '[data-slot="sidebar-wrapper"]',
    );
    if (width === null) {
        wrapper?.style.removeProperty('--sidebar-width');
    } else {
        wrapper?.style.setProperty('--sidebar-width', `${width}px`);
    }
    try {
        if (width === null) {
            window.localStorage.removeItem(SIDEBAR_WIDTH_KEY);
        } else {
            window.localStorage.setItem(SIDEBAR_WIDTH_KEY, String(width));
        }
    } catch {
        // Ohne Speicher gilt die Breite nur bis zum Neuladen.
    }
}

/**
 * Splitter am rechten Rand der Seitenleiste: ziehen ändert die Breite,
 * Doppelklick stellt die Standardbreite wieder her, Pfeiltasten in 16-px-Schritten.
 */
export function SidebarSplitter() {
    const { state, isMobile } = useSidebar();

    if (isMobile || state === 'collapsed') {
        return null;
    }

    const start = (e: ReactMouseEvent<HTMLDivElement>) => {
        e.preventDefault();
        // Die Seitenleiste liegt fest am linken Rand: Breite = Mausposition
        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';
        document
            .querySelectorAll<HTMLElement>('[data-slot="sidebar"] > div')
            .forEach((el) => (el.style.transition = 'none'));

        const move = (ev: MouseEvent) =>
            apply(Math.round(Math.min(MAX, Math.max(MIN, ev.clientX))));
        const up = () => {
            document.removeEventListener('mousemove', move);
            document.removeEventListener('mouseup', up);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document
                .querySelectorAll<HTMLElement>('[data-slot="sidebar"] > div')
                .forEach((el) => (el.style.transition = ''));
        };
        document.addEventListener('mousemove', move);
        document.addEventListener('mouseup', up);
    };

    const current = () =>
        document
            .querySelector('[data-slot="sidebar"] > div.fixed')
            ?.getBoundingClientRect().width ?? 256;

    return (
        <div
            role="separator"
            aria-orientation="vertical"
            aria-label="Breite der Seitenleiste"
            aria-valuemin={MIN}
            aria-valuemax={MAX}
            tabIndex={0}
            title="Ziehen = Breite ändern, Doppelklick = Standardbreite"
            onMouseDown={start}
            onDoubleClick={() => apply(null)}
            onKeyDown={(e) => {
                if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    e.preventDefault();
                    const delta = e.key === 'ArrowLeft' ? -16 : 16;
                    apply(
                        Math.min(
                            MAX,
                            Math.max(MIN, Math.round(current() + delta)),
                        ),
                    );
                }
            }}
            className="absolute inset-y-0 -right-1.5 z-30 w-3 cursor-col-resize after:absolute after:inset-y-0 after:left-1/2 after:w-0.5 after:-translate-x-1/2 after:bg-transparent after:transition-colors hover:after:bg-brand focus-visible:outline-none focus-visible:after:bg-brand"
        />
    );
}
