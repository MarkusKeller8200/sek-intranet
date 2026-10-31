import { Badge } from '@/components/ui/badge';
import type { PluginInfo } from '@/types';

export const categoryLabels: Record<string, string> = {
    identity: 'Identitäten',
    devices: 'Geräte',
    inventory: 'Inventar',
    school: 'Schul-IT',
    communication: 'Kommunikation',
    other: 'Sonstiges',
};

export default function PluginStatus({ plugin }: { plugin: PluginInfo }) {
    if (!plugin.enabled) {
        return <Badge variant="outline">aus</Badge>;
    }
    if (!plugin.configured) {
        return (
            <Badge
                variant="outline"
                className="border-warning/30 bg-warning-soft text-warning"
            >
                nicht eingerichtet
            </Badge>
        );
    }
    if (plugin.last_run?.status === 'error') {
        return (
            <Badge
                variant="outline"
                className="border-destructive/30 bg-destructive/10 text-destructive-foreground"
            >
                Fehler beim letzten Lauf
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="border-success/30 bg-success-soft text-success"
        >
            bereit
        </Badge>
    );
}
