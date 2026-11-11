import PersonLinksCard from '@/components/person-links-card';
import type { PersonLink } from '@/components/person-links-card';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    FileText,
    History as HistoryIcon,
    Lock,
    Mail,
    Pencil,
    Phone,
    Undo2,
} from 'lucide-react';
import { text } from '@/components/data-grid';
import DeleteButton from '@/components/delete-button';
import OptionBadge from '@/components/option-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { displayValue } from '@/lib/person-fields';
import type { Person, PersonField, PersonGroup } from '@/types';

function Value({ field, person }: { field: PersonField; person: Person }) {
    const value = person[field.key];
    const shown = displayValue(field, value);

    if (shown === '') {
        return <span className="text-muted-foreground/60">–</span>;
    }
    if (field.type === 'email') {
        return (
            <a
                className="text-primary hover:underline"
                href={`mailto:${shown}`}
            >
                {shown}
            </a>
        );
    }
    if (field.input === 'tel') {
        return (
            <a
                className="text-primary hover:underline"
                href={`tel:${shown.replace(/\s/g, '')}`}
            >
                {shown}
            </a>
        );
    }
    if (field.key === 'school') {
        return <OptionBadge list="schools" value={person.school} dot />;
    }
    if (field.key === 'role') {
        return <OptionBadge list="roles" value={person.role} />;
    }

    return <span className="whitespace-pre-line">{shown}</span>;
}

type HistoryEntry = {
    id: string;
    created_at: string;
    action: 'created' | 'updated' | 'deleted';
    changes: Record<string, { old: unknown; new: unknown }>;
    source: string;
    user_name: string | null;
};

const ACTIONS = {
    created: 'Angelegt',
    updated: 'Geändert',
    deleted: 'Gelöscht',
};

function History({
    person,
    groups,
    history,
}: {
    person: Person;
    groups: PersonGroup[];
    history: HistoryEntry[];
}) {
    const fields = new Map(
        groups.flatMap((g) => g.fields).map((f) => [f.key as string, f]),
    );
    const show = (key: string, value: unknown) => {
        const field = fields.get(key);
        const shown = field ? displayValue(field, value) : text(value);

        return shown === '' ? '–' : shown;
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <HistoryIcon className="size-4" /> Änderungsverlauf
                    <Lock
                        className="size-4 text-muted-foreground"
                        aria-label="Nur Administration"
                    />
                </CardTitle>
                <CardDescription>
                    Wer hat wann was geändert. Ein früherer Wert lässt sich
                    wiederherstellen.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {history.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Änderungen erfasst.
                    </p>
                ) : (
                    <ol className="grid gap-3 text-sm">
                        {history.map((entry) => (
                            <li
                                key={entry.id}
                                className="rounded-lg border p-3"
                            >
                                <div className="flex flex-wrap items-baseline gap-x-2 text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        {ACTIONS[entry.action]}
                                    </span>
                                    <span>
                                        {formatDateTime(entry.created_at)}
                                    </span>
                                    <span>· {entry.user_name ?? 'System'}</span>
                                    <span>· {entry.source}</span>
                                </div>
                                {entry.action === 'updated' && (
                                    <ul className="mt-2 grid gap-1">
                                        {Object.entries(entry.changes).map(
                                            ([key, c]) => (
                                                <li
                                                    key={key}
                                                    className="flex flex-wrap items-center gap-2"
                                                >
                                                    <span className="text-muted-foreground">
                                                        {fields.get(key)
                                                            ?.label ?? key}
                                                        :
                                                    </span>
                                                    <span className="line-through opacity-70">
                                                        {show(key, c.old)}
                                                    </span>
                                                    <span>
                                                        → {show(key, c.new)}
                                                    </span>
                                                    {fields.has(key) &&
                                                        !fields.get(key)
                                                            ?.computed && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                className="h-6 px-2 text-xs"
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/personen/${person.id}/verlauf/${entry.id}/wiederherstellen`,
                                                                        {
                                                                            field: key,
                                                                        },
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <Undo2 />{' '}
                                                                Wiederherstellen
                                                            </Button>
                                                        )}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}

type PersonDocument = {
    module: string;
    title: string;
    date: string | null;
    url: string;
    kind: string | null;
};

export default function PersonShow({
    person,
    groups,
    history,
    links = [],
    systems = {},
    canManageLinks = false,
    documents = [],
}: {
    person: Person;
    groups: PersonGroup[];
    history: HistoryEntry[];
    links?: PersonLink[];
    systems?: Record<string, string>;
    canManageLinks?: boolean;
    /** Dokumente aus Modulen (#208), z. B. unterschriebene Monatsrapporte */
    documents?: PersonDocument[];
}) {
    const { auth } = usePage().props;
    const canManage = auth.can.manageUsers;
    const subtitle = [person.title, person.function]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={person.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-4">
                        <div
                            className="flex size-14 shrink-0 items-center justify-center rounded-full bg-muted text-lg font-semibold text-primary"
                            aria-hidden
                        >
                            {person.short ??
                                `${person.first_name?.[0] ?? ''}${person.last_name?.[0] ?? person.name[0] ?? ''}`}
                        </div>
                        <div className="space-y-1">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {person.name}
                            </h1>
                            {subtitle && (
                                <p className="text-sm text-muted-foreground">
                                    {subtitle}
                                </p>
                            )}
                            <div className="flex flex-wrap items-center gap-2 pt-1">
                                <OptionBadge
                                    list="schools"
                                    value={person.school}
                                    dot
                                />
                                {canManage && (
                                    <OptionBadge
                                        list="roles"
                                        value={person.role}
                                    />
                                )}
                                {!person.active && (
                                    <Badge variant="outline">inaktiv</Badge>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/personen">
                                <ArrowLeft /> Zur Liste
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={`mailto:${person.email}`}>
                                <Mail /> E-Mail
                            </a>
                        </Button>
                        {person.phone && (
                            <Button variant="outline" asChild>
                                <a
                                    href={`tel:${person.phone.replace(/\s/g, '')}`}
                                >
                                    <Phone /> Anrufen
                                </a>
                            </Button>
                        )}
                        {canManage && (
                            <Button asChild>
                                <Link
                                    href={`/personen/${person.id}/bearbeiten`}
                                >
                                    <Pencil /> Bearbeiten
                                </Link>
                            </Button>
                        )}
                        {canManage && person.id !== auth.user.id && (
                            <DeleteButton
                                url={`/personen/${person.id}`}
                                title={`${person.name} löschen?`}
                            />
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {groups.map((group) => (
                        <Card key={group.key}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    {group.label}
                                    {group.admin && (
                                        <Lock
                                            className="size-4 text-muted-foreground"
                                            aria-label="Nur Administration"
                                        />
                                    )}
                                </CardTitle>
                                {group.description && (
                                    <CardDescription>
                                        {group.description}
                                    </CardDescription>
                                )}
                            </CardHeader>
                            <CardContent>
                                <dl className="grid grid-cols-[minmax(8rem,auto)_1fr] gap-x-4 gap-y-2 text-sm">
                                    {group.fields
                                        .filter((field) => !field.computed)
                                        .map((field) => (
                                            <div
                                                key={field.key}
                                                className="contents"
                                            >
                                                <dt className="text-muted-foreground">
                                                    {field.label}
                                                </dt>
                                                <dd
                                                    className="min-w-0 break-words"
                                                    translate={
                                                        field.type === 'enum' ||
                                                        field.type === 'bool'
                                                            ? undefined
                                                            : 'no'
                                                    }
                                                >
                                                    <Value
                                                        field={field}
                                                        person={person}
                                                    />
                                                </dd>
                                            </div>
                                        ))}
                                </dl>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {documents.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="size-4" aria-hidden />
                                Dokumente
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2 text-sm">
                                {documents.map((d) => (
                                    <li
                                        key={d.url}
                                        className="flex flex-wrap items-center gap-x-3 gap-y-1"
                                    >
                                        <a
                                            href={d.url}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {d.title}
                                        </a>
                                        {d.kind && (
                                            <Badge variant="outline">
                                                {d.kind}
                                            </Badge>
                                        )}
                                        {d.date && (
                                            <span className="text-muted-foreground">
                                                {formatDateTime(d.date)}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {(links.length > 0 || canManageLinks) && (
                    <PersonLinksCard
                        personId={person.id}
                        links={links}
                        systems={systems}
                        canManage={canManageLinks}
                    />
                )}

                {canManage && (
                    <History
                        person={person}
                        groups={groups}
                        history={history}
                    />
                )}
            </div>
        </>
    );
}

PersonShow.layout = (props: { person: Person }) => ({
    breadcrumbs: [
        props.person.person_type === 'schueler'
            ? { title: 'Schüler:innen', href: '/schueler' }
            : { title: 'Personen', href: '/personen' },
        { title: props.person.name, href: `/personen/${props.person.id}` },
    ],
});
