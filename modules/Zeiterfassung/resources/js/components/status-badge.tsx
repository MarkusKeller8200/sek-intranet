import { Badge } from '@/components/ui/badge';
import type { Status } from '../lib/types';

const VARIANT: Record<Status['status'], 'default' | 'secondary' | 'outline' | 'destructive'> = {
    offen: 'outline',
    eingereicht: 'secondary',
    visiert: 'default',
    zurueck: 'destructive',
};

export default function StatusBadge({
    status,
    labels,
}: {
    status: Status['status'];
    labels: Record<string, string>;
}) {
    return <Badge variant={VARIANT[status]}>{labels[status] ?? status}</Badge>;
}
