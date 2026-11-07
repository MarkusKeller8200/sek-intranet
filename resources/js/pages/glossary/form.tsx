import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Lock, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import FormField from '@/components/form-field';
import Markdown from '@/components/markdown';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { MultiSelect } from '@/components/ui/multi-select';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { GlossaryEntry } from '@/lib/glossary';
import { entryTitle } from '@/lib/glossary';

const opts = (o: Record<string, string>) =>
    Object.entries(o).map(([value, label]) => ({ value, label }));

/** Einzeldatensatz des Glossars erfassen oder bearbeiten. */
export default function GlossaryForm({
    eintrag,
    kategorien,
    herkunft,
    bereiche,
    status,
    rechte,
}: {
    eintrag: GlossaryEntry | null;
    kategorien: Record<string, string>;
    herkunft: Record<string, string>;
    bereiche: Record<string, string>;
    status: Record<string, string>;
    rechte: { edit: boolean; official: boolean };
}) {
    const form = useForm({
        kategorien: eintrag?.kategorien ?? [],
        abk_offiziell: eintrag?.abk_offiziell ?? '',
        aliase_offiziell: (eintrag?.aliase_offiziell ?? []).join('; '),
        abk_eigen: eintrag?.abk_eigen ?? '',
        alias_eigen: eintrag?.alias_eigen ?? '',
        bez_offiziell: eintrag?.bez_offiziell ?? '',
        bez_eigen: eintrag?.bez_eigen ?? '',
        kurzbeschreibung: eintrag?.kurzbeschreibung ?? '',
        beschreibung: eintrag?.beschreibung ?? '',
        url: eintrag?.url ?? '',
        notiz: eintrag?.notiz ?? '',
        herkunft: eintrag?.herkunft ?? [],
        genutzt_in: eintrag?.genutzt_in ?? [],
        status: eintrag?.status ?? 'aktuell',
        suchbegriffe: eintrag?.suchbegriffe ?? '',
        gueltig_ab: eintrag?.gueltig_ab ?? '',
        gueltig_bis: eintrag?.gueltig_bis ?? '',
        aktiv: eintrag?.aktiv !== false,
    });
    const [vorschau, setVorschau] = useState(false);
    const d = form.data;
    // aus EduKSX-Glossary (#156): gemeinsame Felder kommen aus dem Sync und sind gesperrt
    const sync = Boolean(eintrag?.glossary);
    // Offizielle Felder: gesperrt, ausser mit Recht oder solange sie leer sind
    const gesperrt = (
        feld: 'abk_offiziell' | 'aliase_offiziell' | 'bez_offiziell',
    ) => {
        const vorher = eintrag?.[feld];

        return (
            sync ||
            (eintrag !== null &&
                !rechte.official &&
                (Array.isArray(vorher) ? vorher.length > 0 : Boolean(vorher)))
        );
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (eintrag) {
            form.put(`/glossar/${eintrag.id}`);
        } else {
            form.post('/glossar');
        }
    };
    const offiziell = (label: string) =>
        rechte.official ? label : `${label} (offiziell, gesperrt)`;

    return (
        <>
            <Head title={eintrag ? 'Eintrag bearbeiten' : 'Neuer Eintrag'} />
            <form
                onSubmit={submit}
                className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    className="self-start"
                    asChild
                >
                    <Link
                        href={eintrag ? `/glossar/${eintrag.id}` : '/glossar'}
                    >
                        <ArrowLeft />{' '}
                        {eintrag ? 'Zurück zum Eintrag' : 'Zum Glossar'}
                    </Link>
                </Button>
                <PageHeader
                    title={eintrag ? 'Eintrag bearbeiten' : 'Neuer Eintrag'}
                    description={
                        sync
                            ? 'Aus dem EduKSX-Glossary: offizielle Werte, Kategorien, Beschreibungen, Herkunft, Status und Gültigkeit kommen aus dem Sync und sind hier gesperrt. Eigene Abkürzung und Bezeichnung, Alias, «Intern genutzt in», Suchbegriffe und Notiz bleiben im Intranet.'
                            : undefined
                    }
                />

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Lock className="size-4 text-muted-foreground" />{' '}
                                Offiziell
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <FormField
                                id="g-abk-off"
                                label={offiziell('Abkürzung')}
                                error={form.errors.abk_offiziell}
                            >
                                <Input
                                    id="g-abk-off"
                                    value={d.abk_offiziell}
                                    disabled={gesperrt('abk_offiziell')}
                                    onChange={(e) =>
                                        form.setData(
                                            'abk_offiziell',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="g-alias-off"
                                label={offiziell('Aliase')}
                                hint="Mehrere mit ; trennen"
                                error={form.errors.aliase_offiziell}
                            >
                                <Input
                                    id="g-alias-off"
                                    value={d.aliase_offiziell}
                                    disabled={gesperrt('aliase_offiziell')}
                                    onChange={(e) =>
                                        form.setData(
                                            'aliase_offiziell',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="g-bez-off"
                                label={offiziell('Bezeichnung')}
                                error={form.errors.bez_offiziell}
                            >
                                <Input
                                    id="g-bez-off"
                                    value={d.bez_offiziell}
                                    disabled={gesperrt('bez_offiziell')}
                                    onChange={(e) =>
                                        form.setData(
                                            'bez_offiziell',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Wie wir es verwenden</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <FormField
                                id="g-abk"
                                label="Abkürzung"
                                error={form.errors.abk_eigen}
                            >
                                <Input
                                    id="g-abk"
                                    value={d.abk_eigen}
                                    onChange={(e) =>
                                        form.setData(
                                            'abk_eigen',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="g-alias"
                                label="Alias"
                                error={form.errors.alias_eigen}
                            >
                                <Input
                                    id="g-alias"
                                    value={d.alias_eigen}
                                    onChange={(e) =>
                                        form.setData(
                                            'alias_eigen',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="g-bez"
                                label="Bezeichnung"
                                hint="Wird im Intranet überall angezeigt, wo auf den Eintrag verwiesen wird"
                                error={form.errors.bez_eigen}
                            >
                                <Input
                                    id="g-bez"
                                    value={d.bez_eigen}
                                    onChange={(e) =>
                                        form.setData(
                                            'bez_eigen',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="grid gap-4 pt-6 sm:grid-cols-2">
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-kat"
                                label="Kategorien"
                                error={form.errors.kategorien}
                            >
                                <MultiSelect
                                    id="g-kat"
                                    values={d.kategorien}
                                    onChange={(v) =>
                                        form.setData('kategorien', v)
                                    }
                                    options={opts(kategorien)}
                                    placeholder="Keine"
                                />
                            </FormField>
                        </fieldset>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-status"
                                label="Status"
                                error={form.errors.status}
                            >
                                <NativeSelect
                                    id="g-status"
                                    value={d.status}
                                    onChange={(e) =>
                                        form.setData('status', e.target.value)
                                    }
                                >
                                    {Object.entries(status).map(([k, l]) => (
                                        <option key={k} value={k}>
                                            {l}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <label className="mt-2 flex items-center gap-2 text-sm">
                                    <Checkbox
                                        id="g-aktiv"
                                        checked={d.aktiv}
                                        onCheckedChange={(c) =>
                                            form.setData('aktiv', c === true)
                                        }
                                    />
                                    Aktiv – inaktive Einträge sehen nur
                                    Pflegende, sie werden im Text nicht markiert
                                </label>
                            </FormField>
                        </fieldset>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-kurz"
                                label="Kurzbeschreibung"
                                hint={`Ein Satz für den Tooltip (${d.kurzbeschreibung.length}/160)`}
                                error={form.errors.kurzbeschreibung}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="g-kurz"
                                    maxLength={160}
                                    value={d.kurzbeschreibung}
                                    onChange={(e) =>
                                        form.setData(
                                            'kurzbeschreibung',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </fieldset>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-beschreibung"
                                label="Beschreibung"
                                error={form.errors.beschreibung}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    id="g-beschreibung"
                                    rows={3}
                                    value={d.beschreibung}
                                    onChange={(e) =>
                                        form.setData(
                                            'beschreibung',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </fieldset>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-url"
                                label="URL"
                                error={form.errors.url}
                                className="sm:col-span-2"
                            >
                                <Input
                                    id="g-url"
                                    type="url"
                                    placeholder="https://"
                                    value={d.url}
                                    onChange={(e) =>
                                        form.setData('url', e.target.value)
                                    }
                                />
                            </FormField>
                        </fieldset>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-herkunft"
                                label="Herkunft"
                                error={form.errors.herkunft}
                            >
                                <MultiSelect
                                    id="g-herkunft"
                                    values={d.herkunft}
                                    onChange={(v) =>
                                        form.setData('herkunft', v)
                                    }
                                    options={opts(herkunft)}
                                    placeholder="Keine"
                                />
                            </FormField>
                        </fieldset>
                        <FormField
                            id="g-genutzt"
                            label="Intern genutzt in"
                            error={form.errors.genutzt_in}
                        >
                            <MultiSelect
                                id="g-genutzt"
                                values={d.genutzt_in}
                                onChange={(v) => form.setData('genutzt_in', v)}
                                options={opts(bereiche)}
                                placeholder="Keine"
                            />
                        </FormField>
                        <FormField
                            id="g-such"
                            label="Suchbegriffe"
                            error={form.errors.suchbegriffe}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="g-such"
                                value={d.suchbegriffe}
                                onChange={(e) =>
                                    form.setData('suchbegriffe', e.target.value)
                                }
                            />
                        </FormField>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-ab"
                                label="Gültig ab"
                                error={form.errors.gueltig_ab}
                            >
                                <Input
                                    id="g-ab"
                                    type="date"
                                    value={d.gueltig_ab}
                                    onChange={(e) =>
                                        form.setData(
                                            'gueltig_ab',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </fieldset>
                        <fieldset disabled={sync} className="contents">
                            <FormField
                                id="g-bis"
                                label="Gültig bis"
                                error={form.errors.gueltig_bis}
                            >
                                <Input
                                    id="g-bis"
                                    type="date"
                                    value={d.gueltig_bis}
                                    onChange={(e) =>
                                        form.setData(
                                            'gueltig_bis',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </fieldset>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between gap-2">
                            Notiz (Markdown)
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setVorschau(!vorschau)}
                                aria-pressed={vorschau}
                            >
                                {vorschau ? 'Bearbeiten' : 'Vorschau'}
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {vorschau ? (
                            <div
                                className="min-h-24 rounded-md border p-3"
                                translate="no"
                            >
                                <Markdown text={d.notiz} />
                            </div>
                        ) : (
                            <FormField
                                id="g-notiz"
                                label="Notiz"
                                hint="**fett**, *kursiv*, - Liste, [Link](https://…)"
                                error={form.errors.notiz}
                            >
                                <Textarea
                                    id="g-notiz"
                                    rows={6}
                                    value={d.notiz}
                                    onChange={(e) =>
                                        form.setData('notiz', e.target.value)
                                    }
                                />
                            </FormField>
                        )}
                    </CardContent>
                </Card>

                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing}>
                        <Save /> Speichern
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href={
                                eintrag ? `/glossar/${eintrag.id}` : '/glossar'
                            }
                        >
                            Abbrechen
                        </Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

// Brotkrumen: «Glossar» führt zur Hauptseite, der Eintrag zur Einzelansicht
GlossaryForm.layout = (props: { eintrag: GlossaryEntry | null }) => ({
    breadcrumbs: props.eintrag
        ? [
              { title: 'Glossar', href: '/glossar' },
              {
                  title: entryTitle(props.eintrag),
                  href: `/glossar/${props.eintrag.id}`,
              },
              {
                  title: 'Bearbeiten',
                  href: `/glossar/${props.eintrag.id}/bearbeiten`,
              },
          ]
        : [
              { title: 'Glossar', href: '/glossar' },
              { title: 'Neuer Eintrag', href: '/glossar/neu' },
          ],
});
