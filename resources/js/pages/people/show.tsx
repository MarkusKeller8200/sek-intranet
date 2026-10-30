import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Lock, Mail, Pencil, Phone } from 'lucide-react';
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

export default function PersonShow({
    person,
    groups,
}: {
    person: Person;
    groups: PersonGroup[];
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
                                                <dd className="min-w-0 break-words">
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
            </div>
        </>
    );
}

PersonShow.layout = (props: { person: Person }) => ({
    breadcrumbs: [
        { title: 'Personen', href: '/personen' },
        { title: props.person.name, href: `/personen/${props.person.id}` },
    ],
});
