import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    ClipboardCheck,
    CloudDownload,
    Download,
    FileSignature,
    LogIn,
    LogOut,
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
import { NativeSelect } from '@/components/ui/native-select';
import EintraegeTabelle from '../components/eintraege-tabelle';
import MonatNavigation from '../components/monat-navigation';
import StatusBadge from '../components/status-badge';
import { UnterschriftBadge } from '../components/unterschrift';
import type {
    Eintrag,
    Kategorien,
    Pruefung,
    Status,
    Summen,
    Unterschrift,
} from '../lib/types';
import { DOSSIER, istGesperrt, monatName, tageListe } from '../lib/types';

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
    person: { id: string; name: string };
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
    /** Hinterlegte vorgesetzte Personen (#208) */
    vorgesetzte?: { id: string; name: string }[];
    /** Unterschrift-Anbieter eingerichtet (#208) */
    signaturAktiv?: boolean;
    /** Ein-/Ausstempeln über ClickTime (#214), null = ohne ClickTime */
    stempel?: {
        eingestempelt: boolean;
        seit: string | null;
        schreiben: boolean;
        fehler: string | null;
    } | null;
    /** Buchungen aus ClickTime korrigieren (#214, Recht) */
    darfKorrigieren?: boolean;
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
    // Monatsrapport an die vorgesetzte Person unterschreiben (#208)
    const vorgesetzte = props.vorgesetzte ?? [];
    const rapportForm = useForm({
        monat,
        vorgesetzte: vorgesetzte[0]?.id ?? '',
    });
    const unterschreibbar =
        (status.status === 'visiert' || status.status === 'abgeschlossen') &&
        (!props.unterschrift.status ||
            ['abgelehnt', 'zurueckgezogen', 'abgelaufen'].includes(
                props.unterschrift.status,
            ));
    const rapportSenden = (e: FormEvent) => {
        e.preventDefault();
        rapportForm.post('/zeiterfassung/rapport-unterschreiben', {
            preserveScroll: true,
        });
    };
    // ClickTime führt (#204): Erfassung eingeklappt, da hier nichts erfasst werden kann
    const [formOffen, setFormOffen] = useState(!fuehrt);
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
    // Korrektur einer ClickTime-Buchung (#214): nur Datum, Zeiten, Stunden und Notiz
    const korrektur = fuehrt && bearbeite !== null;
    const bearbeiten = (e: Eintrag) => {
        setBearbeite(e.id);
        setFormOffen(true);
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
        if (bearbeite && fuehrt) {
            form.put(
                `/zeiterfassung/eintraege/${bearbeite}/clicktime`,
                nachher,
            );
        } else if (bearbeite) {
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
                        <Button asChild variant="outline">
                            <a
                                href={`/zeiterfassung/rapport/${person.id}/${monat}`}
                                target="_blank"
                                rel="noopener"
                            >
                                <Printer /> Monatsrapport (PDF)
                            </a>
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

                {status.status === 'offen' &&
                    status.geoeffnet_at &&
                    status.bemerkung && (
                        <Alert>
                            <AlertTitle>Monat wieder geöffnet</AlertTitle>
                            <AlertDescription>
                                <span translate="no">{status.bemerkung}</span>
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
                {props.stempel && (
                    <Card>
                        <CardContent className="flex flex-wrap items-center gap-3 pt-6">
                            <span className="text-sm font-medium">
                                {props.stempel.eingestempelt
                                    ? `Eingestempelt seit ${props.stempel.seit ?? ''}`
                                    : 'Nicht eingestempelt'}
                            </span>
                            <Button
                                disabled={
                                    props.stempel.eingestempelt ||
                                    !props.stempel.schreiben ||
                                    props.stempel.fehler !== null
                                }
                                onClick={() =>
                                    router.post(
                                        '/zeiterfassung/einstempeln',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <LogIn /> Einstempeln
                            </Button>
                            <Button
                                variant="outline"
                                disabled={
                                    !props.stempel.eingestempelt ||
                                    !props.stempel.schreiben ||
                                    props.stempel.fehler !== null
                                }
                                onClick={() =>
                                    router.post(
                                        '/zeiterfassung/ausstempeln',
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <LogOut /> Ausstempeln
                            </Button>
                            {props.stempel.fehler ? (
                                <span className="text-sm text-destructive">
                                    {props.stempel.fehler}
                                </span>
                            ) : (
                                !props.stempel.schreiben && (
                                    <span className="text-sm text-muted-foreground">
                                        Stempeln ist ausgeschaltet (Plugin
                                        ClickTime: Schreiben nicht erlaubt).
                                    </span>
                                )
                            )}
                        </CardContent>
                    </Card>
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
                        {props.unterschrift.dossier && (
                            <span className="text-muted-foreground">
                                CMI-Dossier:{' '}
                                {DOSSIER[props.unterschrift.dossier] ??
                                    props.unterschrift.dossier}
                            </span>
                        )}
                    </div>
                )}
                {unterschreibbar && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Monatsrapport unterschreiben</CardTitle>
                            <CardDescription>
                                Zuerst unterschreibst du, danach die vorgesetzte
                                Person. Der unterschriebene Rapport wird bei dir
                                abgelegt.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!props.signaturAktiv ? (
                                <p className="text-sm text-muted-foreground">
                                    Elektronische Unterschrift ist nicht
                                    eingerichtet.
                                </p>
                            ) : vorgesetzte.length === 0 ? (
                                <p className="text-sm text-destructive">
                                    Keine vorgesetzte Person hinterlegt – bitte
                                    bei der Schulleitung melden.
                                </p>
                            ) : (
                                <form
                                    onSubmit={rapportSenden}
                                    className="flex flex-wrap items-end gap-3"
                                >
                                    {vorgesetzte.length > 1 ? (
                                        <FormField
                                            id="z-vorgesetzte"
                                            label="Vorgesetzte Person"
                                        >
                                            <NativeSelect
                                                id="z-vorgesetzte"
                                                value={
                                                    rapportForm.data.vorgesetzte
                                                }
                                                onChange={(e) =>
                                                    rapportForm.setData(
                                                        'vorgesetzte',
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {vorgesetzte.map((v) => (
                                                    <option
                                                        key={v.id}
                                                        value={v.id}
                                                        translate="no"
                                                    >
                                                        {v.name}
                                                    </option>
                                                ))}
                                            </NativeSelect>
                                        </FormField>
                                    ) : (
                                        <p className="text-sm">
                                            Vorgesetzte Person:{' '}
                                            <span translate="no">
                                                {vorgesetzte[0].name}
                                            </span>
                                        </p>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={rapportForm.processing}
                                    >
                                        <FileSignature /> Monatsrapport
                                        unterschreiben
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
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
                            <CardTitle className="flex flex-wrap items-center gap-2">
                                {korrektur ? (
                                    'Buchung in ClickTime korrigieren'
                                ) : fuehrt ? (
                                    <button
                                        type="button"
                                        className="inline-flex items-center gap-1 text-left"
                                        aria-expanded={formOffen}
                                        aria-controls="zeit-formular"
                                        onClick={() => setFormOffen(!formOffen)}
                                    >
                                        {formOffen ? (
                                            <ChevronDown
                                                className="size-4"
                                                aria-hidden
                                            />
                                        ) : (
                                            <ChevronRight
                                                className="size-4"
                                                aria-hidden
                                            />
                                        )}
                                        Eintrag erfassen
                                    </button>
                                ) : bearbeite ? (
                                    'Eintrag bearbeiten'
                                ) : (
                                    'Eintrag erfassen'
                                )}
                                {fuehrt && !korrektur && (
                                    <Badge
                                        variant="outline"
                                        className="border-destructive text-destructive"
                                    >
                                        ClickTime aktiv – keine manuellen
                                        Einträge möglich
                                    </Badge>
                                )}
                            </CardTitle>
                            {formOffen && (
                                <CardDescription>
                                    {korrektur
                                        ? 'Die Änderung geht direkt an ClickTime. Buchungsart, Kategorie, Projekt und Pause bleiben wie in ClickTime.'
                                        : fuehrt
                                          ? 'Ihre Zeiten werden in ClickTime erfasst. Eigene Einträge sind hier nicht möglich – mit «Aus ClickTime übernehmen» holen Sie den Monat.'
                                          : 'Beginn und Ende (mit Pause) oder direkt die Stunden angeben.'}
                                </CardDescription>
                            )}
                        </CardHeader>
                        {formOffen && (
                            <CardContent id="zeit-formular">
                                <form onSubmit={submit}>
                                    {/* ClickTime führt (#154): keine eigenen Einträge */}
                                    <fieldset
                                        disabled={fuehrt && !korrektur}
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
                                                disabled={korrektur}
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
                                                    disabled={korrektur}
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
                                                disabled={korrektur}
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
                                                disabled={korrektur}
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
                        )}
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
                            onEdit={
                                gesperrt || (fuehrt && !props.darfKorrigieren)
                                    ? undefined
                                    : bearbeiten
                            }
                            quelleBearbeiten={fuehrt && props.darfKorrigieren}
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
