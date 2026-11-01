import { Link, usePage } from '@inertiajs/react';
import { Tag } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

/** Version, Build und Prototyp-Hinweis in einem Feld (Fuss der Seitenleiste), verlinkt auf «Versionen». */
export function VersionBox({ store }: { store: string }) {
    const { appVersion } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { isMobile, setOpenMobile } = useSidebar();

    return (
        <Link
            href="/versionen"
            onClick={() => isMobile && setOpenMobile(false)}
            title="Versionen und Änderungen"
            className={cn(
                'mt-auto grid gap-0.5 rounded-md border border-dashed border-sidebar-border px-3 py-2 text-xs text-sidebar-foreground/70 group-data-[collapsible=icon]:hidden hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                isCurrentOrParentUrl('/versionen') &&
                    'bg-sidebar-accent text-sidebar-accent-foreground',
            )}
        >
            <span className="flex items-center gap-2">
                <Tag className="size-3.5 shrink-0" />
                <span className="font-medium">
                    {`Version ${appVersion.version}`}
                </span>
                {appVersion.build && (
                    <span
                        className="font-mono text-sidebar-foreground/50"
                        translate="no"
                    >
                        {appVersion.build}
                    </span>
                )}
            </span>
            {appVersion.dev && (
                <span
                    className="pl-5.5 font-medium text-foreground/80"
                    title={`${appVersion.dev.commits} Änderungen seit Version ${appVersion.dev.base} – Ziel ${appVersion.dev.planned}`}
                >
                    Developer Release
                </span>
            )}
            {appVersion.prototype && (
                <span className="pl-5.5 text-muted-foreground">
                    Prototyp · Speicher:{' '}
                    {store === 'json' ? 'Dateien (JSON)' : store}
                </span>
            )}
        </Link>
    );
}
