import { Head, Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { moduleIcon } from '@/lib/module-icons';

type Katalog = {
    key: string;
    title: string;
    bereich: string;
    icon: string;
    description: string;
    anzahl: number;
    pruefen: number;
};

/** Übersicht der Kataloge unter «Bestellen» (#198). */
export default function BeschaffungIndex({
    kataloge,
    darfBearbeiten,
}: {
    kataloge: Katalog[];
    darfBearbeiten: boolean;
}) {
    const bereiche = [...new Set(kataloge.map((k) => k.bereich))];

    return (
        <>
            <Head title="Bestellen – Kataloge" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Bestellen – Kataloge"
                    description={
                        darfBearbeiten
                            ? 'Alle Listen haben denselben Aufbau. Bearbeiten direkt in der Tabelle; fehlende Angaben sind mit «Prüfen» markiert.'
                            : 'Standardausrüstung, Plattformen, Apps und Material der Schule.'
                    }
                />
                {bereiche.map((bereich) => (
                    <section key={bereich} className="space-y-3">
                        <h2 className="text-sm font-semibold text-muted-foreground">
                            {bereich}
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            {kataloge
                                .filter((k) => k.bereich === bereich)
                                .map((k) => {
                                    const Icon = moduleIcon(k.icon);

                                    return (
                                        <Link
                                            key={k.key}
                                            href={`/beschaffung/${k.key}`}
                                            className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            <Card className="h-full transition-colors hover:bg-muted/50">
                                                <CardHeader>
                                                    <CardTitle className="flex items-center gap-2">
                                                        <Icon className="size-5 shrink-0 text-brand" />
                                                        {k.title}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {k.description}
                                                    </CardDescription>
                                                    <div className="flex flex-wrap gap-2 pt-1">
                                                        <Badge variant="outline">{`${k.anzahl} Einträge`}</Badge>
                                                        {darfBearbeiten &&
                                                            k.pruefen > 0 && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-warning/30 bg-warning-soft text-warning"
                                                                >
                                                                    <TriangleAlert />{' '}
                                                                    {`${k.pruefen} zu prüfen`}
                                                                </Badge>
                                                            )}
                                                    </div>
                                                </CardHeader>
                                            </Card>
                                        </Link>
                                    );
                                })}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

BeschaffungIndex.layout = {
    breadcrumbs: [{ title: 'Bestellen', href: '/beschaffung' }],
};
