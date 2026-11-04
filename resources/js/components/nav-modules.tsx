import { Link } from '@inertiajs/react';
import { ChevronDown, ExternalLink } from 'lucide-react';
import { Fragment, useEffect } from 'react';
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
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { moduleIcon } from '@/lib/module-icons';
import {
    resetMobileGroups,
    setGroupOpen,
    useNavGroups,
} from '@/lib/nav-groups';
import { isColorName, palette } from '@/lib/colors';
import { cn } from '@/lib/utils';
import type { ModuleNavGroup, ModuleNavItem } from '@/types';

/** Enthält die Gruppe die aktuelle Seite (auch in einem Untermenü)? */
export function groupContainsCurrent(
    group: ModuleNavGroup,
    isCurrent: (href: string) => boolean,
): boolean {
    return group.items.some(
        (item) =>
            isCurrent(item.href) ||
            (item.children ?? []).some((c) => isCurrent(c.href)),
    );
}

/**
 * Ist ein Hauptmenü am Bildschirm offen? Gemerkte Wahl gilt immer; sonst offen, wenn es Umgesetztes oder die
 * aktuelle Seite enthält.
 */
export function groupIsOpen(
    group: ModuleNavGroup,
    stored: Record<string, boolean>,
    isCurrent: (href: string) => boolean,
): boolean {
    return (
        stored[group.key] ??
        (group.items.some((item) => !item.planned) ||
            groupContainsCurrent(group, isCurrent))
    );
}

export function NavModules({ groups }: { groups: ModuleNavGroup[] }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state, isMobile, openMobile, setOpenMobile, setOpen } =
        useSidebar();
    // Gemerkter Zustand (am Bildschirm) bzw. mobil bei jedem Öffnen alle zu; siehe lib/nav-groups.ts
    const { stored, mobile: mobileGroups } = useNavGroups();
    const groupKeys = groups.map((g) => g.key);

    useEffect(() => {
        if (openMobile) {
            resetMobileGroups();
        }
    }, [openMobile]);

    // Nach Wahl eines Menüpunkts: mobil das Menü schliessen; Fachanwendungen klappen das Seitenmenü
    // auch am Bildschirm ein (mehr Platz für die Anwendung)
    const afterSelect = (item: ModuleNavItem) => {
        if (isMobile) {
            setOpenMobile(false);
        } else if (item.collapse) {
            setOpen(false);
        }
    };

    // In der schmalen Leiste (nur Symbole) gibt es keine Gruppentitel – alles zeigen.
    const iconOnly = state === 'collapsed' && !isMobile;

    const toggle = (key: string, open: boolean) =>
        setGroupOpen(key, open, groupKeys, isMobile);

    return (
        <>
            {groups.map((group) => {
                const containsCurrent = groupContainsCurrent(
                    group,
                    isCurrentOrParentUrl,
                );
                const open = isMobile
                    ? (mobileGroups[group.key] ?? false)
                    : iconOnly ||
                      groupIsOpen(group, stored, isCurrentOrParentUrl);
                const color = isColorName(group.color)
                    ? palette[group.color]
                    : palette.slate;
                const GroupIcon = moduleIcon(group.icon);

                return (
                    <Fragment key={group.key}>
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
                                            // Untermenü: in der Symbolleiste flach, sonst aufklappbar
                                            if (item.children) {
                                                return iconOnly ? (
                                                    item.children.map(
                                                        (child) => (
                                                            <MenuEntry
                                                                key={child.key}
                                                                item={child}
                                                                color={color}
                                                                current={isCurrentOrParentUrl(
                                                                    child.href,
                                                                )}
                                                                onSelect={() =>
                                                                    afterSelect(
                                                                        child,
                                                                    )
                                                                }
                                                            />
                                                        ),
                                                    )
                                                ) : (
                                                    <SubMenu
                                                        key={item.key}
                                                        item={item}
                                                        color={color}
                                                        open={
                                                            isMobile
                                                                ? (mobileGroups[
                                                                      `sub:${item.key}`
                                                                  ] ??
                                                                  item.children.some(
                                                                      (c) =>
                                                                          isCurrentOrParentUrl(
                                                                              c.href,
                                                                          ),
                                                                  ))
                                                                : (stored[
                                                                      `sub:${item.key}`
                                                                  ] ??
                                                                  item.children.some(
                                                                      (c) =>
                                                                          isCurrentOrParentUrl(
                                                                              c.href,
                                                                          ),
                                                                  ))
                                                        }
                                                        onOpenChange={(value) =>
                                                            toggle(
                                                                `sub:${item.key}`,
                                                                value,
                                                            )
                                                        }
                                                        isCurrent={
                                                            isCurrentOrParentUrl
                                                        }
                                                        onSelect={afterSelect}
                                                    />
                                                );
                                            }

                                            return (
                                                <MenuEntry
                                                    key={item.key}
                                                    item={item}
                                                    color={color}
                                                    current={isCurrentOrParentUrl(
                                                        item.href,
                                                    )}
                                                    onSelect={() =>
                                                        afterSelect(item)
                                                    }
                                                />
                                            );
                                        })}
                                    </SidebarMenu>
                                </CollapsibleContent>
                            </SidebarGroup>
                        </Collapsible>
                    </Fragment>
                );
            })}
        </>
    );
}

type GroupColor = (typeof palette)[keyof typeof palette];

/** Von SidebarMenuButton (asChild) übergebene Eigenschaften: Klassen und data-Attribute. */
type SlotProps = { className?: string } & {
    [key: `data-${string}`]: string | boolean | undefined;
};

/**
 * Link eines Menüpunkts: eingebunden nur als neuer Tab direkt zur Adresse, sonst Intranet-Seite.
 * Weitere Eigenschaften (Klassen, data-*) kommen von SidebarMenuButton (asChild) und werden weitergegeben.
 */
function EntryLink({
    item,
    color,
    onSelect,
    ...rest
}: {
    item: ModuleNavItem;
    color: GroupColor;
    onSelect: () => void;
} & SlotProps) {
    const Icon = moduleIcon(item.icon);

    return item.open === 'tab' && item.external ? (
        <a
            {...rest}
            href={item.external}
            target="_blank"
            rel="noopener noreferrer"
            onClick={onSelect}
        >
            <Icon className={color.icon} />
            <span>{item.title}</span>
            <ExternalLink className="ml-auto size-3 opacity-50" />
        </a>
    ) : (
        <Link
            {...rest}
            href={item.href}
            prefetch={!item.planned}
            onClick={onSelect}
        >
            <Icon className={cn(!item.planned && color.icon)} />
            <span>{item.title}</span>
        </Link>
    );
}

function MenuEntry({
    item,
    color,
    current,
    onSelect,
}: {
    item: ModuleNavItem;
    color: GroupColor;
    current: boolean;
    onSelect: () => void;
}) {
    return (
        <SidebarMenuItem>
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
                    item.planned && 'text-sidebar-foreground/60',
                )}
            >
                <EntryLink item={item} color={color} onSelect={onSelect} />
            </SidebarMenuButton>
            {item.planned && (
                <SidebarMenuBadge className="text-[10px] font-normal text-sidebar-foreground/50">
                    geplant
                </SidebarMenuBadge>
            )}
        </SidebarMenuItem>
    );
}

/** Aufklappbares Untermenü innerhalb einer Gruppe (z. B. Fachanwendungen). */
function SubMenu({
    item,
    color,
    open,
    onOpenChange,
    isCurrent,
    onSelect,
}: {
    item: ModuleNavItem;
    color: GroupColor;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    isCurrent: (href: string) => boolean;
    onSelect: (item: ModuleNavItem) => void;
}) {
    const Icon = moduleIcon(item.icon);
    const children = item.children ?? [];
    const containsCurrent = children.some((c) => isCurrent(c.href));

    return (
        <Collapsible open={open} onOpenChange={onOpenChange} asChild>
            <SidebarMenuItem data-testid={`submenu-${item.key}`}>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        className={cn(
                            'border-l-2 border-transparent',
                            containsCurrent && !open && color.bar,
                        )}
                        aria-expanded={open}
                    >
                        <Icon className={color.icon} />
                        <span>{item.title}</span>
                        <ChevronDown
                            className={cn(
                                'ml-auto size-4 opacity-70 transition-transform duration-200',
                                open && 'rotate-180',
                            )}
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {children.map((child) => {
                            const ChildIcon = moduleIcon(child.icon);
                            const current = isCurrent(child.href);

                            return (
                                <SidebarMenuSubItem key={child.key}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={current}
                                    >
                                        {child.open === 'tab' &&
                                        child.external ? (
                                            <a
                                                href={child.external}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                onClick={() => onSelect(child)}
                                            >
                                                <ChildIcon
                                                    className={color.icon}
                                                />
                                                <span>{child.title}</span>
                                                <ExternalLink className="ml-auto size-3 opacity-50" />
                                            </a>
                                        ) : (
                                            <Link
                                                href={child.href}
                                                onClick={() => onSelect(child)}
                                            >
                                                <ChildIcon
                                                    className={color.icon}
                                                />
                                                <span>{child.title}</span>
                                            </Link>
                                        )}
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}
