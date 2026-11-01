import { AlertTriangle, Bell, CheckCircle2, Home, Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { statusClasses } from '@/lib/colors';
import { cssVars } from '@/lib/themes';
import type { Colors, Mode } from '@/lib/themes';
import { cn } from '@/lib/utils';

/**
 * Bereich mit eigenen Farben (Vorschau eines Themes, hell oder dunkel). Fenster, die sich über die Seite
 * legen (Dialoge, Popover, Menüs), erscheinen in den Farben des Intranets.
 */
export function ThemeScope({
    colors,
    mode,
    children,
    className,
}: {
    colors: Colors;
    mode: Mode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            data-theme-scope={mode}
            style={{ ...cssVars(colors), colorScheme: mode }}
            className={cn(mode === 'dark' && 'dark', 'bg-background text-foreground', className)}
        >
            {children}
        </div>
    );
}

/** Kleine Musterseite für die Vorschau eines Themes. */
export function ThemeSample() {
    return (
        <div className="grid grid-cols-[9rem_1fr] overflow-hidden rounded-lg border text-sm">
            <nav className="grid content-start gap-0.5 border-r border-sidebar-border bg-sidebar p-2 text-sidebar-foreground">
                <span className="flex items-center gap-2 rounded-md px-2 py-1.5">
                    <Home className="size-4" /> Start
                </span>
                <span className="flex items-center gap-2 rounded-md bg-sidebar-accent px-2 py-1.5 font-medium text-sidebar-accent-foreground">
                    <Bell className="size-4" /> Mitteilungen
                </span>
            </nav>
            <div className="grid gap-3 p-3">
                <div>
                    <p className="font-semibold">Mitteilungen</p>
                    <p className="text-xs text-muted-foreground">Leiser Text für Erklärungen.</p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button size="sm">Speichern</Button>
                    <Button size="sm" variant="secondary">
                        Abbrechen
                    </Button>
                    <Button size="sm" variant="outline">
                        Export
                    </Button>
                    <Button size="sm" variant="destructive">
                        Löschen
                    </Button>
                </div>
                <div className="rounded-md border bg-card p-3 text-card-foreground">
                    <p className="font-medium">Karte</p>
                    <p className="text-xs text-muted-foreground">Leiser Text in der Karte.</p>
                    <span className="text-xs text-primary underline">Ein Link</span>
                    <Input className="mt-2 h-8" placeholder="Eingabefeld" />
                </div>
                <div className="flex flex-wrap gap-2">
                    <Badge>Neu</Badge>
                    <Badge variant="secondary">Entwurf</Badge>
                    <Badge variant="outline">Vorgabe</Badge>
                    <span className={cn('inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs', statusClasses.success)}>
                        <CheckCircle2 className="size-3" /> Erfolg
                    </span>
                    <span className={cn('inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs', statusClasses.warning)}>
                        <AlertTriangle className="size-3" /> Warnung
                    </span>
                </div>
                <Alert>
                    <Info />
                    <AlertTitle>Hinweis</AlertTitle>
                    <AlertDescription>Nur erfundene Testdaten.</AlertDescription>
                </Alert>
            </div>
        </div>
    );
}
