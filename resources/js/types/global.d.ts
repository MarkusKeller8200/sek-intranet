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
            store: string;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
