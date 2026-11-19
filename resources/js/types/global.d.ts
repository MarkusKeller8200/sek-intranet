import type { Auth } from '@/types/auth';
import type { Options } from '@/types/intranet';
import type { ModuleNavGroup } from '@/types/navigation';

/** Erscheinungsbild (#216): Wortmarke nach CI oder eigenes Logo. */
export type Branding = {
    marke: 'eduksx' | 'markussx' | 'eigene';
    name: string;
    wortmarke: 'education' | 'developer' | null;
    logo: { hell: string; dunkel: string } | null;
    signet: string;
    /** Entwicklersignatur anzeigen (#220) */
    signatur: boolean;
    version: string;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            options: Options;
            navigation: ModuleNavGroup[];
            appVersion: {
                version: string;
                build: string | null;
                build_date: string | null;
                prototype: boolean;
                dev: {
                    commits: number;
                    base: string;
                    planned: string;
                } | null;
            };
            store: string;
            sidebarOpen: boolean;
            /** Erscheinungsbild der Instanz (#216) */
            branding?: Branding;
            [key: string]: unknown;
        };
    }
}
