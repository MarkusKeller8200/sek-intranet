import { usePage } from '@inertiajs/react';
import BrandWordmark from '@/components/brand-wordmark';
import { cn } from '@/lib/utils';

/**
 * Vermerk unter dem Logo (#216): Entwicklersignatur «Developed by MarKusSXCH» nach CI-Manual (Footer und
 * Signaturen), die Wortmarke in den CI-Farben, und – falls gesetzt – für wen die Instanz läuft.
 */
export default function AppCredit({ className }: { className?: string }) {
    const { branding } = usePage().props;

    return (
        <p className={cn('text-right text-[11px] leading-tight', className)}>
            <span className="block text-muted-foreground" translate="no">
                © 2026 Developed by{' '}
                <BrandWordmark marke="developer" className="font-bold" />
            </span>
            {branding?.name && branding.marke !== 'eigene' && (
                <span className="block text-muted-foreground" translate="no">
                    {branding.name}
                </span>
            )}
        </p>
    );
}
