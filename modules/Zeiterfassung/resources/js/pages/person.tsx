import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Printer, Undo2 } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import EintraegeTabelle from '../components/eintraege-tabelle';
import MonatNavigation from '../components/monat-navigation';
import StatusBadge from '../components/status-badge';
import { druckeMonatsblatt } from '../lib/monatsblatt';
import type { Eintrag, Kategorien, Status, Summen } from '../lib/types';

type Props = {
    monat: string;
    person: { id: string; name: string; email: string };
    eintraege: Eintrag[];
    status: Status;
    summen: Summen;
    kategorien: Kategorien;
    visiertVon: string | null;
};

/** Monatsblatt einer Person für das Visum: visieren oder mit Bemerkung zurückgeben. */
export default function ZeiterfassungPerson(props: Props) {
    const { monat, person, eintraege, status, summen, kategorien } = props;
    const form = useForm({ monat, bemerkung: '' });

    const zurueck = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/zeiterfassung/visum/${person.id}/zurueck`, {
            preserveScroll: true,
            onSuccess: () => form.reset('bemerkung'),
        });
    };

    return (
        <>
            <Head title={`Zeiterfassung ${person.name}`} />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader title={person.name} description="Monatsblatt für das Visum" />
                <div className="flex flex-wrap items-center gap-3">
                    <MonatNavigation monat={monat} basis={`/zeiterfassung/visum/${person.id}`} />
                    <StatusBadge status={status.status} labels={kategorien.status} />
                    {props.visiertVon && (
                        <span className="text-sm text-muted-foreground" translate="no">
                            {props.visiertVon}
                        </span>
                    )}
                    <span className="ml-auto flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() =>
                                druckeMonatsblatt({
                                    person: person.name,
                                    monat,
                                    eintraege,
                                    summen,
                                    status,
                                    kategorien,
                                    visiertVon: props.visiertVon,
                                })
                            }
                        >
                            <Printer /> Monatsblatt (PDF)
                        </Button>
                        <Button
                            disabled={status.status !== 'eingereicht'}
                            onClick={() =>
                                router.post(`/zeiterfassung/visum/${person.id}/visieren`, { monat }, { preserveScroll: true })
                            }
                        >
                            <CheckCircle2 /> Visieren
                        </Button>
                    </span>
                </div>
                {status.status === 'zurueck' && status.bemerkung && (
                    <Alert>
                        <AlertTitle>Zurückgegeben mit Bemerkung</AlertTitle>
                        <AlertDescription>
                            <span translate="no">{status.bemerkung}</span>
                        </AlertDescription>
                    </Alert>
                )}
                <Card>
                    <CardContent>
                        <EintraegeTabelle eintraege={eintraege} summen={summen} kategorien={kategorien} />
                    </CardContent>
                </Card>
                {(status.status === 'eingereicht' || status.status === 'visiert') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Zurück an die Person</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={zurueck} className="grid gap-3">
                                <FormField id="z-bemerkung" label="Was soll korrigiert werden? *" error={form.errors.bemerkung}>
                                    <Textarea
                                        id="z-bemerkung"
                                        rows={2}
                                        value={form.data.bemerkung}
                                        onChange={(e) => form.setData('bemerkung', e.target.value)}
                                    />
                                </FormField>
                                <div>
                                    <Button type="submit" variant="outline" disabled={form.processing}>
                                        <Undo2 /> Zurückgeben
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ZeiterfassungPerson.layout = {
    breadcrumbs: [
        { title: 'Zeiterfassung', href: '/zeiterfassung' },
        { title: 'Visum', href: '/zeiterfassung/visum' },
    ],
};
