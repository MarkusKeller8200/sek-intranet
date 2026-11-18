import { usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * Vermerk unter dem Logo (#216): Entwicklersignatur «developed by MarKusSXCH» (CI-Manual, Footer und Signaturen) und
 * – falls gesetzt – für wen die Instanz läuft.
 */
export default function AppCredit({ className }: { className?: string }) {
    const { branding } = usePage().props;

    return (
        <p className={cn('text-right text-[10px] leading-tight', className)}>
            <span
                className="block font-semibold text-foreground"
                translate="no"
            >
                © 2026 developed by MarKusSXCH
            </span>
            {branding?.name && branding.marke !== 'eigene' && (
                <span className="block text-muted-foreground" translate="no">
                    {branding.name}
                </span>
            )}
        </p>
    );
}
