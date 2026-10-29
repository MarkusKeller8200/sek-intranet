import type { Auth } from '@/types/auth';
import type { Options } from '@/types/intranet';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            options: Options;
            store: string;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
