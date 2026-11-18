import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';

/** Signet mit Titel und Name der Instanz (#216). */
export default function AppLogo() {
    const { branding } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-8 shrink-0 rounded-md" />
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold">Intranet</span>
                {branding?.name && (
                    <span
                        className="truncate text-xs text-muted-foreground"
                        translate="no"
                    >
                        {branding.name}
                    </span>
                )}
            </div>
        </>
    );
}
