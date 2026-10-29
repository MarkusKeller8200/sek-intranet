import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import SelectField from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useOptions } from '@/hooks/use-options';
import { formatFileSize } from '@/lib/format';
import type { DocumentItem } from '@/types';

type DocumentFormData = {
    title: string;
    description: string;
    category: string;
    kind: 'file' | 'link';
    url: string;
    file: File | null;
};

export default function DocumentForm({
    document,
}: {
    document: DocumentItem | null;
}) {
    const options = useOptions();
    const form = useForm<DocumentFormData>({
        title: document?.title ?? '',
        description: document?.description ?? '',
        category: document?.category ?? 'links',
        kind: document?.kind ?? 'link',
        url: document?.url ?? '',
        file: null,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (document) {
            // Datei-Uploads gehen als multipart POST; Laravel liest _method=PUT.
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(`/dokumente/${document.id}`, { forceFormData: true });
        } else {
            form.post('/dokumente', { forceFormData: true });
        }
    };

    return (
        <>
            <Head title={document ? 'Eintrag bearbeiten' : 'Neuer Eintrag'} />
            <div className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={
                        document
                            ? 'Eintrag bearbeiten'
                            : 'Neues Dokument oder Link'
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

                            <div className="grid gap-5 md:grid-cols-2">
                                <FormField
                                    id="category"
                                    label="Kategorie"
                                    error={form.errors.category}
                                >
                                    <SelectField
                                        id="category"
                                        value={form.data.category}
                                        options={options.documentCategories}
                                        onChange={(value) =>
                                            form.setData('category', value)
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="kind"
                                    label="Art"
                                    error={form.errors.kind}
                                >
                                    <SelectField
                                        id="kind"
                                        value={form.data.kind}
                                        options={{
                                            link: 'Link auf Webseite',
                                            file: 'Datei hochladen',
                                        }}
                                        onChange={(value) =>
                                            form.setData(
                                                'kind',
                                                value === 'file'
                                                    ? 'file'
                                                    : 'link',
                                            )
                                        }
                                    />
                                </FormField>
                            </div>

                            {form.data.kind === 'link' ? (
                                <FormField
                                    id="url"
                                    label="Link"
                                    error={form.errors.url}
                                >
                                    <Input
                                        id="url"
                                        type="url"
                                        placeholder="https://"
                                        value={form.data.url}
                                        onChange={(e) =>
                                            form.setData('url', e.target.value)
                                        }
                                        aria-invalid={!!form.errors.url}
                                    />
                                </FormField>
                            ) : (
                                <FormField
                                    id="file"
                                    label="Datei"
                                    error={form.errors.file}
                                    hint={
                                        document?.kind === 'file' &&
                                        document.file_name
                                            ? `Aktuell: ${document.file_name} (${formatFileSize(document.file_size)}). Neue Datei wählen, um sie zu ersetzen.`
                                            : 'Max. 20 MB. Keine Personendaten hochladen (Prototyp).'
                                    }
                                >
                                    <Input
                                        id="file"
                                        type="file"
                                        onChange={(e) =>
                                            form.setData(
                                                'file',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                        aria-invalid={!!form.errors.file}
                                    />
                                </FormField>
                            )}

                            <FormField
                                id="description"
                                label="Beschreibung (optional)"
                                error={form.errors.description}
                            >
                                <Textarea
                                    id="description"
                                    rows={3}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            {form.progress && (
                                <progress
                                    value={form.progress.percentage}
                                    max={100}
                                    className="w-full"
                                />
                            )}

                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Speichern
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href="/dokumente">Abbrechen</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DocumentForm.layout = {
    breadcrumbs: [{ title: 'Dokumente & Links', href: '/dokumente' }],
};
