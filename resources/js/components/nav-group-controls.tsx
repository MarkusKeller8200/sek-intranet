import { usePage } from '@inertiajs/react';
import { ChevronsDownUp, ChevronsUpDown } from 'lucide-react';
import { groupContainsCurrent, groupIsOpen } from '@/components/nav-modules';
import { Checkbox } from '@/components/ui/checkbox';
import { useSidebar } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { setAllGroups, setSingleOpen, useNavGroups } from '@/lib/nav-groups';
import { cn } from '@/lib/utils';
import type { ModuleNavGroup } from '@/types';

/**
 * Steuerung der Hauptmenüs unter dem Logo (links neben dem Vermerk): alle zu- bzw. aufklappen und
 * «Nur ein Menü offen» (Aufklappen eines Menüs klappt die anderen zu). Gilt für diesen Browser.
 */
export default function NavGroupControls({
    className,
}: {
    className?: string;
}) {
    const groups = usePage<{ navigation: ModuleNavGroup[] }>().props.navigation;
    const { isMobile } = useSidebar();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { stored, mobile, single } = useNavGroups();
    const keys = groups.map((g) => g.key);
    const openKeys = groups
        .filter((g) =>
            isMobile
                ? (mobile[g.key] ?? false)
                : groupIsOpen(g, stored, isCurrentOrParentUrl),
        )
        .map((g) => g.key);
    // Mit «nur eines offen» gibt es nur «alle zuklappen»
    const collapse = openKeys.length > 0 || single;
    const label = collapse ? 'Alle Menüs zuklappen' : 'Alle Menüs aufklappen';
    const Icon = collapse ? ChevronsDownUp : ChevronsUpDown;

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <button
                type="button"
                onClick={() => setAllGroups(keys, !collapse, isMobile)}
                disabled={collapse && openKeys.length === 0}
                aria-label={label}
                title={label}
                className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none disabled:opacity-40"
            >
                <Icon className="size-4" aria-hidden />
            </button>
            <label
                className="flex cursor-pointer items-center gap-1.5 text-[10px] leading-tight text-muted-foreground"
                title="Aufklappen eines Menüs klappt alle anderen zu"
            >
                <Checkbox
                    checked={single}
                    onCheckedChange={(c: boolean | 'indeterminate') =>
                        setSingleOpen(
                            c === true,
                            keys,
                            groups.find((g) =>
                                groupContainsCurrent(g, isCurrentOrParentUrl),
                            )?.key ??
                                openKeys[0] ??
                                null,
                            isMobile,
                        )
                    }
                    className="size-3.5"
                />
                Nur ein Menü offen
            </label>
        </div>
    );
}
