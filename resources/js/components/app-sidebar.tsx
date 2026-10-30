import { Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppWordmark from '@/components/app-wordmark';
import { NavModules } from '@/components/nav-modules';
import { LanguageSelect } from '@/components/language-picker';
import { NavUser } from '@/components/nav-user';
import { SidebarSplitter } from '@/components/sidebar-splitter';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';

export function AppSidebar() {
    const { navigation, store } = usePage().props;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader className="px-3 pt-3 pb-2">
                {/* Logo über die ganze Breite; skaliert mit dem Splitter mit */}
                <Link
                    href="/start"
                    prefetch
                    aria-label="Zur Startseite"
                    className="block rounded-md p-1 group-data-[collapsible=icon]:hidden focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                >
                    <AppWordmark className="w-full" />
                </Link>
                {/* Schmale Leiste: nur das Signet */}
                <Link
                    href="/start"
                    prefetch
                    aria-label="Zur Startseite"
                    className="hidden justify-center group-data-[collapsible=icon]:flex"
                >
                    <AppLogoIcon className="size-8 rounded-md" />
                </Link>
            </SidebarHeader>

            <SidebarContent>
                <NavModules groups={navigation} />
            </SidebarContent>

            <SidebarFooter>
                <div className="mt-auto rounded-md border border-dashed border-sidebar-border px-3 py-2 text-xs text-muted-foreground group-data-[collapsible=icon]:hidden">
                    Prototyp · Speicher:{' '}
                    {store === 'json' ? 'Dateien (JSON)' : store}
                </div>
                <LanguageSelect />
                <NavUser />
            </SidebarFooter>
            <SidebarSplitter />
        </Sidebar>
    );
}
