import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <AppLogoIcon className="size-8 shrink-0 rounded-md" />
            <div className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-sm font-semibold">Intranet</span>
                <span className="truncate text-xs text-muted-foreground">
                    Sekundarschule Bülach
                </span>
            </div>
        </>
    );
}
