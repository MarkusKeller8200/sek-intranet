import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavModules } from '@/components/nav-modules';
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

export function AppSidebar() {
    const { navigation, store } = usePage().props;

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
                <NavModules groups={navigation} />
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
