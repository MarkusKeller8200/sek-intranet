import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, ExternalLink, Tag } from 'lucide-react';
import { Fragment, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
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

/** Version und Build, verlinkt auf die Seite «Versionen». */
function VersionLine() {
    const { appVersion } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="px-2 py-1 group-data-[collapsible=icon]:hidden">
            <Link
                href="/versionen"
                className={cn(
                    'flex items-center gap-2 rounded-md px-2.5 py-1.5 text-xs text-sidebar-foreground/70 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                    isCurrentOrParentUrl('/versionen') &&
                        'bg-sidebar-accent text-sidebar-accent-foreground',
                )}
                title="Versionen und Änderungen"
            >
                <Tag className="size-3.5 shrink-0" />
                <span>Version {appVersion.version}</span>
                {appVersion.build && (
                    <span className="font-mono text-sidebar-foreground/50">
                        {appVersion.build}
                    </span>
                )}
                {appVersion.prototype && (
                    <span className="ml-auto rounded border border-sidebar-border px-1 text-[10px] tracking-wide uppercase">
                        Prototyp
                    </span>
                )}
            </Link>
        </div>
    );
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

    const hasPrototype = groups.some((g) => g.key === 'prototyp');

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
                const GroupIcon = moduleIcon(group.icon);

                return (
                    <Fragment key={group.key}>
                        {group.key === 'prototyp' && <VersionLine />}
                        <Collapsible
                            open={open}
                            onOpenChange={(value: boolean) =>
                                toggle(group.key, value)
                            }
                            className="group/collapsible"
                        >
                            <SidebarGroup className="px-2 py-0.5">
                                {/* Hauptmenü: farbige Fläche mit Symbol (in der Symbolleiste ausgeblendet) */}
                                <CollapsibleTrigger
                                    className={cn(
                                        'flex h-10 w-full items-center gap-2.5 rounded-lg px-2.5 text-left text-sm font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none',
                                        'group-data-[collapsible=icon]:hidden',
                                        color.panel,
                                    )}
                                    title={open ? 'Einklappen' : 'Ausklappen'}
                                >
                                    <GroupIcon
                                        className={cn(
                                            'size-5 shrink-0',
                                            color.icon,
                                        )}
                                        aria-hidden
                                    />
                                    <span className="flex-1 truncate">
                                        {group.title}
                                    </span>
                                    {!open && containsCurrent && (
                                        <span
                                            className={cn(
                                                'size-2 shrink-0 rounded-full',
                                                color.dot,
                                            )}
                                            title="Enthält die aktuelle Seite"
                                        />
                                    )}
                                    <ChevronDown
                                        className={cn(
                                            'size-4 shrink-0 opacity-70 transition-transform duration-200',
                                            open && 'rotate-180',
                                        )}
                                    />
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenu className="py-1">
                                        {group.items.map((item) => {
                                            const Icon = moduleIcon(item.icon);
                                            const current =
                                                isCurrentOrParentUrl(item.href);

                                            return (
                                                <SidebarMenuItem key={item.key}>
                                                    <SidebarMenuButton
                                                        asChild
                                                        isActive={current}
                                                        tooltip={{
                                                            children:
                                                                item.planned
                                                                    ? `${item.title} (geplant)`
                                                                    : item.title,
                                                        }}
                                                        className={cn(
                                                            'border-l-2 border-transparent',
                                                            current &&
                                                                color.bar,
                                                            item.planned &&
                                                                'text-sidebar-foreground/60',
                                                        )}
                                                    >
                                                        {item.open ===
                                                            'tab' &&
                                                        item.external ? (
                                                            // Nur neuer Tab: direkt zur Adresse
                                                            <a
                                                                href={
                                                                    item.external
                                                                }
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                            >
                                                                <Icon
                                                                    className={
                                                                        color.icon
                                                                    }
                                                                />
                                                                <span>
                                                                    {item.title}
                                                                </span>
                                                                <ExternalLink className="ml-auto size-3 opacity-50" />
                                                            </a>
                                                        ) : (
                                                            <Link
                                                                href={item.href}
                                                                prefetch={
                                                                    !item.planned
                                                                }
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
                                                        )}
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
                    </Fragment>
                );
            })}
            {!hasPrototype && <VersionLine />}
        </>
    );
}
