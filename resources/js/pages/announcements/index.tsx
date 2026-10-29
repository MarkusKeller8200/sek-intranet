import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, Megaphone, Pin, Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import EmptyState from '@/components/empty-state';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import SelectField from '@/components/select-field';
import { useOptions } from '@/hooks/use-options';
import { formatDate } from '@/lib/format';
import type { Announcement } from '@/types';

export default function AnnouncementsIndex({
    announcements,
    filters,
}: {
    announcements: Announcement[];
    filters: { q: string; category: string };
}) {
    const { auth } = usePage().props;
    const options = useOptions();
    const [query, setQuery] = useState(filters.q);
    const [category, setCategory] = useState(filters.category || 'alle');

    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return announcements.filter(
            (item) =>
                (category === 'alle' || item.category === category) &&
                (needle === '' ||
                    item.title.toLowerCase().includes(needle) ||
                    item.body.toLowerCase().includes(needle)),
        );
    }, [announcements, query, category]);

    return (
        <>
            <Head title="Mitteilungen" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Mitteilungen"
                    description="Informationen für die Mitarbeitenden der Sekundarschule"
                    actions={
                        auth.can.manageContent && (
                            <Button asChild>
                                <Link href="/mitteilungen/neu">
                                    <Plus /> Neue Mitteilung
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-col gap-3 md:flex-row md:items-center">
                    <div className="relative md:w-72">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Suchen …"
                            className="pl-8"
                            aria-label="Mitteilungen durchsuchen"
                        />
                    </div>
                    <div className="md:w-56">
                        <SelectField
                            id="category-filter"
                            value={category}
                            options={{
                                alle: 'Alle Kategorien',
                                ...options.announcementCategories,
                            }}
                            onChange={setCategory}
                        />
                    </div>
                </div>

                {visible.length === 0 ? (
                    <EmptyState
                        icon={Megaphone}
                        title="Keine Mitteilungen gefunden"
                    >
                        Suchbegriff oder Kategorie anpassen.
                    </EmptyState>
                ) : (
                    <div className="grid gap-3">
                        {visible.map((item) => (
                            <Link
                                key={item.id}
                                href={`/mitteilungen/${item.id}`}
                                prefetch
                                className="grid gap-1.5 rounded-xl border bg-card p-4 transition-colors hover:bg-accent"
                            >
                                <div className="flex flex-wrap items-center gap-2">
                                    {item.pinned && (
                                        <Pin
                                            className="size-4 text-brand"
                                            aria-label="Angeheftet"
                                        />
                                    )}
                                    <h2 className="font-medium">
                                        {item.title}
                                    </h2>
                                    <OptionBadge
                                        list="announcementCategories"
                                        value={item.category}
                                    />
                                    <OptionBadge
                                        list="audiences"
                                        value={item.audience}
                                        dot
                                    />
                                    {item.scheduled && (
                                        <Badge
                                            variant="outline"
                                            className="gap-1 border-warning/30 bg-warning-soft text-warning"
                                        >
                                            <Clock /> geplant
                                        </Badge>
                                    )}
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
                    </div>
                )}
            </div>
        </>
    );
}

AnnouncementsIndex.layout = {
    breadcrumbs: [{ title: 'Mitteilungen', href: '/mitteilungen' }],
};
