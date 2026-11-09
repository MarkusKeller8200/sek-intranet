import { Head, router, useForm } from '@inertiajs/react';
import {
    Bell,
    CheckCircle2,
    Download,
    GitCompare,
    Printer,
    Undo2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import EintraegeTabelle from '../components/eintraege-tabelle';
import MonatNavigation from '../components/monat-navigation';
import StatusBadge from '../components/status-badge';
import UnterschriftKarte from '../components/unterschrift';
import type {
    Eintrag,
    Kategorien,
    Status,
    Pruefung,
    Summen,
    Unterschrift,
} from '../lib/types';
import { istGesperrt, tageListe } from '../lib/types';

type Props = {
    monat: string;
    person: { id: string; name: string; email: string };
    eintraege: Eintrag[];
    status: Status;
    summen: Summen;
    kategorien: Kategorien;
    /** Feiertage des Monats (#102): Datum → Name */
    feiertage?: Record<string, string>;
    /** Sollzeit pro Tag (#103): Datum → Stunden */
    soll?: Record<string, number>;
    visiertVon: string | null;
    unterschrift: Unterschrift;
    /** Abgelegtes Monatsblatt (#34) */
    monatsblatt?: string | null;
    /** ClickTime führt die Zeiten der Person (#164) */
    clicktime?: boolean;
    abgeschlossenVon?: string | null;
    /** Prüfhinweise je Tag (#167) */
    pruefungen?: Record<string, Pruefung[]>;
    /** Arbeitstage ohne Eintrag (#33) */
    fehlend?: string[];
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
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={person.name}
                    description="Monatsblatt für das Visum"
                />
                <div className="flex flex-wrap items-center gap-3">
                    <MonatNavigation
                        monat={monat}
                        basis={`/zeiterfassung/visum/${person.id}`}
                    />
                    <StatusBadge
                        status={status.status}
                        labels={kategorien.status}
                    />
                    {props.visiertVon && (
                        <span
                            className="text-sm text-muted-foreground"
                            translate="no"
                        >
                            {props.visiertVon}
                        </span>
                    )}
                    <span className="ml-auto flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={`/zeiterfassung/rapport/${person.id}/${monat}`}
                                target="_blank"
                                rel="noopener"
                            >
                                <Printer /> Monatsrapport (PDF)
                            </a>
                        </Button>
                        {props.monatsblatt && (
                            <Button asChild variant="outline">
                                <a href={props.monatsblatt}>
                                    <Download /> Abgelegtes Monatsblatt
                                </a>
                            </Button>
                        )}
                        {props.clicktime ? (
                            <>
                                {status.status === 'abgeschlossen' && (
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            router.post(
                                                `/zeiterfassung/visum/${person.id}/vergleichen`,
                                                { monat },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <GitCompare /> Mit ClickTime vergleichen
                                    </Button>
                                )}
                                <Button
                                    disabled={istGesperrt(status.status)}
                                    onClick={() =>
                                        router.post(
                                            `/zeiterfassung/visum/${person.id}/abschliessen`,
                                            { monat },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <CheckCircle2 /> Stellvertretend
                                    abschliessen
                                </Button>
                            </>
                        ) : (
                            <Button
                                disabled={status.status !== 'eingereicht'}
                                onClick={() =>
                                    router.post(
                                        `/zeiterfassung/visum/${person.id}/visieren`,
                                        { monat },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCircle2 /> Visieren
                            </Button>
                        )}
                    </span>
                </div>
                {props.clicktime && (
                    <Alert>
                        <AlertTitle>
                            {status.status === 'abgeschlossen'
                                ? `In ClickTime abgeschlossen – bestätigt von ${props.abgeschlossenVon ?? '–'}${status.abgeschlossen_stellvertretend ? ' (stellvertretend)' : ''}`
                                : 'ClickTime führt die Zeiten dieser Person'}
                        </AlertTitle>
                        <AlertDescription>
                            {status.status === 'abgeschlossen'
                                ? 'Abschluss und Genehmigung laufen in ClickTime. Nachbuchungen danach mit «Mit ClickTime vergleichen» prüfen; zum Übernehmen den Monat zurückgeben.'
                                : 'Die Person schliesst den Monat in ClickTime ab und bestätigt es hier. Im Notfall stellvertretend abschliessen – das wird vermerkt.'}
                        </AlertDescription>
                    </Alert>
                )}
                {status.abweichung && status.abweichung.anzahl > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>Nachbuchungen in ClickTime</AlertTitle>
                        <AlertDescription>
                            {`${status.abweichung.neu} neu, ${status.abweichung.geaendert} geändert, ${status.abweichung.geloescht} gelöscht seit dem Abschluss.`}
                        </AlertDescription>
                    </Alert>
                )}
                {(props.fehlend ?? []).length > 0 &&
                    !istGesperrt(status.status) && (
                        <Alert>
                            <Bell />
                            <AlertTitle>Arbeitstage ohne Eintrag</AlertTitle>
                            <AlertDescription>
                                {tageListe(props.fehlend ?? [])}
                            </AlertDescription>
                        </Alert>
                    )}
                {status.status === 'zurueck' && status.bemerkung && (
                    <Alert>
                        <AlertTitle>Zurückgegeben mit Bemerkung</AlertTitle>
                        <AlertDescription>
                            <span translate="no">{status.bemerkung}</span>
                        </AlertDescription>
                    </Alert>
                )}
                {(status.status === 'visiert' ||
                    status.status === 'abgeschlossen' ||
                    props.unterschrift.status) && (
                    <UnterschriftKarte
                        personId={person.id}
                        monat={monat}
                        visiert={
                            status.status === 'visiert' ||
                            status.status === 'abgeschlossen'
                        }
                        unterschrift={props.unterschrift}
                    />
                )}
                <Card>
                    <CardContent>
                        <EintraegeTabelle
                            monat={monat}
                            feiertage={props.feiertage}
                            soll={props.soll}
                            pruefungen={props.pruefungen}
                            eintraege={eintraege}
                            summen={summen}
                            kategorien={kategorien}
                        />
                    </CardContent>
                </Card>
                {istGesperrt(status.status) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Zurück an die Person</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={zurueck} className="grid gap-3">
                                <FormField
                                    id="z-bemerkung"
                                    label="Was soll korrigiert werden? *"
                                    error={form.errors.bemerkung}
                                >
                                    <Textarea
                                        id="z-bemerkung"
                                        rows={2}
                                        value={form.data.bemerkung}
                                        onChange={(e) =>
                                            form.setData(
                                                'bemerkung',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </FormField>
                                <div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={form.processing}
                                    >
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
