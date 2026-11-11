import { Head, useForm } from '@inertiajs/react';
import { RotateCcw, Save } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
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

type Regeln = {
    kommen_fehlt: boolean;
    gehen_fehlt: boolean;
    keine_buchung: boolean;
    tagesist: boolean;
    tagesist_max: string;
    abweichung: boolean;
    abweichung_prozent: number;
    mittag: boolean;
    mittag_von: string;
    mittag_bis: string;
    mittag_min: number;
};

type Schalter =
    | 'kommen_fehlt'
    | 'gehen_fehlt'
    | 'keine_buchung'
    | 'tagesist'
    | 'abweichung'
    | 'mittag';

/**
 * Einstellungen der Zeiterfassung (#204): welche Prüfhinweise je Tag in «Meine Zeiterfassung» und im Visum erscheinen.
 */
export default function Einstellungen({
    regeln,
    vorgabe,
}: {
    regeln: Regeln;
    vorgabe: Regeln;
}) {
    const form = useForm<Regeln>(regeln);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/zeiterfassung/einstellungen', { preserveScroll: true });
    };

    const zeile = (
        feld: Schalter,
        titel: string,
        text: string,
        extra?: ReactNode,
    ) => (
        <div className="grid gap-2 border-b py-3 last:border-b-0 sm:grid-cols-[1fr_auto] sm:items-center">
            <label className="flex items-start gap-3">
                <Checkbox
                    className="mt-0.5"
                    checked={form.data[feld]}
                    onCheckedChange={(c) => form.setData(feld, c === true)}
                />
                <span className="grid gap-0.5">
                    <span className="text-sm font-medium">{titel}</span>
                    <span className="text-sm text-muted-foreground">
                        {text}
                    </span>
                </span>
            </label>
            {extra && (
                <div className="flex flex-wrap items-center gap-2 pl-7 text-sm sm:pl-0">
                    {extra}
                </div>
            )}
        </div>
    );

    return (
        <>
            <Head title="Zeiterfassung – Einstellungen" />
            <form
                onSubmit={submit}
                className="flex max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title="Zeiterfassung – Einstellungen"
                    description="Prüfhinweise je Tag in «Meine Zeiterfassung» und im Visum ein- und ausschalten."
                    actions={
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => form.setData(vorgabe)}
                            >
                                <RotateCcw /> Vorgabe
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Save /> Speichern
                            </Button>
                        </>
                    }
                />
                <InputError
                    message={(form.errors as Record<string, string>).regeln}
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Prüfhinweise</CardTitle>
                        <CardDescription>
                            Gilt für alle Personen, auch für Buchungen aus
                            ClickTime.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {zeile(
                            'kommen_fehlt',
                            'Kommen-Buchung fehlt anzeigen',
                            'Eintrag mit Ende, aber ohne Beginn.',
                        )}
                        {zeile(
                            'gehen_fehlt',
                            'Gehen-Buchung fehlt anzeigen',
                            'Eintrag mit Beginn, aber ohne Ende.',
                        )}
                        {zeile(
                            'keine_buchung',
                            'Keine Buchung vorhanden anzeigen',
                            'Arbeitstag mit Sollzeit bis heute ohne Eintrag.',
                        )}
                        {zeile(
                            'tagesist',
                            'Tagesist über Grenze anzeigen',
                            'Angerechnete Arbeitszeit eines Tages liegt über der Grenze.',
                            <>
                                <span>Grenze</span>
                                <Input
                                    type="time"
                                    aria-label="Grenze Tagesist"
                                    className="w-28"
                                    value={form.data.tagesist_max}
                                    disabled={!form.data.tagesist}
                                    onChange={(e) =>
                                        form.setData(
                                            'tagesist_max',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={form.errors.tagesist_max}
                                />
                            </>,
                        )}
                        {zeile(
                            'abweichung',
                            'Tagesdifferenz anzeigen',
                            'Tagesist weicht stärker vom Soll ab (ohne Kompensationstage).',
                            <>
                                <Input
                                    type="number"
                                    min={1}
                                    max={100}
                                    aria-label="Abweichung in Prozent"
                                    className="w-20"
                                    value={form.data.abweichung_prozent}
                                    disabled={!form.data.abweichung}
                                    onChange={(e) =>
                                        form.setData(
                                            'abweichung_prozent',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                                <span>% Abweichung</span>
                                <InputError
                                    message={form.errors.abweichung_prozent}
                                />
                            </>,
                        )}
                        {zeile(
                            'mittag',
                            'Keine Mittagsbuchung anzeigen',
                            'Gearbeitet über das Mittagsfenster ohne Lücke oder Pause von mindestens der Mindestpause.',
                            <>
                                <Input
                                    type="time"
                                    aria-label="Mittag von"
                                    className="w-28"
                                    value={form.data.mittag_von}
                                    disabled={!form.data.mittag}
                                    onChange={(e) =>
                                        form.setData(
                                            'mittag_von',
                                            e.target.value,
                                        )
                                    }
                                />
                                <span>bis</span>
                                <Input
                                    type="time"
                                    aria-label="Mittag bis"
                                    className="w-28"
                                    value={form.data.mittag_bis}
                                    disabled={!form.data.mittag}
                                    onChange={(e) =>
                                        form.setData(
                                            'mittag_bis',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Input
                                    type="number"
                                    min={5}
                                    max={180}
                                    aria-label="Mindestpause in Minuten"
                                    className="w-20"
                                    value={form.data.mittag_min}
                                    disabled={!form.data.mittag}
                                    onChange={(e) =>
                                        form.setData(
                                            'mittag_min',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                                <span>Min. Pause</span>
                            </>,
                        )}
                    </CardContent>
                </Card>
            </form>
        </>
    );
}

Einstellungen.layout = {
    breadcrumbs: [
        { title: 'Zeiterfassung', href: '/zeiterfassung' },
        { title: 'Einstellungen', href: '/zeiterfassung/einstellungen' },
    ],
};
