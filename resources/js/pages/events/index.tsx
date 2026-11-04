import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, MapPin, Pencil, Plus } from 'lucide-react';
import { useMemo } from 'react';
import DeleteButton from '@/components/delete-button';
import EmptyState from '@/components/empty-state';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import { TermText } from '@/components/term-text';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useColor } from '@/hooks/use-options';
import { formatEventRange, formatMonth } from '@/lib/format';
import type { IntranetEvent } from '@/types';

export default function EventsIndex({
    events,
    showPast,
}: {
    events: IntranetEvent[];
    showPast: boolean;
}) {
    const { auth } = usePage().props;
    const color = useColor();

    // Nach Monat gruppieren (Reihenfolge kommt sortiert vom Server).
    const months = useMemo(() => {
        const groups = new Map<string, IntranetEvent[]>();

        for (const event of events) {
            const key = formatMonth(event.starts_at);
            groups.set(key, [...(groups.get(key) ?? []), event]);
        }

        return [...groups.entries()];
    }, [events]);

    return (
        <>
            <Head title="Termine" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Termine"
                    description="Anlässe, Konferenzen, Weiterbildungen und Ferien"
                    actions={
                        auth.can.manageContent && (
                            <Button asChild>
                                <Link href="/termine/neu">
                                    <Plus /> Neuer Termin
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="past"
                        checked={showPast}
                        onCheckedChange={(checked: boolean | 'indeterminate') =>
                            router.get(
                                '/termine',
                                checked === true ? { vergangene: 1 } : {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    />
                    <Label htmlFor="past">Vergangene Termine anzeigen</Label>
                </div>

                {months.length === 0 && (
                    <EmptyState icon={CalendarDays} title="Keine Termine" />
                )}

                {months.map(([month, items]) => (
                    <section key={month} className="grid gap-3">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                            {month}
                        </h2>
                        {items.map((event) => (
                            <div
                                key={event.id}
                                className={`flex gap-3 rounded-xl border border-l-4 bg-card p-4 ${color('eventCategories', event.category).classes.bar}`}
                            >
                                <div className="grid flex-1 gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            <span translate="no">
                                                {event.title}
                                            </span>
                                        </span>
                                        <OptionBadge
                                            list="eventCategories"
                                            value={event.category}
                                        />
                                        <OptionBadge
                                            list="schools"
                                            value={event.school}
                                            dot
                                        />
                                    </div>
                                    <p className="text-sm">
                                        {formatEventRange(
                                            event.starts_at,
                                            event.ends_at,
                                            event.all_day,
                                        )}
                                    </p>
                                    {event.location && (
                                        <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                            <MapPin className="size-3.5" />{' '}
                                            {event.location}
                                        </p>
                                    )}
                                    {event.description && (
                                        <p
                                            className="text-sm whitespace-pre-line text-muted-foreground"
                                            translate="no"
                                        >
                                            <TermText
                                                text={event.description}
                                            />
                                        </p>
                                    )}
                                </div>
                                {auth.can.manageContent && (
                                    <div className="flex shrink-0 items-start gap-1">
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            asChild
                                        >
                                            <Link
                                                href={`/termine/${event.id}/bearbeiten`}
                                                aria-label="Bearbeiten"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                        <DeleteButton
                                            url={`/termine/${event.id}`}
                                            title="Termin löschen?"
                                            size="icon"
                                        />
                                    </div>
                                )}
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </>
    );
}

EventsIndex.layout = {
    breadcrumbs: [{ title: 'Termine', href: '/termine' }],
};
