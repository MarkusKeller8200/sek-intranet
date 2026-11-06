import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import FontPicker from '@/components/font-picker';
import { InstallAppButton } from '@/components/install-app';
import LanguagePicker from '@/components/language-picker';
import Heading from '@/components/heading';
import SidebarOverlaySetting from '@/components/sidebar-overlay-setting';
import TermHighlightSetting from '@/components/term-highlight-setting';

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

                <Heading
                    variant="small"
                    title="Menü"
                    description="Gilt für diesen Browser."
                />
                <SidebarOverlaySetting />

                <Heading
                    variant="small"
                    title="Begriffe aus dem Glossar"
                    description="Abkürzungen und Begriffe aus dem Glossar sind fein gepunktet unterstrichen und zeigen beim Darüberfahren oder Antippen eine kurze Erklärung. Gilt für diesen Browser."
                />
                <TermHighlightSetting />

                <Heading
                    variant="small"
                    title="App"
                    description="Das Intranet lässt sich auf Computer, Tablet und Handy wie eine App installieren (eigenes Fenster, Symbol auf dem Startbildschirm)."
                />
                <InstallAppButton variant="settings" />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [{ title: 'Darstellung', href: '/einstellungen/darstellung' }],
};
