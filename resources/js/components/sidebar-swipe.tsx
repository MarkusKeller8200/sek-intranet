import { useEffect } from 'react';
import { useSidebar } from '@/components/ui/sidebar';

const MIN_DISTANCE = 60; // px waagrecht
const MAX_TIME = 700; // ms

/** Liegt der Startpunkt in etwas, das selbst waagrecht scrollt oder bedient wird (Tabelle, Eingabe)? */
function blocksSwipe(target: EventTarget | null): boolean {
    let el = target instanceof Element ? target : null;
    while (el && el !== document.body) {
        if (el.matches('input, textarea, select, [contenteditable="true"], [data-no-swipe]')) {
            return true;
        }
        if (el instanceof HTMLElement && el.scrollWidth > el.clientWidth + 1) {
            const overflow = getComputedStyle(el).overflowX;
            if (overflow === 'auto' || overflow === 'scroll') {
                return true;
            }
        }
        el = el.parentElement;
    }

    return false;
}

/**
 * Mobil: Wischen von links nach rechts blendet das Menü ein, von rechts nach links wieder aus.
 * Waagrecht scrollende Bereiche (z. B. Tabellen) und Eingabefelder sind ausgenommen.
 */
export default function SidebarSwipe() {
    const { isMobile, openMobile, setOpenMobile } = useSidebar();

    useEffect(() => {
        if (!isMobile) {
            return;
        }
        let start: { x: number; y: number; t: number } | null = null;

        const onStart = (e: TouchEvent) => {
            if (e.touches.length !== 1 || blocksSwipe(e.target)) {
                start = null;

                return;
            }
            const touch = e.touches[0];
            start = { x: touch.clientX, y: touch.clientY, t: Date.now() };
        };

        const onEnd = (e: TouchEvent) => {
            if (!start) {
                return;
            }
            const touch = e.changedTouches[0];
            const dx = touch.clientX - start.x;
            const dy = touch.clientY - start.y;
            const quick = Date.now() - start.t < MAX_TIME;
            start = null;
            // Deutlich waagrecht, nicht beim senkrechten Scrollen
            if (!quick || Math.abs(dx) < MIN_DISTANCE || Math.abs(dy) > Math.abs(dx) / 2) {
                return;
            }
            if (dx > 0 && !openMobile) {
                setOpenMobile(true);
            } else if (dx < 0 && openMobile) {
                setOpenMobile(false);
            }
        };

        document.addEventListener('touchstart', onStart, { passive: true });
        document.addEventListener('touchend', onEnd, { passive: true });

        return () => {
            document.removeEventListener('touchstart', onStart);
            document.removeEventListener('touchend', onEnd);
        };
    }, [isMobile, openMobile, setOpenMobile]);

    return null;
}
