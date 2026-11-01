import { AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { statusClasses } from '@/lib/colors';
import { cn } from '@/lib/utils';

const ICONS = { info: Info, warning: AlertTriangle, success: CheckCircle2 } as const;

/**
 * Banner über die ganze Breite oben auf der Seite, z. B. «Wartung heute ab 18 Uhr».
 * Für Hinweise innerhalb eines Bereichs die Hinweisbox (Alert) verwenden. GUI-Prototyp «Banner».
 */
function Banner({
    tone = 'info',
    children,
    action,
    onClose,
}: {
    tone?: keyof typeof ICONS;
    children: ReactNode;
    action?: ReactNode;
    onClose?: () => void;
}) {
    const Icon = ICONS[tone];

    return (
        <div role={tone === 'warning' ? 'alert' : 'status'} className={cn('flex items-center gap-3 border-y px-4 py-2 text-sm', statusClasses[tone])}>
            <Icon className="size-4 shrink-0" aria-hidden />
            <div className="flex-1">{children}</div>
            {action}
            {onClose && (
                <button type="button" onClick={onClose} title="Schliessen" className="rounded p-1 hover:bg-background/50">
                    <X className="size-4" />
                    <span className="sr-only">Schliessen</span>
                </button>
            )}
        </div>
    );
}

export { Banner };
