import { Head, usePage } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Info, TriangleAlert } from 'lucide-react';
import AppWordmark from '@/components/app-wordmark';
import FontPicker from '@/components/font-picker';
import { GlossaryTag } from '@/components/glossary-tags';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import ThemeSelector from '@/components/theme-selector';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useOptions } from '@/hooks/use-options';
import { isColorName, palette, statusClasses } from '@/lib/colors';
import { moduleIcon } from '@/lib/module-icons';
import type { ColoredList } from '@/lib/colors';
import type { ThemeData, ThemeDefaults } from '@/lib/themes';
import { cn } from '@/lib/utils';

const brandColors = [
    {
        name: 'Primär',
        token: '--primary',
        light: '#0272AD',
        dark: '#38B6F0',
        swatch: 'bg-primary',
        usage: 'Buttons, Links, aktive Navigation. Kontrast AA (5,2:1 hell, 7,8:1 dunkel).',
    },
    {
        name: 'Logo-Blau',
        token: '--brand',
        light: '#0299D7',
        dark: '#38B6F0',
        swatch: 'bg-brand',
        usage: 'Linien, Markierungen, Fokusrahmen, Symbole. Nicht für Fliesstext.',
    },
    {
        name: 'Cyan',
        token: '--brand-accent',
        light: '#00AEEF',
        dark: '#00AEEF',
        swatch: 'bg-brand-accent',
        usage: 'Nur dekorativ (Akzentlinien). Nie für Text.',
    },
    {
        name: 'Anthrazit',
        token: '--foreground',
        light: '#191718',
        dark: '#F3F4F5',
        swatch: 'bg-foreground',
        usage: 'Text. Im Dunkelmodus hell.',
    },
    {
        name: 'Fläche',
        token: '--sidebar / --muted',
        light: '#FBFBFB',
        dark: '#0F1112',
        swatch: 'bg-sidebar border',
        usage: 'Navigation und ruhige Hintergründe.',
    },
];

const statuses = [
    {
        key: 'success',
        label: 'Erfolg',
        icon: CircleCheck,
        text: 'Gespeichert.',
    },
    {
        key: 'warning',
        label: 'Warnung',
        icon: TriangleAlert,
        text: 'Geplant, noch nicht sichtbar.',
    },
    {
        key: 'error',
        label: 'Fehler',
        icon: CircleAlert,
        text: 'Bitte Titel ausfüllen.',
    },
    {
        key: 'info',
        label: 'Hinweis',
        icon: Info,
        text: 'Prototyp mit Testdaten.',
    },
] as const;

const lists: { list: ColoredList; title: string; dot?: boolean }[] = [
    { list: 'schools', title: 'Schulhäuser', dot: true },
    { list: 'announcementCategories', title: 'Kategorien Mitteilungen' },
    { list: 'eventCategories', title: 'Arten von Terminen' },
    { list: 'documentCategories', title: 'Kategorien Dokumente' },
    { list: 'guiStatuses', title: 'Status GUI-Prototypen' },
    { list: 'audiences', title: 'Zielgruppen', dot: true },
    { list: 'roles', title: 'Rollen' },
];

export default function ColorConcept({
    themes,
    defaults,
    presets,
    glossarTags = { kategorien: [], herkunft: [] },
}: {
    themes: ThemeData[];
    defaults: ThemeDefaults;
    presets: Record<string, { name: string; description: string }>;
    glossarTags?: Record<
        'kategorien' | 'herkunft',
        { label: string; bezeichnung: string; farbe: string }[]
    >;
}) {
    const options = useOptions();
    const { navigation } = usePage().props;

    return (
        <>
            <Head title="Farbkonzept" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Farbkonzept"
                    description="Abgeleitet aus dem Auftritt von sekbuelach.ch. Schrift: Open Sans (umstellbar)."
                />

                <ThemeSelector
                    themes={themes}
                    defaults={defaults}
                    presets={presets}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Logo</CardTitle>
                        <CardDescription>
                            Offizieller Schriftzug, im Dunkelmodus mit heller
                            Schrift. Signet für App-Symbol und Favicon.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-center gap-8">
                        <AppWordmark className="w-80 max-w-full" />
                        <img
                            src="/images/sekbuelach-icon.svg"
                            alt="Signet"
                            className="size-14 rounded-xl"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Grundfarben</CardTitle>
                        <CardDescription>
                            Definiert in resources/css/app.css (hell und
                            dunkel).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {brandColors.map((color) => (
                            <div key={color.token} className="flex gap-3">
                                <span
                                    className={cn(
                                        'size-14 shrink-0 rounded-lg',
                                        color.swatch,
                                    )}
                                />
                                <div className="grid content-start gap-0.5 text-sm">
                                    <span className="font-medium">
                                        {color.name}
                                    </span>
                                    <span className="font-mono text-xs text-muted-foreground">
                                        {color.light} · dunkel {color.dark}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {color.usage}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Bedienelemente</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3">
                            <Button>Speichern</Button>
                            <Button variant="outline">Abbrechen</Button>
                            <Button variant="secondary">Sekundär</Button>
                            <Button variant="destructive">Löschen</Button>
                            <Button variant="link">Link</Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Statusfarben</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            {statuses.map((status) => (
                                <div
                                    key={status.key}
                                    className={cn(
                                        'flex items-center gap-2 rounded-md border px-3 py-2 text-sm',
                                        statusClasses[status.key],
                                    )}
                                >
                                    <status.icon className="size-4" />
                                    <span className="font-medium">
                                        {status.label}:
                                    </span>
                                    {status.text}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Farben je Auswahlwert</CardTitle>
                        <CardDescription>
                            Zuordnung in config/intranet.php → colors. Neue
                            Werte dort eintragen, sonst erscheinen sie grau.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        {lists.map(({ list, title, dot }) => (
                            <div key={list} className="grid gap-2">
                                <span className="text-sm font-medium">
                                    {title}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {Object.keys(options[list]).map((key) => (
                                        <OptionBadge
                                            key={key}
                                            list={list}
                                            value={key}
                                            dot={dot}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                        <p className="text-xs text-muted-foreground">
                            Gefüllte Badges für Kategorien, Badges mit Punkt für
                            Schulhäuser und Zielgruppen. Termine erhalten
                            zusätzlich einen farbigen Rand links.
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Glossar: Kategorien und Herkunft</CardTitle>
                        <CardDescription>
                            Farbige Labels in der Glossar-Tabelle. Farbe je
                            Eintrag unter Glossar → Kategorien & Herkunft.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        {(
                            [
                                ['kategorien', 'Kategorien'],
                                ['herkunft', 'Herkunft'],
                            ] as const
                        ).map(([key, title]) => (
                            <div key={key} className="grid gap-2">
                                <span className="text-sm font-medium">
                                    {title}
                                </span>
                                <div className="flex flex-wrap gap-2">
                                    {glossarTags[key].map((t) => (
                                        <GlossaryTag
                                            key={t.label}
                                            label={t.label}
                                            color={t.farbe}
                                            title={t.bezeichnung}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Navigation (Hauptmenüs)</CardTitle>
                        <CardDescription>
                            Jeder Bereich der Seitenleiste hat eine Farbe und
                            ein Symbol: farbige Fläche für den Kopf, Symbole und
                            Markierung der aktiven Seite in der Bereichsfarbe.
                            Zuordnung in config/intranet.php → modules (color,
                            icon).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {navigation.map((group) => {
                            const color = isColorName(group.color)
                                ? palette[group.color]
                                : palette.slate;
                            const Icon = moduleIcon(group.icon);

                            return (
                                <div key={group.key} className="grid gap-1.5">
                                    <div
                                        className={cn(
                                            'flex h-10 items-center gap-2.5 rounded-lg px-2.5 text-sm font-semibold',
                                            color.panel,
                                        )}
                                    >
                                        <Icon
                                            className={cn('size-5', color.icon)}
                                        />
                                        {group.title}
                                    </div>
                                    <span className="px-1 font-mono text-xs text-muted-foreground">
                                        {group.color} · {group.icon} ·{' '}
                                        {group.items.length} Module
                                    </span>
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Schrift</CardTitle>
                        <CardDescription>
                            Standard ist Open Sans wie auf sekbuelach.ch. Jede
                            Person kann die Schrift unter Einstellungen →
                            Darstellung umstellen (gilt pro Browser).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FontPicker />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ColorConcept.layout = {
    breadcrumbs: [{ title: 'Farbkonzept', href: '/farbkonzept' }],
};
