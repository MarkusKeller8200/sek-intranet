import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import FontPicker from '@/components/font-picker';
import LanguagePicker from '@/components/language-picker';
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

                <Heading
                    variant="small"
                    title="Schriftart"
                    description="Gilt für diesen Browser. Gut lesbare Schriften helfen auf kleinen Bildschirmen."
                />
                <FontPicker />

                <Heading
                    variant="small"
                    title="Sprache"
                    description="Sprache der Oberfläche. Gilt für diesen Browser; Inhalte wie Mitteilungen bleiben in ihrer Sprache."
                />
                <LanguagePicker />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [{ title: 'Darstellung', href: '/einstellungen/darstellung' }],
};
