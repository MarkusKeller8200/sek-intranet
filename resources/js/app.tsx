import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import { initializeFont } from '@/hooks/use-font';
import { initializeLanguage, t } from '@/lib/i18n';
import { initializePwa } from '@/lib/pwa';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'SekBülach Intranet';

// Seiten des Core (resources/js/pages) und der Module (modules/<Ordner>/resources/js/pages, Name «Ordner::seite»)
const corePages = import.meta.glob('./pages/**/*.tsx');
const modulePages = import.meta.glob(
    '../../modules/*/resources/js/pages/**/*.tsx',
);

void createInertiaApp({
    resolve: async (name: string) => {
        const [folder, page] = name.includes('::')
            ? name.split('::', 2)
            : [null, name];
        const loader =
            folder === null
                ? corePages[`./pages/${page}.tsx`]
                : modulePages[
                      `../../modules/${folder}/resources/js/pages/${page}.tsx`
                  ];
        if (!loader) {
            throw new Error(`Page not found: ${name}`);
        }
        const module = (await loader()) as { default?: unknown };

        return (module.default ?? module) as never;
    },
    title: (title) => (title ? `${t(title)} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializeFont();
initializeLanguage();
initializePwa(
    document.querySelector<HTMLMetaElement>('meta[name="app-build"]')
        ?.content ?? null,
);
