import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import DeleteButton from '@/components/delete-button';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { SortableList } from '@/components/ui/sortable-list';
import { Textarea } from '@/components/ui/textarea';

type Gruppe = { id: string; name: string; bemerkung: string; verwendet: number };

/** Personengruppen für «gilt für» bei Schritten. */
export default function AblaeufeGruppen({ gruppen }: { gruppen: Gruppe[] }) {
    const [bearbeiten, setBearbeiten] = useState<Gruppe | null>(null);
    const form = useForm({ name: '', bemerkung: '' });

    const waehlen = (g: Gruppe | null) => {
        setBearbeiten(g);
        form.clearErrors();
        form.setData({ name: g?.name ?? '', bemerkung: g?.bemerkung ?? '' });
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        const fertig = { preserveScroll: true, onSuccess: () => waehlen(null) };
        if (bearbeiten) {
            form.put(`/ablaeufe/gruppen/${bearbeiten.id}`, fertig);
        } else {
            form.post('/ablaeufe/gruppen', fertig);
        }
    };

    return (
        <>
            <Head title="Personengruppen" />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Personengruppen"
                    description="Gruppen für «gilt für» bei Schritten, z. B. Lehrpersonen oder Vikare. Bemerkungen helfen beim Festlegen der Berechtigungen."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Gruppen</CardTitle>
                        <CardDescription>
                            Reihenfolge per Ziehen am Griff oder mit den Pfeilen.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SortableList
                            items={gruppen.map((g) => ({ id: g.id, label: g.name }))}
                            onChange={(items) =>
                                router.post(
                                    '/ablaeufe/gruppen/reihenfolge',
                                    { ids: items.map((i) => i.id) },
                                    { preserveScroll: true },
                                )
                            }
                            render={(item) => {
                                const g = gruppen.find((x) => x.id === item.id);
                                if (!g) {
                                    return null;
                                }

                                return (
                                    <span className="flex flex-1 flex-wrap items-center gap-2">
                                        <span className="font-medium" translate="no">
                                            {g.name}
                                        </span>
                                        {g.verwendet > 0 && (
                                            <Badge variant="outline">{`${g.verwendet} Schritte`}</Badge>
                                        )}
                                        {g.bemerkung && (
                                            <span className="basis-full text-xs text-muted-foreground" translate="no">
                                                {g.bemerkung}
                                            </span>
                                        )}
                                        <span className="ml-auto flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => waehlen(g)}
                                            >
                                                <Pencil /> Bearbeiten
                                            </Button>
                                            <DeleteButton
                                                url={`/ablaeufe/gruppen/${g.id}`}
                                                title={`Gruppe «${g.name}» entfernen?`}
                                                description="Die Gruppe wird auch aus allen Schritten entfernt («gilt für»)."
                                                size="icon"
                                            />
                                        </span>
                                    </span>
                                );
                            }}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {bearbeiten ? 'Gruppe bearbeiten' : 'Neue Gruppe'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4">
                            <FormField id="g-name" label="Name *" error={form.errors.name}>
                                <Input
                                    id="g-name"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                />
                            </FormField>
                            <FormField id="g-bemerkung" label="Bemerkung" error={form.errors.bemerkung}>
                                <Textarea
                                    id="g-bemerkung"
                                    rows={2}
                                    value={form.data.bemerkung}
                                    onChange={(e) => form.setData('bemerkung', e.target.value)}
                                />
                            </FormField>
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit" disabled={form.processing}>
                                    {bearbeiten ? (
                                        'Speichern'
                                    ) : (
                                        <>
                                            <Plus /> Hinzufügen
                                        </>
                                    )}
                                </Button>
                                {bearbeiten && (
                                    <Button type="button" variant="outline" onClick={() => waehlen(null)}>
                                        Abbrechen
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AblaeufeGruppen.layout = {
    breadcrumbs: [
        { title: 'Abläufe Verwaltung', href: '/ablaeufe' },
        { title: 'Personengruppen', href: '/ablaeufe/gruppen' },
    ],
};
