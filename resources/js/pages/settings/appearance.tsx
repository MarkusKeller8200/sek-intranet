import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';

export default function Appearance() {
    return (
        <>
            <Head title="Darstellung" />
            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Darstellung"
                    description="Hell, dunkel oder wie das Betriebssystem"
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [{ title: 'Darstellung', href: '/einstellungen/darstellung' }],
};
