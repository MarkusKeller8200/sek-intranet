import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ChevronRight, Plug } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import DataNotice from '@/components/plugins/data-notice';
import PluginStatus, {
    categoryLabels,
} from '@/components/plugins/plugin-status';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/format';
import type { PluginInfo } from '@/types';

export default function PluginsIndex({
    plugins,
    errors,
    serverInSwitzerland,
}: {
    plugins: PluginInfo[];
    errors: string[];
    serverInSwitzerland: boolean;
}) {
    return (
        <>
            <Head title="Plugins" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Plugins"
                    description="Anbindung anderer Systeme per API. Plugins kommen aus dem Repository und werden hier ein- und ausgeschaltet."
                />

                {!serverInSwitzerland && <DataNotice />}

                {errors.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>Nicht geladene Plugins</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {errors.map((e) => (
                                    <li key={e}>{e}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {plugins.length === 0 && (
                    <EmptyState icon={Plug} title="Keine Plugins gefunden">
                        Plugins liegen im Ordner plugins/ (siehe PLUGINS.md).
                    </EmptyState>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    {plugins.map((plugin) => (
                        <div
                            key={plugin.id}
                            className="grid gap-3 rounded-xl border bg-card p-4"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="grid gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">
                                            {plugin.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {categoryLabels[plugin.category] ??
                                                plugin.category}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            v{plugin.version}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {plugin.description}
                                    </p>
                                </div>
                                <PluginStatus plugin={plugin} />
                            </div>

                            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>
                                    {plugin.service.endpoints.length} Endpunkte
                                </span>
                                <span>{plugin.actions.length} Aktionen</span>
                                {plugin.widgets.length > 0 && (
                                    <span>
                                        {plugin.widgets.length} Kachel(n)
                                    </span>
                                )}
                                {plugin.events.length > 0 && (
                                    <span>
                                        {plugin.events.length} Ereignisse
                                    </span>
                                )}
                                {plugin.last_run && (
                                    <span>
                                        Zuletzt: {plugin.last_run.label},{' '}
                                        {formatDateTime(
                                            plugin.last_run.created_at,
                                        )}
                                    </span>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id={`enable-${plugin.id}`}
                                        checked={plugin.enabled}
                                        onCheckedChange={(
                                            c: boolean | 'indeterminate',
                                        ) =>
                                            router.post(
                                                `/plugins/${plugin.id}/schalter`,
                                                { enabled: c === true },
                                                { preserveScroll: true },
                                            )
                                        }
                                    />
                                    <Label htmlFor={`enable-${plugin.id}`}>
                                        Eingeschaltet
                                    </Label>
                                </div>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={`/plugins/${plugin.id}`}>
                                        Einrichten und testen <ChevronRight />
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

PluginsIndex.layout = {
    breadcrumbs: [{ title: 'Plugins', href: '/plugins' }],
};
