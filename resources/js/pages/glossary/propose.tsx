import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { AlertDialog } from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { Textarea } from '@/components/ui/textarea';

type Werte = {
    official_abbreviation: string;
    official_name: string;
    short_description: string;
    description: string;
    url: string;
    alternative_aliases: string[];
    categories: string[];
};

/** Im Formular: alternative Schreibweisen als Text mit «;» */
type FormWerte = Omit<Werte, 'alternative_aliases'> & {
    alternative_aliases: string;
};

/** Neuen Begriff bzw. Korrektur dem EduKSX-Glossary vorschlagen (#162) – landet in der Moderation. */
export default function GlossaryPropose({
    eintrag,
    werte,
    kategorien,
    felder,
    sync,
}: {
    eintrag: { id: string; titel: string; sync: boolean };
    werte: Werte;
    kategorien: Record<string, string>;
    felder: Record<string, string>;
    sync: { token?: boolean; schreiben?: boolean };
}) {
    const form = useForm<{ werte: FormWerte; begruendung: string }>({
        werte: {
            ...werte,
            alternative_aliases: werte.alternative_aliases.join('; '),
        },
        begruendung: '',
    });
    const [bestaetigen, setBestaetigen] = useState(false);
    const w = form.data.werte;
    const setze = <K extends keyof FormWerte>(feld: K, value: FormWerte[K]) =>
        form.setData('werte', { ...w, [feld]: value });
    const titel = eintrag.sync
        ? 'Korrektur vorschlagen'
        : 'Dem EduKSX-Glossary vorschlagen';

    return (
        <>
            <Head title={titel} />
            <div className="flex max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <Button
                    variant="ghost"
                    size="sm"
                    className="self-start"
                    asChild
                >
                    <Link href={`/glossar/${eintrag.id}`}>
                        <ArrowLeft /> Zurück zum Eintrag
                    </Link>
                </Button>
                <PageHeader
                    title={titel}
                    description="Der Vorschlag geht an die Moderation des EduKSX-Glossary und wird erst nach der Prüfung veröffentlicht. Gesendet werden nur die Fachdaten unten – eigene Formen, «Intern genutzt in» und Notiz bleiben im Intranet. Keine Personendaten eintragen."
                />
                <p className="text-sm font-medium" translate="no">
                    {eintrag.titel}
                </p>
                {(!sync.token || !sync.schreiben) && (
                    <p className="text-sm text-destructive">
                        Vorschläge sind noch nicht möglich: Im Plugin
                        «EduKSX-Glossary» fehlen das Token des Servicekontos
                        oder die Freigabe für schreibende Aktionen.
                    </p>
                )}
                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        <FormField
                            id="p-abk"
                            label={felder.official_abbreviation}
                        >
                            <Input
                                id="p-abk"
                                maxLength={40}
                                value={w.official_abbreviation}
                                onChange={(e) =>
                                    setze(
                                        'official_abbreviation',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField id="p-bez" label={felder.official_name}>
                            <Input
                                id="p-bez"
                                maxLength={500}
                                value={w.official_name}
                                onChange={(e) =>
                                    setze('official_name', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="p-kurz"
                            label={felder.short_description}
                            hint={`${w.short_description.length}/160`}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="p-kurz"
                                maxLength={160}
                                value={w.short_description}
                                onChange={(e) =>
                                    setze('short_description', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="p-beschreibung"
                            label={felder.description}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="p-beschreibung"
                                rows={4}
                                value={w.description}
                                onChange={(e) =>
                                    setze('description', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField id="p-url" label={felder.url}>
                            <Input
                                id="p-url"
                                type="url"
                                placeholder="https://"
                                value={w.url}
                                onChange={(e) => setze('url', e.target.value)}
                            />
                        </FormField>
                        <FormField
                            id="p-alias"
                            label={felder.alternative_aliases}
                            hint="Mehrere mit «;» trennen"
                        >
                            <Input
                                id="p-alias"
                                value={w.alternative_aliases}
                                onChange={(e) =>
                                    setze('alternative_aliases', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="p-kat"
                            label={felder.categories}
                            hint="Nur Kategorien, die es im EduKSX-Glossary gibt"
                            className="sm:col-span-2"
                        >
                            <MultiSelect
                                id="p-kat"
                                values={w.categories}
                                onChange={(v) => setze('categories', v)}
                                options={Object.entries(kategorien).map(
                                    ([value, label]) => ({ value, label }),
                                )}
                                placeholder="Keine"
                            />
                        </FormField>
                        <FormField
                            id="p-grund"
                            label="Begründung"
                            hint={`Warum dieser Vorschlag? (${form.data.begruendung.length}/2000)`}
                            error={form.errors.begruendung}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="p-grund"
                                rows={3}
                                maxLength={2000}
                                value={form.data.begruendung}
                                onChange={(e) =>
                                    form.setData('begruendung', e.target.value)
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>
                <div className="flex gap-2">
                    <Button
                        disabled={form.processing || !sync.token}
                        onClick={() => setBestaetigen(true)}
                    >
                        <Send /> Vorschlag senden …
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={`/glossar/${eintrag.id}`}>Abbrechen</Link>
                    </Button>
                </div>
                <AlertDialog
                    open={bestaetigen}
                    onOpenChange={setBestaetigen}
                    title="Vorschlag senden?"
                    description={
                        eintrag.sync
                            ? 'Gesendet werden nur die geänderten Felder. Die Moderation des EduKSX-Glossary prüft den Vorschlag.'
                            : 'Der neue Begriff geht an die Moderation des EduKSX-Glossary. Nach der Annahme kommt er über den Sync zurück und ersetzt diesen lokalen Eintrag (eigene Formen bleiben).'
                    }
                    confirmLabel="Senden"
                    onConfirm={() => {
                        setBestaetigen(false);
                        form.post(`/glossar/${eintrag.id}/vorschlag`);
                    }}
                />
            </div>
        </>
    );
}

GlossaryPropose.layout = {
    breadcrumbs: [{ title: 'Glossar', href: '/glossar' }],
};
