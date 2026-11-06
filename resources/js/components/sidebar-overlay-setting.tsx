import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import {
    SIDEBAR_OVERLAY_LABELS,
    setSidebarOverlay,
    useSidebarOverlaySetting,
} from '@/lib/sidebar-overlay';
import type { SidebarOverlay } from '@/lib/sidebar-overlay';

/** Einstellung «Menü überlagernd» (#153), gilt für diesen Browser. */
export default function SidebarOverlaySetting() {
    const value = useSidebarOverlaySetting();

    return (
        <div className="grid max-w-sm gap-2">
            <Label htmlFor="sidebar-overlay">Menü überlagernd</Label>
            <NativeSelect
                id="sidebar-overlay"
                value={value}
                onChange={(e) =>
                    setSidebarOverlay(e.target.value as SidebarOverlay)
                }
                aria-describedby="sidebar-overlay-hint"
            >
                {Object.entries(SIDEBAR_OVERLAY_LABELS).map(([key, label]) => (
                    <option key={key} value={key}>
                        {label}
                    </option>
                ))}
            </NativeSelect>
            <p
                id="sidebar-overlay-hint"
                className="text-sm text-muted-foreground"
            >
                Das Seitenmenü öffnet über dem Inhalt statt daneben (Knopf oben
                links). Schafft Platz, wenn eine Fachanwendung im Browser
                geteilt neben dem Intranet läuft. «Bei schmalem Fenster» gilt
                unter 1280 Pixel Breite.
            </p>
        </div>
    );
}
