import { usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';
import { storedSidebarWidth } from '@/components/sidebar-splitter';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="flex min-h-screen w-full flex-col">{children}</div>
        );
    }

    // Breite aus dem Splitter der Seitenleiste (pro Browser gemerkt)
    const width = typeof window === 'undefined' ? null : storedSidebarWidth();

    return (
        <SidebarProvider
            defaultOpen={isOpen}
            style={
                width
                    ? ({ '--sidebar-width': `${width}px` } as CSSProperties)
                    : undefined
            }
        >
            {children}
        </SidebarProvider>
    );
}
