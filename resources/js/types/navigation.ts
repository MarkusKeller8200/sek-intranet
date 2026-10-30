import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/** Modul in der Seitenleiste (config/intranet.php → modules). */
export type ModuleNavItem = {
    key: string;
    title: string;
    icon: string;
    href: string;
    /** Noch nicht umgesetzt – führt auf die Platzhalterseite. */
    planned: boolean;
};

export type ModuleNavGroup = {
    key: string;
    title: string;
    /** Farbe aus dem Farbkonzept (Palette in lib/colors.ts) */
    color: string;
    /** Symbol des Bereichs (lib/module-icons.ts) */
    icon: string;
    items: ModuleNavItem[];
};

/** Geplantes Modul auf der Platzhalterseite. */
export type PlannedModule = {
    key: string;
    title: string;
    icon: string;
    group: string;
    description: string;
    features: string[];
    plugin: string | null;
};
