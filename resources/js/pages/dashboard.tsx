import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    ExternalLink,
    FileText,
    Megaphone,
    Pin,
    Users,
} from 'lucide-react';
import EmptyState from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { label, useOptions } from '@/hooks/use-options';
import { formatDate, formatEventRange } from '@/lib/format';
import type { Announcement, DocumentItem, IntranetEvent } from '@/types';

type Props = {
    announcements: Announcement[];
    events: IntranetEvent[];
    links: DocumentItem[];
    stats: {
        announcements: number;
        events: number;
        documents: number;
        people: number;
    };
};

export default function Dashboard({
    announcements,
    events,
    links,
    stats,
}: Props) {
    const { auth } = usePage().props;
    const options = useOptions();
    const firstName = auth.user.name.split(' ')[0];

    const tiles = [
        {
            title: 'Mitteilungen',
            value: stats.announcements,
            href: '/mitteilungen',
            icon: Megaphone,
        },
        {
            title: 'Kommende Termine',
            value: stats.events,
            href: '/termine',
            icon: CalendarDays,
        },
        {
            title: 'Dokumente & Links',
            value: stats.documents,
            href: '/dokumente',
            icon: FileText,
        },
        {
            title: 'Personen',
            value: stats.people,
            href: '/personen',
            icon: Users,
        },
    ];

    return (
        <>
            <Head title="Start" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Guten Tag, {firstName}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {formatDate(new Date().toISOString())}
                    </p>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {tiles.map((tile) => (
                        <Link
                            key={tile.href}
                            href={tile.href}
                            prefetch
                            className="group rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                        >
                            <tile.icon className="mb-3 size-5 text-muted-foreground" />
                            <div className="text-2xl font-semibold tabular-nums">
                                {tile.value}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {tile.title}
                            </div>
                        </Link>
                    ))}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Aktuelle Mitteilungen</CardTitle>
                            <CardDescription>
                                Angeheftete zuerst, dann die neuesten
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {announcements.length === 0 && (
                                <EmptyState
                                    icon={Megaphone}
                                    title="Keine Mitteilungen"
                                />
                            )}
                            {announcements.map((item) => (
                                <Link
                                    key={item.id}
                                    href={`/mitteilungen/${item.id}`}
                                    className="-mx-2 grid gap-1 rounded-lg p-2 hover:bg-accent"
                                >
                                    <div className="flex flex-wrap items-center gap-2">
                                        {item.pinned && (
                                            <Pin className="size-4 text-amber-600" />
                                        )}
                                        <span className="font-medium">
                                            {item.title}
                                        </span>
                                        <Badge variant="secondary">
                                            {label(
                                                options.announcementCategories,
                                                item.category,
                                            )}
                                        </Badge>
                                    </div>
                                    <p className="line-clamp-2 text-sm text-muted-foreground">
                                        {item.body}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDate(item.published_at)}
                                        {item.author_name &&
                                            ` · ${item.author_name}`}
                                    </p>
                                </Link>
                            ))}
                            <Button
                                variant="ghost"
                                size="sm"
                                className="justify-self-start"
                                asChild
                            >
                                <Link href="/mitteilungen">
                                    Alle Mitteilungen <ArrowRight />
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <div className="grid content-start gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Nächste Termine</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3">
                                {events.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Keine kommenden Termine.
                                    </p>
                                )}
                                {events.map((event) => (
                                    <div
                                        key={event.id}
                                        className="grid gap-0.5 border-l-2 border-primary/30 pl-3"
                                    >
                                        <span className="text-sm font-medium">
                                            {event.title}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {formatEventRange(
                                                event.starts_at,
                                                event.ends_at,
                                                event.all_day,
                                            )}
                                        </span>
                                        {event.location && (
                                            <span className="text-xs text-muted-foreground">
                                                {event.location}
                                            </span>
                                        )}
                                    </div>
                                ))}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="justify-self-start"
                                    asChild
                                >
                                    <Link href="/termine">
                                        Alle Termine <ArrowRight />
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Wichtige Links</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-1">
                                {links.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        Noch keine Links erfasst.
                                    </p>
                                )}
                                {links.map((link) => (
                                    <a
                                        key={link.id}
                                        href={link.url ?? '#'}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="-mx-2 flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-accent"
                                    >
                                        <ExternalLink className="size-4 text-muted-foreground" />
                                        {link.title}
                                    </a>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Start', href: '/start' }],
};
