import { Head, useForm } from '@inertiajs/react';
import { Plus, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

type Zeile = {
    key: string;
    name: string;
    art: 'arbeit' | 'abwesenheit';
    aktiv: boolean;
};

/**
 * Buchungsarten der Zeiterfassung pflegen (#99): Bezeichnung, Arbeit oder Abwesenheit, aktiv. Nicht mehr benötigte
 * Buchungsarten deaktivieren – bestehende Einträge behalten ihre Bezeichnung.
 */
export default function Buchungsarten({
    buchungsarten,
    arten,
}: {
    buchungsarten: Zeile[];
    arten: Record<string, string>;
}) {
    const form = useForm<{ zeilen: Zeile[] }>({ zeilen: buchungsarten });
    const setZeile = (i: number, patch: Partial<Zeile>) =>
        form.setData(
            'zeilen',
            form.data.zeilen.map((z, j) => (j === i ? { ...z, ...patch } : z)),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/zeiterfassung/buchungsarten', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Buchungsarten" />
            <form
                onSubmit={submit}
                className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title="Buchungsarten"
                    description="Arten für die Zeiterfassung, z. B. Arbeitszeit, Homeoffice, Ferien, Krankheit. Die Zuordnung zu ClickTime steht im Plugin ClickTime."
                    actions={
                        <Button type="submit" disabled={form.processing}>
                            <Save /> Speichern
                        </Button>
                    }
                />
                <InputError message={form.errors.zeilen} />
                <Card>
                    <CardContent className="grid gap-2 pt-6">
                        {form.data.zeilen.map((z, i) => (
                            <div
                                key={z.key || `neu-${i}`}
                                className="grid grid-cols-[1fr_10rem_auto] items-center gap-2"
                            >
                                <Input
                                    aria-label="Bezeichnung"
                                    value={z.name}
                                    onChange={(e) =>
                                        setZeile(i, { name: e.target.value })
                                    }
                                />
                                <NativeSelect
                                    aria-label="Art"
                                    value={z.art}
                                    onChange={(e) =>
                                        setZeile(i, {
                                            art: e.target.value as Zeile['art'],
                                        })
                                    }
                                >
                                    {Object.entries(arten).map(([k, l]) => (
                                        <option key={k} value={k}>
                                            {l}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={z.aktiv}
                                        onCheckedChange={(c) =>
                                            setZeile(i, { aktiv: c === true })
                                        }
                                    />
                                    aktiv
                                </label>
                            </div>
                        ))}
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    form.setData('zeilen', [
                                        ...form.data.zeilen,
                                        {
                                            key: '',
                                            name: '',
                                            art: 'abwesenheit',
                                            aktiv: true,
                                        },
                                    ])
                                }
                            >
                                <Plus /> Buchungsart hinzufügen
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

Buchungsarten.layout = {
    breadcrumbs: [
        { title: 'Zeiterfassung', href: '/zeiterfassung' },
        { title: 'Visum', href: '/zeiterfassung/visum' },
        { title: 'Buchungsarten', href: '/zeiterfassung/buchungsarten' },
    ],
};
