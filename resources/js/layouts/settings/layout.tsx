import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <div className="px-4 py-6">
            <Heading
                title="Einstellungen"
                description="Persönliche Einstellungen für dieses Gerät"
            />
            <div className="max-w-xl space-y-12">{children}</div>
        </div>
    );
}
