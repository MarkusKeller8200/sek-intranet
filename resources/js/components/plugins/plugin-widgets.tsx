import { Deferred } from '@inertiajs/react';
import { AlertTriangle, Plug } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import type { PluginWidget } from '@/types';

/** Kacheln der eingeschalteten Plugins (nachgeladen). */
export default function PluginWidgets({
    widgets,
}: {
    widgets?: PluginWidget[];
}) {
    return (
        <Deferred
            data="widgets"
            fallback={
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Skeleton className="h-28 rounded-xl" />
                </div>
            }
        >
            {(widgets ?? []).length > 0 ? (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {(widgets ?? []).map((widget) => (
                        <Card key={`${widget.plugin}-${widget.id}`}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Plug className="size-4 text-brand" />
                                    {widget.label}
                                </CardTitle>
                                <CardDescription>
                                    {widget.plugin_name}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-2">
                                {widget.error ? (
                                    <p className="flex items-center gap-2 text-sm text-destructive-foreground">
                                        <AlertTriangle className="size-4" />{' '}
                                        {widget.error}
                                    </p>
                                ) : (
                                    widget.items.map((item) => (
                                        <div
                                            key={item.label}
                                            className="flex items-baseline justify-between gap-3 text-sm"
                                        >
                                            <span className="text-muted-foreground">
                                                {item.label}
                                            </span>
                                            <span
                                                className="font-semibold tabular-nums"
                                                title={item.hint}
                                            >
                                                {item.value}
                                            </span>
                                        </div>
                                    ))
                                )}
                                {widget.note && (
                                    <p className="text-xs text-muted-foreground">
                                        {widget.note}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            ) : null}
        </Deferred>
    );
}
