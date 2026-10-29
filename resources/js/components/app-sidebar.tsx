import { Link, usePage } from '@inertiajs/react';
import {
    CalendarDays,
    Database,
    FileText,
    LayoutGrid,
    Megaphone,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Start', href: '/start', icon: LayoutGrid },
    { title: 'Mitteilungen', href: '/mitteilungen', icon: Megaphone },
    { title: 'Termine', href: '/termine', icon: CalendarDays },
    { title: 'Dokumente & Links', href: '/dokumente', icon: FileText },
    { title: 'Personen', href: '/personen', icon: Users },
];

const adminNavItems: NavItem[] = [
    { title: 'Datenstrukturen', href: '/datenstrukturen', icon: Database },
];

export function AppSidebar() {
    const { auth, store } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/start" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label="Intranet" />
                {auth.can.manageUsers && (
                    <NavMain items={adminNavItems} label="Prototyp" />
                )}
            </SidebarContent>

            <SidebarFooter>
                <div className="mt-auto rounded-md border border-dashed border-sidebar-border px-3 py-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    Prototyp · Speicher:{' '}
                    {store === 'json' ? 'Dateien (JSON)' : store}
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
