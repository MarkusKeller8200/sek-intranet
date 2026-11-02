import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDateTime } from '@/lib/format';

type Eintrag = {
    id: string;
    titel: string;
    notiz?: string | null;
    created_at: string;
};

/** Beispielmodul: Startseite des Moduls (Gerüst – an das Fachthema anpassen). */
export default function Start({ eintraege }: { eintraege: Eintrag[] }) {
    const form = useForm({ titel: '', notiz: '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/beispiel', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    return (
        <>
            <Head title="Beispielmodul" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title="Beispielmodul" description="Modul Beispielmodul" />
                <Card>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                            <FormField id="beispiel-titel" label="Titel *" error={form.errors.titel}>
                                <Input
                                    id="beispiel-titel"
                                    value={form.data.titel}
                                    onChange={(e) => form.setData('titel', e.target.value)}
                                />
                            </FormField>
                            <Button type="submit" disabled={form.processing}>
                                Speichern
                            </Button>
                        </form>
                    </CardContent>
                </Card>
                {eintraege.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Noch keine Einträge.</p>
                ) : (
                    <ul className="grid gap-2">
                        {eintraege.map((e) => (
                            <li key={e.id} className="rounded-md border p-3 text-sm">
                                <span className="font-medium" translate="no">
                                    {e.titel}
                                </span>
                                <span className="ml-2 text-xs text-muted-foreground">
                                    {formatDateTime(e.created_at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Start.layout = {
    breadcrumbs: [{ title: 'Beispielmodul', href: '/beispiel' }],
};
