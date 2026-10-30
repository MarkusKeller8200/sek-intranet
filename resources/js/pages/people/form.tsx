import { Head, Link, useForm } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Person, PersonField, PersonGroup } from '@/types';

type FormValues = Record<string, string | boolean>;

const DEFAULTS: FormValues = {
    role: 'mitarbeitende',
    school: 'gesamt',
    active: true,
};

function initial(groups: PersonGroup[], person: Person | null): FormValues {
    const values: FormValues = {};
    for (const field of groups.flatMap((g) => g.fields)) {
        if (field.computed) {
            continue;
        }
        const current: unknown = person?.[field.key];
        values[field.key] =
            field.type === 'bool'
                ? typeof current === 'boolean'
                    ? current
                    : (DEFAULTS[field.key] ?? false)
                : typeof current === 'string'
                  ? current
                  : (DEFAULTS[field.key] ?? '');
    }

    return values;
}

export default function PersonForm({
    person,
    groups,
}: {
    person: Person | null;
    groups: PersonGroup[];
}) {
    const form = useForm<FormValues>(initial(groups, person));

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (person) {
            form.put(`/personen/${person.id}`);
        } else {
            form.post('/personen');
        }
    };

    const input = (field: PersonField) => {
        const id = `person-${field.key}`;
        const value = form.data[field.key];
        const error = form.errors[field.key];

        if (field.type === 'bool') {
            return (
                <div
                    key={field.key}
                    className="flex items-center gap-2 md:col-span-2"
                >
                    <Checkbox
                        id={id}
                        checked={value === true}
                        onCheckedChange={(checked: boolean | 'indeterminate') =>
                            form.setData(field.key, checked === true)
                        }
                    />
                    <Label htmlFor={id}>
                        {field.key === 'active'
                            ? 'Aktiv (kann sich anmelden, erscheint im Verzeichnis)'
                            : field.label}
                    </Label>
                </div>
            );
        }

        return (
            <FormField
                key={field.key}
                id={id}
                label={field.label + (field.required ? ' *' : '')}
                error={error}
                className={field.type === 'text' ? 'md:col-span-2' : undefined}
            >
                {field.type === 'enum' ? (
                    <SelectField
                        id={id}
                        value={String(value)}
                        options={field.options ?? {}}
                        invalid={!!error}
                        onChange={(v) => form.setData(field.key, v)}
                    />
                ) : field.type === 'text' ? (
                    <Textarea
                        id={id}
                        rows={3}
                        value={String(value)}
                        onChange={(e) =>
                            form.setData(field.key, e.target.value)
                        }
                    />
                ) : (
                    <Input
                        id={id}
                        type={
                            field.type === 'email' || field.type === 'date'
                                ? field.type
                                : field.input === 'tel'
                                  ? 'tel'
                                  : 'text'
                        }
                        value={String(value)}
                        aria-invalid={!!error}
                        autoFocus={field.key === 'first_name'}
                        onChange={(e) =>
                            form.setData(field.key, e.target.value)
                        }
                    />
                )}
            </FormField>
        );
    };

    return (
        <>
            <Head title={person ? 'Person bearbeiten' : 'Neue Person'} />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={person ? `${person.name} bearbeiten` : 'Neue Person'}
                    description="Prototyp: nur erfundene Testdaten erfassen, keine echten Personendaten."
                />

                <form onSubmit={submit} className="grid gap-4">
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
                            <CardContent className="grid gap-5 md:grid-cols-2">
                                {group.fields
                                    .filter((field) => !field.computed)
                                    .map(input)}
                            </CardContent>
                        </Card>
                    ))}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Speichern
                        </Button>
                        <Button variant="outline" asChild>
                            <Link
                                href={
                                    person
                                        ? `/personen/${person.id}`
                                        : '/personen'
                                }
                            >
                                Abbrechen
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [{ title: 'Personen', href: '/personen' }],
};
