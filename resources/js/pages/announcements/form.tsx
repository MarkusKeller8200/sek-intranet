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
import { nowInputValue, toInputValue } from '@/lib/format';
import type { Announcement } from '@/types';

export default function AnnouncementForm({
    announcement,
}: {
    announcement: Announcement | null;
}) {
    const options = useOptions();
    const form = useForm({
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        category: announcement?.category ?? 'allgemein',
        audience: announcement?.audience ?? 'alle',
        pinned: announcement?.pinned ?? false,
        published_at: announcement
            ? toInputValue(announcement.published_at)
            : nowInputValue(),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (announcement) {
            form.put(`/mitteilungen/${announcement.id}`);
        } else {
            form.post('/mitteilungen');
        }
    };

    return (
        <>
            <Head
                title={
                    announcement ? 'Mitteilung bearbeiten' : 'Neue Mitteilung'
                }
            />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={
                        announcement
                            ? 'Mitteilung bearbeiten'
                            : 'Neue Mitteilung'
                    }
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

                            <FormField
                                id="body"
                                label="Text"
                                error={form.errors.body}
                            >
                                <Textarea
                                    id="body"
                                    rows={8}
                                    value={form.data.body}
                                    onChange={(e) =>
                                        form.setData('body', e.target.value)
                                    }
                                    aria-invalid={!!form.errors.body}
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
                                        options={options.announcementCategories}
                                        onChange={(value) =>
                                            form.setData('category', value)
                                        }
                                        invalid={!!form.errors.category}
                                    />
                                </FormField>
                                <FormField
                                    id="audience"
                                    label="Zielgruppe"
                                    error={form.errors.audience}
                                >
                                    <SelectField
                                        id="audience"
                                        value={form.data.audience}
                                        options={options.audiences}
                                        onChange={(value) =>
                                            form.setData('audience', value)
                                        }
                                        invalid={!!form.errors.audience}
                                    />
                                </FormField>
                                <FormField
                                    id="published_at"
                                    label="Veröffentlicht ab"
                                    hint="Liegt das Datum in der Zukunft, sieht nur die Redaktion die Mitteilung."
                                    error={form.errors.published_at}
                                >
                                    <Input
                                        id="published_at"
                                        type="datetime-local"
                                        value={form.data.published_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'published_at',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            !!form.errors.published_at
                                        }
                                    />
                                </FormField>
                                <div className="flex items-center gap-2 md:pt-7">
                                    <Checkbox
                                        id="pinned"
                                        checked={form.data.pinned}
                                        onCheckedChange={(
                                            checked: boolean | 'indeterminate',
                                        ) =>
                                            form.setData(
                                                'pinned',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="pinned">
                                        Oben anheften
                                    </Label>
                                </div>
                            </div>

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Speichern
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={
                                            announcement
                                                ? `/mitteilungen/${announcement.id}`
                                                : '/mitteilungen'
                                        }
                                    >
                                        Abbrechen
                                    </Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AnnouncementForm.layout = {
    breadcrumbs: [{ title: 'Mitteilungen', href: '/mitteilungen' }],
};
