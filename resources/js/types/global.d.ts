import type { Auth } from '@/types/auth';
import type { Options } from '@/types/intranet';
import type { ModuleNavGroup } from '@/types/navigation';

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
            [key: string]: unknown;
        };
    }
}
