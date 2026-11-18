import { usePage } from '@inertiajs/react';
import BrandWordmark from '@/components/brand-wordmark';
import { cn } from '@/lib/utils';

/**
 * Logo der Instanz (#216, White-Labeling): eigenes Logo (hell und dunkel) oder die Wortmarke EduKSX-Intranet bzw.
 * MarKusSXCH nach CI. Einstellungen → Erscheinungsbild.
 */
export default function AppWordmark({ className }: { className?: string }) {
    const { branding } = usePage().props;

    if (branding?.logo) {
        const alt = branding.name || 'Logo';

        return (
            <>
                <img
                    src={branding.logo.hell}
                    alt={alt}
                    className={cn('h-auto dark:hidden', className)}
                />
                <img
                    src={branding.logo.dunkel}
                    alt={alt}
                    className={cn('hidden h-auto dark:block', className)}
                />
            </>
        );
    }

    return (
        <span className={cn('grid gap-0.5', className)}>
            <span className="flex items-center gap-2">
                <img
                    src={
                        branding?.signet ??
                        '/images/brands/eduksx-intranet-tile-light.svg'
                    }
                    alt=""
                    className="size-9 shrink-0"
                />
                {branding?.wortmarke === 'developer' ? (
                    <BrandWordmark marke="developer" className="text-xl" />
                ) : (
                    <BrandWordmark
                        marke="education"
                        zusatz="Intranet"
                        className="text-xl"
                    />
                )}
            </span>
            {branding?.name && (
                <span
                    className="truncate text-xs text-muted-foreground"
                    translate="no"
                >
                    {branding.name}
                </span>
            )}
        </span>
    );
}
