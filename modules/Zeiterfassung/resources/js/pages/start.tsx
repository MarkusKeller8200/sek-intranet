import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ClipboardCheck,
    CloudDownload,
    Download,
    Lock,
    Printer,
    Send,
} from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertDialog } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import EintraegeTabelle from '../components/eintraege-tabelle';
import MonatNavigation from '../components/monat-navigation';
import StatusBadge from '../components/status-badge';
import { UnterschriftBadge } from '../components/unterschrift';
import { druckeMonatsblatt } from '../lib/monatsblatt';
import type {
    Eintrag,
    Kategorien,
    Pruefung,
    Status,
    Summen,
    Unterschrift,
} from '../lib/types';
import { istGesperrt, monatName, tageListe } from '../lib/types';

type Props = {
    monat: string;
    eintraege: Eintrag[];
    status: Status;
    summen: Summen;
    kategorien: Kategorien;
    /** Feiertage des Monats (#102): Datum → Name */
    feiertage?: Record<string, string>;
    /** Sollzeit pro Tag (#103): Datum → Stunden */
    soll?: Record<string, number>;
    person: { name: string };
    darfVisum: boolean;
    unterschrift: Unterschrift;
    /** Knopf «Aus ClickTime übernehmen» (#101), null = nicht verfügbar */
    clicktime?: { nr: string } | null;
    /** ClickTime führt die Zeiten dieser Person (#154): keine eigenen Einträge */
    clicktimeFuehrt?: boolean;
    /** Abgelegtes Monatsblatt (#34) */
    monatsblatt?: string | null;
    /** Prüfhinweise je Tag (#167) */
    pruefungen?: Record<string, Pruefung[]>;
    /** Arbeitstage ohne Eintrag bis heute (#33) */
    fehlend?: string[];
};

/** Eigene Zeiterfassung eines Monats: erfassen, Übersicht, einreichen, Monatsblatt als PDF. */
export default function ZeiterfassungStart(props: Props) {
    const { monat, eintraege, status, summen, kategorien, person } = props;
    const fuehrt = props.clicktimeFuehrt === true;
    const gesperrt = istGesperrt(status.status);
    const fehlend = props.fehlend ?? [];
    // Rückfrage vor dem Einreichen bzw. Abschliessen (#33, #164)
    const [rueckfrage, setRueckfrage] = useState(false);
    const abschliessen = () =>
        router.post(
            fuehrt
                ? '/zeiterfassung/abschliessen'
                : '/zeiterfassung/einreichen',
            { monat },
            { preserveScroll: true },
        );
    const heute = new Date().toISOString().slice(0, 10);
    // Buchungsarten zur Auswahl (#99): aktive, nach Art gruppiert
    const buchungsarten = Object.entries(kategorien.buchungsarten ?? {}).filter(
        ([, b]) => b.aktiv,
    );
    const form = useForm({
        datum: heute.startsWith(monat) ? heute : `${monat}-01`,
        buchungsart: buchungsarten[0]?.[0] ?? 'arbeitszeit',
        kategorie: Object.keys(kategorien.arbeit)[0] ?? '',
        beginn: '',
        ende: '',
        pause_min: '',
        stunden: '',
        projekt: '',
        notiz: '',
    });
    // Eintrag bearbeiten (#102): nur von Hand erfasste, das Formular wird damit gefüllt
    const [bearbeite, setBearbeite] = useState<string | null>(null);
    const formularRef = useRef<HTMLDivElement>(null);
    const art =
        kategorien.buchungsarten?.[form.data.buchungsart]?.art ?? 'arbeit';
    const optionen = (['arbeit', 'abwesenheit'] as const).map((gruppe) => (
        <optgroup
            key={gruppe}
            label={gruppe === 'arbeit' ? 'Arbeit' : 'Abwesenheit'}
        >
            {buchungsarten
                .filter(([, b]) => b.art === gruppe)
                .map(([k, b]) => (
                    <option key={k} value={k}>
                        {b.name}
                    </option>
                ))}
        </optgroup>
    ));
    const taetigkeiten = Object.entries(kategorien.arbeit).map(([k, l]) => (
        <option key={k} value={k}>
            {l}
        </option>
    ));

    const leeren = () =>
        form.reset(
            'beginn',
            'ende',
            'pause_min',
            'stunden',
            'projekt',
            'notiz',
        );
    const bearbeiten = (e: Eintrag) => {
        setBearbeite(e.id);
        form.clearErrors();
        form.setData({
            datum: e.datum,
            buchungsart: e.buchungsart ?? form.data.buchungsart,
            kategorie: e.art === 'arbeit' ? e.kategorie : form.data.kategorie,
            beginn: e.beginn ?? '',
            ende: e.ende ?? '',
            pause_min: e.pause_min ? String(e.pause_min) : '',
            stunden: e.beginn ? '' : String(e.stunden),
            projekt: e.projekt ?? '',
            notiz: e.notiz ?? '',
        });
        formularRef.current?.scrollIntoView({ behavior: 'smooth' });
    };
    const abbrechen = () => {
        setBearbeite(null);
        form.clearErrors();
        leeren();
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const nachher = {
            preserveScroll: true,
            onSuccess: () => {
                setBearbeite(null);
                leeren();
            },
        };
        if (bearbeite) {
            form.put(`/zeiterfassung/eintraege/${bearbeite}`, nachher);
        } else {
            form.post('/zeiterfassung', nachher);
        }
    };

    return (
        <>
            <Head title="Zeiterfassung" />
            <div className="flex w-full flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Zeiterfassung"
                    description="Arbeitszeit, Tätigkeiten, Projekte und Abwesenheiten mit Buchungsart erfassen. Am Monatsende einreichen – die Schulleitung visiert."
                />
                <div className="flex flex-wrap items-center gap-3">
                    <MonatNavigation monat={monat} basis="/zeiterfassung" />
                    <StatusBadge
                        status={status.status}
                        labels={kategorien.status}
                    />
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
                                })
                            }
                        >
                            <Printer /> Monatsblatt (PDF)
                        </Button>
                        {props.darfVisum && (
                            <Button asChild variant="outline">
                                <Link
                                    href={`/zeiterfassung/visum?monat=${monat}`}
                                >
                                    <ClipboardCheck /> Visum
                                </Link>
                            </Button>
                        )}
                        {props.clicktime && (
                            <Button
                                variant="outline"
                                disabled={gesperrt}
                                title={`ClickTime-Nr. ${props.clicktime.nr}`}
                                onClick={() =>
                                    router.post(
                                        '/zeiterfassung/aus-clicktime',
                                        { monat },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CloudDownload /> Aus ClickTime übernehmen
                            </Button>
                        )}
                        {props.monatsblatt && (
                            <Button asChild variant="outline">
                                <a href={props.monatsblatt}>
                                    <Download /> Abgelegtes Monatsblatt
                                </a>
                            </Button>
                        )}
                        {fuehrt ? (
                            <Button
                                disabled={gesperrt}
                                onClick={() => setRueckfrage(true)}
                            >
                                <CheckCircle2 /> In ClickTime abgeschlossen
                            </Button>
                        ) : (
                            <Button
                                disabled={gesperrt || eintraege.length === 0}
                                onClick={() =>
                                    fehlend.length > 0
                                        ? setRueckfrage(true)
                                        : abschliessen()
                                }
                            >
                                <Send /> Monat einreichen
                            </Button>
                        )}
                    </span>
                </div>
                <AlertDialog
                    open={rueckfrage}
                    onOpenChange={setRueckfrage}
                    title={
                        fuehrt
                            ? 'Monat in ClickTime abgeschlossen?'
                            : 'Alles eingetragen?'
                    }
                    description={
                        <>
                            {fuehrt
                                ? 'Bitte zuerst den Monat in ClickTime abschliessen. Danach übernimmt das Intranet die Buchungen ein letztes Mal, sperrt den Monat und legt das Monatsblatt ab.'
                                : 'Für diese Arbeitstage gibt es noch keinen Eintrag. Trotzdem einreichen?'}
                            {fehlend.length > 0 && (
                                <span className="mt-2 block">
                                    {`Arbeitstage ohne Eintrag: ${tageListe(fehlend)}`}
                                </span>
                            )}
                        </>
                    }
                    confirmLabel={
                        fuehrt
                            ? 'Ja, in ClickTime abgeschlossen'
                            : 'Ja, alles eingetragen'
                    }
                    cancelLabel={fuehrt ? 'Abbrechen' : 'Ich ergänze noch'}
                    onConfirm={() => {
                        setRueckfrage(false);
                        abschliessen();
                    }}
                />
                {fuehrt && !gesperrt && (
                    <Alert>
                        <AlertTitle>ClickTime führt Ihre Zeiten</AlertTitle>
                        <AlertDescription>
                            Den Monat in ClickTime abschliessen – die
                            Genehmigung erfolgt dort durch die vorgesetzte
                            Person. Danach hier «In ClickTime abgeschlossen»
                            bestätigen.
                        </AlertDescription>
                    </Alert>
                )}

                {status.status === 'zurueck' && status.bemerkung && (
                    <Alert variant="destructive">
                        <AlertTitle>Zurück von der Schulleitung</AlertTitle>
                        <AlertDescription>
                            <span translate="no">{status.bemerkung}</span>
                        </AlertDescription>
                    </Alert>
                )}
                {props.unterschrift.status && (
                    <div className="flex flex-wrap items-center gap-3 text-sm">
                        <span>Elektronische Unterschrift:</span>
                        <UnterschriftBadge status={props.unterschrift.status} />
                        {props.unterschrift.pdf && (
                            <Button asChild variant="outline" size="sm">
                                <a href={props.unterschrift.pdf}>
                                    <Download /> Unterschriebenes PDF
                                </a>
                            </Button>
                        )}
                    </div>
                )}
                {gesperrt && (
                    <Alert>
                        <Lock />
                        <AlertTitle>
                            {status.status === 'visiert'
                                ? 'Monat visiert'
                                : status.status === 'abgeschlossen'
                                  ? 'Monat in ClickTime abgeschlossen'
                                  : 'Monat eingereicht'}
                        </AlertTitle>
                        <AlertDescription>
                            Einträge können nicht mehr geändert werden. Für
                            Korrekturen gibt die Schulleitung den Monat zurück.
                        </AlertDescription>
                    </Alert>
                )}

                {!gesperrt && (
                    <Card ref={formularRef}>
                        <CardHeader>
                            <CardTitle>
                                {bearbeite
                                    ? 'Eintrag bearbeiten'
                                    : 'Eintrag erfassen'}
                            </CardTitle>
                            <CardDescription>
                                {fuehrt
                                    ? 'Ihre Zeiten werden in ClickTime erfasst. Eigene Einträge sind hier nicht möglich – mit «Aus ClickTime übernehmen» holen Sie den Monat.'
                                    : 'Beginn und Ende (mit Pause) oder direkt die Stunden angeben.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit}>
                                {/* ClickTime führt (#154): keine eigenen Einträge */}
                                <fieldset
                                    disabled={fuehrt}
                                    className="grid gap-3 disabled:opacity-60 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <FormField
                                        id="z-datum"
                                        label="Datum *"
                                        error={form.errors.datum}
                                    >
                                        <Input
                                            id="z-datum"
                                            type="date"
                                            value={form.data.datum}
                                            onChange={(e) =>
                                                form.setData(
                                                    'datum',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="z-buchungsart"
                                        label="Buchungsart *"
                                        error={form.errors.buchungsart}
                                    >
                                        <NativeSelect
                                            id="z-buchungsart"
                                            value={form.data.buchungsart}
                                            onChange={(e) =>
                                                form.setData(
                                                    'buchungsart',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            {optionen}
                                        </NativeSelect>
                                    </FormField>
                                    {art === 'arbeit' ? (
                                        <FormField
                                            id="z-kategorie"
                                            label="Kategorie *"
                                            error={form.errors.kategorie}
                                        >
                                            <NativeSelect
                                                id="z-kategorie"
                                                value={form.data.kategorie}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'kategorie',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {taetigkeiten}
                                            </NativeSelect>
                                        </FormField>
                                    ) : (
                                        <div />
                                    )}
                                    <FormField
                                        id="z-projekt"
                                        label="Projekt / Einsatz"
                                        error={form.errors.projekt}
                                    >
                                        <Input
                                            id="z-projekt"
                                            value={form.data.projekt}
                                            onChange={(e) =>
                                                form.setData(
                                                    'projekt',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField id="z-beginn" label="Beginn">
                                        <Input
                                            id="z-beginn"
                                            type="time"
                                            value={form.data.beginn}
                                            onChange={(e) =>
                                                form.setData(
                                                    'beginn',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="z-ende"
                                        label="Ende"
                                        error={form.errors.ende}
                                    >
                                        <Input
                                            id="z-ende"
                                            type="time"
                                            value={form.data.ende}
                                            onChange={(e) =>
                                                form.setData(
                                                    'ende',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="z-pause"
                                        label="Pause (Minuten)"
                                        error={form.errors.pause_min}
                                    >
                                        <Input
                                            id="z-pause"
                                            type="number"
                                            min={0}
                                            step={5}
                                            value={form.data.pause_min}
                                            onChange={(e) =>
                                                form.setData(
                                                    'pause_min',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        id="z-stunden"
                                        label="oder Stunden"
                                        error={form.errors.stunden}
                                    >
                                        <Input
                                            id="z-stunden"
                                            type="number"
                                            min={0.25}
                                            max={24}
                                            step={0.25}
                                            value={form.data.stunden}
                                            onChange={(e) =>
                                                form.setData(
                                                    'stunden',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </FormField>
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <FormField
                                            id="z-notiz"
                                            label="Notiz"
                                            error={form.errors.notiz}
                                        >
                                            <Input
                                                id="z-notiz"
                                                value={form.data.notiz}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'notiz',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <div className="flex items-end gap-2">
                                        <Button
                                            type="submit"
                                            disabled={form.processing}
                                            className="flex-1"
                                        >
                                            Speichern
                                        </Button>
                                        {bearbeite && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={abbrechen}
                                            >
                                                Abbrechen
                                            </Button>
                                        )}
                                    </div>
                                </fieldset>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{`Einträge ${monatName(monat)}`}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <EintraegeTabelle
                            monat={monat}
                            feiertage={props.feiertage}
                            soll={props.soll}
                            pruefungen={props.pruefungen}
                            eintraege={eintraege}
                            summen={summen}
                            kategorien={kategorien}
                            onEdit={gesperrt || fuehrt ? undefined : bearbeiten}
                            onDelete={
                                gesperrt
                                    ? undefined
                                    : (e) =>
                                          router.delete(
                                              `/zeiterfassung/eintraege/${e.id}`,
                                              {
                                                  preserveScroll: true,
                                              },
                                          )
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

ZeiterfassungStart.layout = {
    breadcrumbs: [{ title: 'Zeiterfassung', href: '/zeiterfassung' }],
};
