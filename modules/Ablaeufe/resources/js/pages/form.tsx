import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import FormField from '@/components/form-field';
import InputError from '@/components/input-error';
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
import { MultiSelect } from '@/components/ui/multi-select';
import { NativeSelect } from '@/components/ui/native-select';
import { SortableList } from '@/components/ui/sortable-list';
import { Textarea } from '@/components/ui/textarea';
import type { Ablauf, Dokument, Gruppe, Phase, Schritt } from '../lib/types';
import { fristText, nummer } from '../lib/types';

type Daten = {
    titel: string;
    bereich: string;
    status: string;
    beschreibung: string;
    verantwortlich: string;
    gueltig_ab: string;
    phasen: Phase[];
    schritte: Schritt[];
};

const neueId = (prefix: string) =>
    `${prefix}-${Math.random().toString(36).slice(2, 10)}`;

/** Ablauf erfassen oder bearbeiten: Angaben, Phasen und Schritte (Checkliste). */
export default function AblaufForm({
    ablauf,
    gruppen,
    dokumente,
    bereiche,
    status,
}: {
    ablauf: Ablauf | null;
    gruppen: Gruppe[];
    dokumente: Dokument[];
    bereiche: Record<string, string>;
    status: Record<string, string>;
}) {
    const form = useForm<Daten>({
        titel: ablauf?.titel ?? '',
        bereich: ablauf?.bereich ?? 'personal',
        status: ablauf?.status ?? 'entwurf',
        beschreibung: ablauf?.beschreibung ?? '',
        verantwortlich: ablauf?.verantwortlich ?? '',
        gueltig_ab: ablauf?.gueltig_ab ?? '',
        phasen: ablauf?.phasen ?? [{ key: 'ablauf', titel: 'Ablauf' }],
        schritte: ablauf?.schritte ?? [],
    });
    const { data } = form;
    const [aktiv, setAktiv] = useState(data.phasen[0]?.key ?? '');
    const [gewaehlt, setGewaehlt] = useState<string | null>(null);
    const phase = data.phasen.find((p) => p.key === aktiv) ?? data.phasen[0];
    const schritteDerPhase = data.schritte.filter(
        (s) => s.phase === phase?.key,
    );
    const schritt = data.schritte.find((s) => s.id === gewaehlt) ?? null;
    const fehler = form.errors as Record<string, string | undefined>;
    const editor = useRef<HTMLDivElement>(null);
    // Gewählten Schritt zeigen (der Editor steht unter der Liste)
    useEffect(() => {
        if (gewaehlt !== null) {
            editor.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
        }
    }, [gewaehlt]);

    // Schritte in Phasen-Reihenfolge halten
    const ordnen = (schritte: Schritt[], phasen: Phase[] = data.phasen) =>
        phasen.flatMap((p) => schritte.filter((s) => s.phase === p.key));
    const setzeSchritt = (id: string, felder: Partial<Schritt>) =>
        form.setData(
            'schritte',
            ordnen(
                data.schritte.map((s) =>
                    s.id === id ? { ...s, ...felder } : s,
                ),
            ),
        );

    const phaseHinzufuegen = () => {
        const key = neueId('phase');
        form.setData('phasen', [...data.phasen, { key, titel: 'Neue Phase' }]);
        setAktiv(key);
    };
    const phaseEntfernen = (key: string) => {
        const phasen = data.phasen.filter((p) => p.key !== key);
        form.setData((d) => ({
            ...d,
            phasen,
            schritte: d.schritte
                .filter((s) => s.phase !== key)
                .map((s) =>
                    d.schritte.find((x) => x.id === s.gegenstueck)?.phase ===
                    key
                        ? { ...s, gegenstueck: null }
                        : s,
                ),
        }));
        setAktiv(phasen[0]?.key ?? '');
        setGewaehlt(null);
    };
    const schrittHinzufuegen = () => {
        if (!phase) {
            return;
        }
        const neu: Schritt = {
            id: neueId('neu'),
            phase: phase.key,
            text: '',
            verantwortung: '',
            kommunikation: '',
            dokumente: [],
            bemerkung: '',
            frist_tage: null,
            gilt_fuer: [],
            gegenstueck: null,
        };
        form.setData('schritte', ordnen([...data.schritte, neu]));
        setGewaehlt(neu.id);
    };
    const schrittEntfernen = (id: string) => {
        form.setData(
            'schritte',
            data.schritte
                .filter((s) => s.id !== id)
                .map((s) =>
                    s.gegenstueck === id ? { ...s, gegenstueck: null } : s,
                ),
        );
        setGewaehlt(null);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (ablauf) {
            form.put(`/ablaeufe/${ablauf.id}`);
        } else {
            form.post('/ablaeufe');
        }
    };

    return (
        <>
            <Head title={ablauf ? 'Ablauf bearbeiten' : 'Neuer Ablauf'} />
            <form
                onSubmit={submit}
                className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title={ablauf ? 'Ablauf bearbeiten' : 'Neuer Ablauf'}
                    description="Checkliste als Vorlage: Phasen (z. B. Eintritt, Austritt) und Schritte mit Verantwortung, Kommunikation, Dokumenten und Bemerkungen."
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Angaben</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            id="a-titel"
                            label="Titel *"
                            error={form.errors.titel}
                            className="sm:col-span-2"
                        >
                            <Input
                                id="a-titel"
                                value={data.titel}
                                onChange={(e) =>
                                    form.setData('titel', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="a-bereich"
                            label="Bereich *"
                            error={form.errors.bereich}
                        >
                            <NativeSelect
                                id="a-bereich"
                                value={data.bereich}
                                onChange={(e) =>
                                    form.setData('bereich', e.target.value)
                                }
                            >
                                {Object.entries(bereiche).map(([k, l]) => (
                                    <option key={k} value={k}>
                                        {l}
                                    </option>
                                ))}
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="a-status"
                            label="Status *"
                            error={form.errors.status}
                            hint="Nur «Gültig» sehen alle mit Recht «Abläufe ansehen»."
                        >
                            <NativeSelect
                                id="a-status"
                                value={data.status}
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
                        </FormField>
                        <FormField
                            id="a-verantwortlich"
                            label="Verantwortlich für den Ablauf"
                            error={form.errors.verantwortlich}
                        >
                            <Input
                                id="a-verantwortlich"
                                value={data.verantwortlich}
                                onChange={(e) =>
                                    form.setData(
                                        'verantwortlich',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="a-gueltig"
                            label="Gültig ab"
                            error={form.errors.gueltig_ab}
                        >
                            <Input
                                id="a-gueltig"
                                type="date"
                                value={data.gueltig_ab}
                                onChange={(e) =>
                                    form.setData('gueltig_ab', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="a-beschreibung"
                            label="Beschreibung"
                            error={form.errors.beschreibung}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="a-beschreibung"
                                rows={3}
                                value={data.beschreibung}
                                onChange={(e) =>
                                    form.setData('beschreibung', e.target.value)
                                }
                            />
                        </FormField>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Phasen</CardTitle>
                        <CardDescription>
                            Zum Beispiel Onboarding, Offboarding, Mutation. Eine
                            Phase entfernen löscht auch ihre Schritte.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {data.phasen.map((p, i) => (
                            <div
                                key={p.key}
                                className="flex items-center gap-2"
                            >
                                <Input
                                    aria-label={`Titel der Phase ${i + 1}`}
                                    value={p.titel}
                                    onChange={(e) =>
                                        form.setData(
                                            'phasen',
                                            data.phasen.map((x) =>
                                                x.key === p.key
                                                    ? {
                                                          ...x,
                                                          titel: e.target.value,
                                                      }
                                                    : x,
                                            ),
                                        )
                                    }
                                />
                                <Badge variant="outline" className="shrink-0">
                                    {`${data.schritte.filter((s) => s.phase === p.key).length} Schritte`}
                                </Badge>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon"
                                    disabled={data.phasen.length === 1}
                                    aria-label={`Phase ${p.titel} entfernen`}
                                    onClick={() => phaseEntfernen(p.key)}
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        ))}
                        <InputError message={fehler.phasen} />
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={phaseHinzufuegen}
                            >
                                <Plus /> Phase hinzufügen
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Schritte</CardTitle>
                        <CardDescription>
                            Reihenfolge per Ziehen am Griff oder mit den
                            Pfeilen. Schritt anklicken zum Bearbeiten.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <div
                            className="flex flex-wrap gap-2"
                            role="group"
                            aria-label="Phase wählen"
                        >
                            {data.phasen.map((p) => (
                                <Button
                                    key={p.key}
                                    type="button"
                                    variant={
                                        p.key === phase?.key
                                            ? 'default'
                                            : 'outline'
                                    }
                                    aria-pressed={p.key === phase?.key}
                                    onClick={() => {
                                        setAktiv(p.key);
                                        setGewaehlt(null);
                                    }}
                                >
                                    <span translate="no">{p.titel}</span>
                                </Button>
                            ))}
                        </div>

                        {schritteDerPhase.length > 0 && (
                            <SortableList
                                items={schritteDerPhase.map((s) => ({
                                    id: s.id,
                                    label: s.text || '(ohne Text)',
                                }))}
                                onChange={(items) =>
                                    form.setData(
                                        'schritte',
                                        data.phasen.flatMap((p) =>
                                            p.key === phase?.key
                                                ? items
                                                      .map((i) =>
                                                          data.schritte.find(
                                                              (s) =>
                                                                  s.id === i.id,
                                                          ),
                                                      )
                                                      .filter(
                                                          (s): s is Schritt =>
                                                              s !== undefined,
                                                      )
                                                : data.schritte.filter(
                                                      (s) => s.phase === p.key,
                                                  ),
                                        ),
                                    )
                                }
                                render={(item) => {
                                    const s = data.schritte.find(
                                        (x) => x.id === item.id,
                                    );

                                    return (
                                        <button
                                            type="button"
                                            className={`flex-1 rounded px-1 text-left ${gewaehlt === item.id ? 'font-semibold text-primary' : ''}`}
                                            aria-pressed={gewaehlt === item.id}
                                            onClick={() => setGewaehlt(item.id)}
                                        >
                                            <span translate="no">
                                                {item.label}
                                            </span>
                                            {s?.verantwortung && (
                                                <span
                                                    className="text-muted-foreground"
                                                    translate="no"
                                                >
                                                    {` · ${s.verantwortung}`}
                                                </span>
                                            )}
                                        </button>
                                    );
                                }}
                            />
                        )}
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={schrittHinzufuegen}
                            >
                                <Plus /> Schritt hinzufügen
                            </Button>
                        </div>

                        {schritt && (
                            <div
                                ref={editor}
                                className="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2"
                            >
                                <p className="font-medium sm:col-span-2">
                                    {`Schritt ${nummer(data.schritte, schritt)} bearbeiten`}
                                </p>
                                <FormField
                                    id="s-text"
                                    label="Schritt *"
                                    className="sm:col-span-2"
                                >
                                    <Input
                                        id="s-text"
                                        value={schritt.text}
                                        onChange={(e) =>
                                            setzeSchritt(schritt.id, {
                                                text: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="s-verantwortung"
                                    label="Verantwortung"
                                >
                                    <Input
                                        id="s-verantwortung"
                                        value={schritt.verantwortung}
                                        onChange={(e) =>
                                            setzeSchritt(schritt.id, {
                                                verantwortung: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="s-kommunikation"
                                    label="Kommunikation an"
                                >
                                    <Input
                                        id="s-kommunikation"
                                        value={schritt.kommunikation}
                                        onChange={(e) =>
                                            setzeSchritt(schritt.id, {
                                                kommunikation: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="s-dokumente"
                                    label="Dokumente"
                                    hint="Aus «Dokumente & Links»"
                                >
                                    <MultiSelect
                                        id="s-dokumente"
                                        values={schritt.dokumente}
                                        onChange={(v) =>
                                            setzeSchritt(schritt.id, {
                                                dokumente: v,
                                            })
                                        }
                                        options={dokumente.map((d) => ({
                                            value: d.id,
                                            label: d.title,
                                        }))}
                                        placeholder="Keine"
                                    />
                                </FormField>
                                <FormField
                                    id="s-gruppen"
                                    label="Gilt für"
                                    hint="Leer = für alle Personengruppen"
                                >
                                    <MultiSelect
                                        id="s-gruppen"
                                        values={schritt.gilt_fuer}
                                        onChange={(v) =>
                                            setzeSchritt(schritt.id, {
                                                gilt_fuer: v,
                                            })
                                        }
                                        options={gruppen.map((g) => ({
                                            value: g.id,
                                            label: g.name,
                                        }))}
                                        placeholder="Alle"
                                    />
                                </FormField>
                                <FormField
                                    id="s-frist"
                                    label="Frist (Tage zum Stichtag)"
                                    hint={
                                        schritt.frist_tage === null
                                            ? 'Leer = keine Frist; negativ = vor dem Stichtag'
                                            : fristText(schritt.frist_tage)
                                    }
                                >
                                    <Input
                                        id="s-frist"
                                        type="number"
                                        min={-365}
                                        max={365}
                                        value={schritt.frist_tage ?? ''}
                                        onChange={(e) =>
                                            setzeSchritt(schritt.id, {
                                                frist_tage:
                                                    e.target.value === ''
                                                        ? null
                                                        : Number(
                                                              e.target.value,
                                                          ),
                                            })
                                        }
                                    />
                                </FormField>
                                <FormField
                                    id="s-gegen"
                                    label="Gegenstück"
                                    hint="z. B. «Login erstellen» ↔ «Login sperren»"
                                >
                                    <NativeSelect
                                        id="s-gegen"
                                        value={schritt.gegenstueck ?? ''}
                                        onChange={(e) =>
                                            setzeSchritt(schritt.id, {
                                                gegenstueck:
                                                    e.target.value || null,
                                            })
                                        }
                                    >
                                        <option value="">Keines</option>
                                        {data.phasen
                                            .filter(
                                                (p) => p.key !== schritt.phase,
                                            )
                                            .map((p) => (
                                                <optgroup
                                                    key={p.key}
                                                    label={p.titel}
                                                >
                                                    {data.schritte
                                                        .filter(
                                                            (s) =>
                                                                s.phase ===
                                                                p.key,
                                                        )
                                                        .map((s) => (
                                                            <option
                                                                key={s.id}
                                                                value={s.id}
                                                            >
                                                                {`${nummer(data.schritte, s)}. ${s.text}`}
                                                            </option>
                                                        ))}
                                                </optgroup>
                                            ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField id="s-phase" label="Phase">
                                    <NativeSelect
                                        id="s-phase"
                                        value={schritt.phase}
                                        onChange={(e) => {
                                            setzeSchritt(schritt.id, {
                                                phase: e.target.value,
                                                gegenstueck: null,
                                            });
                                            setAktiv(e.target.value);
                                        }}
                                    >
                                        {data.phasen.map((p) => (
                                            <option key={p.key} value={p.key}>
                                                {p.titel}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                </FormField>
                                <FormField
                                    id="s-bemerkung"
                                    label="Bemerkungen"
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="s-bemerkung"
                                        rows={2}
                                        value={schritt.bemerkung}
                                        onChange={(e) =>
                                            setzeSchritt(schritt.id, {
                                                bemerkung: e.target.value,
                                            })
                                        }
                                    />
                                </FormField>
                                <div className="flex flex-wrap gap-2 sm:col-span-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setGewaehlt(null)}
                                    >
                                        Fertig
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            schrittEntfernen(schritt.id)
                                        }
                                    >
                                        <Trash2 /> Schritt entfernen
                                    </Button>
                                </div>
                            </div>
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
                                ablauf ? `/ablaeufe/${ablauf.id}` : '/ablaeufe'
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

AblaufForm.layout = {
    breadcrumbs: [{ title: 'Abläufe Verwaltung', href: '/ablaeufe' }],
};
