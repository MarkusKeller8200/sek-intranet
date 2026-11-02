import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Clock, History, Pencil, Pin } from 'lucide-react';
import DeleteButton from '@/components/delete-button';
import OptionBadge from '@/components/option-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import type { Announcement } from '@/types';

export default function AnnouncementShow({
    announcement,
}: {
    announcement: Announcement;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={announcement.title} />
            <article className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/mitteilungen">
                            <ArrowLeft /> Alle Mitteilungen
                        </Link>
                    </Button>
                    {auth.can.manageContent && (
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={`/mitteilungen/${announcement.id}/bearbeiten`}
                                >
                                    <Pencil /> Bearbeiten
                                </Link>
                            </Button>
                            <DeleteButton
                                url={`/mitteilungen/${announcement.id}`}
                                title="Mitteilung löschen?"
                            />
                        </div>
                    )}
                </div>

                <header className="space-y-3">
                    <div className="flex flex-wrap gap-2">
                        {announcement.pinned && (
                            <Badge className="gap-1">
                                <Pin /> Angeheftet
                            </Badge>
                        )}
                        <OptionBadge
                            list="announcementCategories"
                            value={announcement.category}
                        />
                        <OptionBadge
                            list="audiences"
                            value={announcement.audience}
                            dot
                        />
                        {announcement.scheduled && (
                            <Badge
                                variant="outline"
                                className="gap-1 border-warning/30 bg-warning-soft text-warning"
                            >
                                <Clock /> geplant
                            </Badge>
                        )}
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                        <span translate="no">{announcement.title}</span>
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {formatDateTime(announcement.published_at)}
                        {announcement.author_name &&
                            ` · ${announcement.author_name}`}
                    </p>
                </header>

                <div
                    className="leading-relaxed whitespace-pre-line"
                    translate="no"
                >
                    {announcement.body}
                </div>
                {announcement.release_version && (
                    <div>
                        <Button asChild variant="outline">
                            <Link href="/versionen">
                                <History /> Alle Änderungen
                            </Link>
                        </Button>
                    </div>
                )}
            </article>
        </>
    );
}

AnnouncementShow.layout = {
    breadcrumbs: [{ title: 'Mitteilungen', href: '/mitteilungen' }],
};
