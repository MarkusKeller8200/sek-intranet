import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { NotificationItem } from '@/types/auth';

/** Alle Benachrichtigungen der angemeldeten Person (neueste zuerst). */
export default function Notifications({
    items,
}: {
    items: NotificationItem[];
}) {
    const unread = items.filter((n) => n.read_at === null).length;

    return (
        <>
            <Head title="Benachrichtigungen" />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Benachrichtigungen"
                    description="Meldungen zu deinen Aufgaben im Intranet, z. B. Prüfungen und Wünsche bei den GUI-Prototypen."
                    actions={
                        unread > 0 ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post('/benachrichtigungen/gelesen')
                                }
                            >
                                <CheckCheck /> Alle gelesen
                            </Button>
                        ) : undefined
                    }
                />
                {items.length === 0 ? (
                    <EmptyState icon={Bell} title="Keine Benachrichtigungen">
                        Hier erscheinen Meldungen, sobald etwas für dich
                        ansteht.
                    </EmptyState>
                ) : (
                    <Card className="py-0">
                        <CardContent className="divide-y px-0">
                            {items.map((n) => (
                                <Link
                                    key={n.id}
                                    href={`/benachrichtigungen/${n.id}`}
                                    className={cn(
                                        'grid gap-0.5 px-4 py-3 hover:bg-accent/50',
                                        n.read_at === null && 'bg-accent/30',
                                    )}
                                    data-testid={`notification-${n.id}`}
                                >
                                    <span
                                        className={cn(
                                            'flex items-start gap-2 text-sm',
                                            n.read_at === null &&
                                                'font-semibold',
                                        )}
                                    >
                                        {n.read_at === null && (
                                            <span
                                                className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                                aria-hidden
                                            />
                                        )}
                                        <span>{n.title}</span>
                                    </span>
                                    {n.body && (
                                        <span className="text-sm text-muted-foreground">
                                            {n.body}
                                        </span>
                                    )}
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {formatDateTime(n.sent_at)}
                                    </span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [{ title: 'Benachrichtigungen', href: '/benachrichtigungen' }],
};
