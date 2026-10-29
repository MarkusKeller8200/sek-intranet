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
import { Textarea } from '@/components/ui/textarea';
import { useOptions } from '@/hooks/use-options';
import { toInputValue } from '@/lib/format';
import type { IntranetEvent } from '@/types';

export default function EventForm({ event }: { event: IntranetEvent | null }) {
    const options = useOptions();
    const allDay = event?.all_day ?? false;

    const form = useForm({
        title: event?.title ?? '',
        description: event?.description ?? '',
        category: event?.category ?? 'termin',
        school: event?.school ?? 'gesamt',
        location: event?.location ?? '',
        all_day: allDay,
        starts_at: toInputValue(event?.starts_at, !allDay),
        ends_at: toInputValue(event?.ends_at, !allDay),
    });

    const toggleAllDay = (checked: boolean) => {
        // Format der Datumsfelder an den Modus anpassen.
        form.setData((data) => ({
            ...data,
            all_day: checked,
            starts_at: checked
                ? data.starts_at.slice(0, 10)
                : data.starts_at && `${data.starts_at.slice(0, 10)}T08:00`,
            ends_at: checked
                ? data.ends_at.slice(0, 10)
                : data.ends_at && `${data.ends_at.slice(0, 10)}T17:00`,
        }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (event) {
            form.put(`/termine/${event.id}`);
        } else {
            form.post('/termine');
        }
    };

    const inputType = form.data.all_day ? 'date' : 'datetime-local';

    return (
        <>
            <Head title={event ? 'Termin bearbeiten' : 'Neuer Termin'} />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={event ? 'Termin bearbeiten' : 'Neuer Termin'}
                />

                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-5">
                            <FormField
                                id="title"
                                label="Titel"
                                error={form.errors.title}
                            >
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    aria-invalid={!!form.errors.title}
                                    autoFocus
                                />
                            </FormField>

                            <div className="grid gap-5 md:grid-cols-2">
                                <FormField
                                    id="category"
                                    label="Kategorie"
                                    error={form.errors.category}
                                >
                                    <SelectField
                                        id="category"
                                        value={form.data.category}
                                        options={options.eventCategories}
                                        onChange={(value) =>
                                            form.setData('category', value)
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

                                <div className="flex items-center gap-2 md:col-span-2">
                                    <Checkbox
                                        id="all_day"
                                        checked={form.data.all_day}
                                        onCheckedChange={(
                                            checked: boolean | 'indeterminate',
                                        ) => toggleAllDay(checked === true)}
                                    />
                                    <Label htmlFor="all_day">Ganztägig</Label>
                                </div>

                                <FormField
                                    id="starts_at"
                                    label="Beginn"
                                    error={form.errors.starts_at}
                                >
                                    <Input
                                        id="starts_at"
                                        type={inputType}
                                        value={form.data.starts_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'starts_at',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!form.errors.starts_at}
                                    />
                                </FormField>
                                <FormField
                                    id="ends_at"
                                    label="Ende (optional)"
                                    error={form.errors.ends_at}
                                >
                                    <Input
                                        id="ends_at"
                                        type={inputType}
                                        value={form.data.ends_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'ends_at',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={!!form.errors.ends_at}
                                    />
                                </FormField>
                            </div>

                            <FormField
                                id="location"
                                label="Ort"
                                error={form.errors.location}
                            >
                                <Input
                                    id="location"
                                    value={form.data.location}
                                    onChange={(e) =>
                                        form.setData('location', e.target.value)
                                    }
                                />
                            </FormField>

                            <FormField
                                id="description"
                                label="Beschreibung"
                                error={form.errors.description}
                            >
                                <Textarea
                                    id="description"
                                    rows={4}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Speichern
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/termine">Abbrechen</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

EventForm.layout = {
    breadcrumbs: [{ title: 'Termine', href: '/termine' }],
};
