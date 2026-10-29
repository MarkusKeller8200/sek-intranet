import { Head, Link } from '@inertiajs/react';
import { Check, Construction, Plug } from 'lucide-react';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { moduleIcon } from '@/lib/module-icons';
import type { PlannedModule } from '@/types';

type PluginLink = {
    id: string;
    name: string;
    enabled: boolean;
    canManage: boolean;
};

export default function ModuleShow({
    module,
    plugin,
}: {
    module: PlannedModule;
    plugin: PluginLink | null;
}) {
    const Icon = moduleIcon(module.icon);

    return (
        <>
            <Head title={module.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-start gap-4">
                    <div className="flex size-12 shrink-0 items-center justify-center rounded-xl border bg-muted text-brand">
                        <Icon className="size-6" />
                    </div>
                    <div className="flex-1">
                        <PageHeader
                            title={module.title}
                            description={module.description}
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge
                        variant="outline"
                        className="border-warning/30 bg-warning-soft text-warning"
                    >
                        <Construction /> Geplant
                    </Badge>
                    <span>Bereich {module.group}</span>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Geplante Funktionen</CardTitle>
                            <CardDescription>
                                Erster Entwurf – wird mit dem Kollegium
                                geschärft, bevor das Modul gebaut wird.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {module.features.length > 0 ? (
                                <ul className="space-y-2">
                                    {module.features.map((feature) => (
                                        <li
                                            key={feature}
                                            className="flex items-start gap-2 text-sm"
                                        >
                                            <Check className="mt-0.5 size-4 shrink-0 text-brand" />
                                            <span>{feature}</span>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Noch keine Funktionen festgelegt.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>So geht es weiter</CardTitle>
                            <CardDescription>
                                Platzhalter im Prototyp – es werden noch keine
                                Daten gespeichert.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <ol className="list-decimal space-y-1 pl-5">
                                <li>Bedürfnisse und Abläufe klären</li>
                                <li>Datenstruktur im Schema festlegen</li>
                                <li>Im Prototyp umsetzen und testen</li>
                            </ol>
                            {plugin && (
                                <div className="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                                    <Plug className="size-4 text-brand" />
                                    <span className="flex-1">
                                        Daten kommen über das Plugin{' '}
                                        <strong>{plugin.name}</strong> (
                                        {plugin.enabled
                                            ? 'eingeschaltet'
                                            : 'ausgeschaltet'}
                                        ).
                                    </span>
                                    {plugin.canManage && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={`/plugins/${plugin.id}`}
                                            >
                                                Plugin öffnen
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ModuleShow.layout = (props: { module: PlannedModule }) => ({
    breadcrumbs: [
        { title: props.module.group, href: `/module/${props.module.key}` },
        { title: props.module.title, href: `/module/${props.module.key}` },
    ],
});
