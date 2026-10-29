import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useOptions } from '@/hooks/use-options';
import type { Person } from '@/types';

export default function PersonForm({ person }: { person: Person | null }) {
    const options = useOptions();
    const form = useForm({
        name: person?.name ?? '',
        email: person?.email ?? '',
        role: person?.role ?? 'mitarbeitende',
        function: person?.function ?? '',
        school: person?.school ?? 'gesamt',
        phone: person?.phone ?? '',
        active: person?.active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (person) {
            form.put(`/personen/${person.id}`);
        } else {
            form.post('/personen');
        }
    };

    return (
        <>
            <Head title={person ? 'Person bearbeiten' : 'Neue Person'} />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={person ? 'Person bearbeiten' : 'Neue Person'}
                    description="Prototyp: nur erfundene Testdaten erfassen, keine echten Personendaten."
                />

                <Card>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-5 md:grid-cols-2"
                        >
                            <FormField
                                id="name"
                                label="Name"
                                error={form.errors.name}
                            >
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    aria-invalid={!!form.errors.name}
                                    autoFocus
                                />
                            </FormField>
                            <FormField
                                id="email"
                                label="E-Mail"
                                error={form.errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    aria-invalid={!!form.errors.email}
                                />
                            </FormField>
                            <FormField
                                id="function"
                                label="Funktion"
                                error={form.errors.function}
                            >
                                <Input
                                    id="function"
                                    value={form.data.function}
                                    onChange={(e) =>
                                        form.setData('function', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="phone"
                                label="Telefon"
                                error={form.errors.phone}
                            >
                                <Input
                                    id="phone"
                                    type="tel"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="school"
                                label="Schulhaus"
                                error={form.errors.school}
                            >
                                <SelectField
                                    id="school"
                                    value={form.data.school}
                                    options={options.schools}
                                    onChange={(value) =>
                                        form.setData('school', value)
                                    }
                                />
                            </FormField>
                            <FormField
                                id="role"
                                label="Rolle im Intranet"
                                error={form.errors.role}
                            >
                                <SelectField
                                    id="role"
                                    value={form.data.role}
                                    options={options.roles}
                                    onChange={(value) =>
                                        form.setData('role', value)
                                    }
                                />
                            </FormField>
                            <div className="flex items-center gap-2 md:col-span-2">
                                <Checkbox
                                    id="active"
                                    checked={form.data.active}
                                    onCheckedChange={(
                                        checked: boolean | 'indeterminate',
                                    ) =>
                                        form.setData('active', checked === true)
                                    }
                                />
                                <Label htmlFor="active">
                                    Aktiv (kann sich anmelden, erscheint im
                                    Verzeichnis)
                                </Label>
                            </div>

                            <div className="flex gap-2 md:col-span-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Speichern
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/personen">Abbrechen</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

PersonForm.layout = {
    breadcrumbs: [{ title: 'Personen', href: '/personen' }],
};
