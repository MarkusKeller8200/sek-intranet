import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { moduleIcon } from '@/lib/module-icons';
import { isColorName, palette } from '@/lib/colors';
import { cn } from '@/lib/utils';
import type { ModuleNavGroup } from '@/types';

const STORAGE_KEY = 'intranet.nav.groups';

/** Gemerkter Zustand je Gruppe (nur in diesem Browser). */
function readStored(): Record<string, boolean> {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : {};

        return parsed && typeof parsed === 'object'
            ? (parsed as Record<string, boolean>)
            : {};
    } catch {
        return {};
    }
}

function writeStored(value: Record<string, boolean>) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Ohne Speicher bleibt der Zustand nur bis zum Neuladen.
    }
}

export function NavModules({ groups }: { groups: ModuleNavGroup[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state, isMobile } = useSidebar();
    const [stored, setStored] = useState<Record<string, boolean>>(readStored);

    // In der schmalen Leiste (nur Symbole) gibt es keine Gruppentitel – alles zeigen.
    const iconOnly = state === 'collapsed' && !isMobile;

    const toggle = (key: string, open: boolean) => {
        const next = { ...stored, [key]: open };
        setStored(next);
        writeStored(next);
    };

    return (
        <>
            {groups.map((group) => {
                const containsCurrent = group.items.some((item) =>
                    isCurrentOrParentUrl(item.href),
                );
                // Standard: offen, wenn die Gruppe Umgesetztes enthält
                const defaultOpen = group.items.some((item) => !item.planned);
                // Gemerkte Wahl gilt immer – auch für die Gruppe der aktuellen Seite
                const open =
                    iconOnly ||
                    (stored[group.key] ?? (defaultOpen || containsCurrent));
                const color = isColorName(group.color)
                    ? palette[group.color]
                    : palette.slate;

                return (
                    <Collapsible
                        key={group.key}
                        open={open}
                        onOpenChange={(value: boolean) =>
                            toggle(group.key, value)
                        }
                        className="group/collapsible"
                    >
                        <SidebarGroup className="px-2 py-0">
                            <SidebarGroupLabel asChild>
                                <CollapsibleTrigger
                                    className={cn(
                                        'w-full gap-2 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                        containsCurrent &&
                                            !open &&
                                            'font-semibold text-sidebar-foreground',
                                    )}
                                    title={open ? 'Einklappen' : 'Ausklappen'}
                                >
                                    <span
                                        className={cn(
                                            'size-2 shrink-0 rounded-full',
                                            color.dot,
                                        )}
                                        aria-hidden
                                    />
                                    <span>{group.title}</span>
                                    {!open && (
                                        <span className="ml-1.5 text-sidebar-foreground/50">
                                            {group.items.length}
                                        </span>
                                    )}
                                    <ChevronRight
                                        className={cn(
                                            'ml-auto transition-transform duration-200',
                                            open && 'rotate-90',
                                        )}
                                    />
                                </CollapsibleTrigger>
                            </SidebarGroupLabel>
                            <CollapsibleContent>
                                <SidebarMenu>
                                    {group.items.map((item) => {
                                        const Icon = moduleIcon(item.icon);
                                        const current = isCurrentOrParentUrl(
                                            item.href,
                                        );

                                        return (
                                            <SidebarMenuItem key={item.key}>
                                                <SidebarMenuButton
                                                    asChild
                                                    isActive={current}
                                                    tooltip={{
                                                        children: item.planned
                                                            ? `${item.title} (geplant)`
                                                            : item.title,
                                                    }}
                                                    className={cn(
                                                        'border-l-2 border-transparent',
                                                        current && color.bar,
                                                        item.planned &&
                                                            'text-sidebar-foreground/60',
                                                    )}
                                                >
                                                    <Link
                                                        href={item.href}
                                                        prefetch={!item.planned}
                                                    >
                                                        <Icon
                                                            className={cn(
                                                                !item.planned &&
                                                                    color.icon,
                                                            )}
                                                        />
                                                        <span>
                                                            {item.title}
                                                        </span>
                                                    </Link>
                                                </SidebarMenuButton>
                                                {item.planned && (
                                                    <SidebarMenuBadge className="text-[10px] font-normal text-sidebar-foreground/50">
                                                        geplant
                                                    </SidebarMenuBadge>
                                                )}
                                            </SidebarMenuItem>
                                        );
                                    })}
                                </SidebarMenu>
                            </CollapsibleContent>
                        </SidebarGroup>
                    </Collapsible>
                );
            })}
        </>
    );
}
