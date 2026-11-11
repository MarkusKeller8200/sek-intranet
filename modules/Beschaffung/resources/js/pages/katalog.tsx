import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    ExternalLink,
    ImageOff,
    ImageUp,
    LayoutGrid,
    Plug,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import DataGrid from '@/components/data-grid';
import type { CellChange, GridColumn } from '@/components/grid/types';
import PageHeader from '@/components/page-header';
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
import { postJson } from '@/lib/http';
import { moduleIcon } from '@/lib/module-icons';

type Zeile = {
    id: string;
    bild: string;
    gruppe: string;
    bestellnummer: string;
    bezeichnung: string;
    modell: string;
    beschreibung: string;
    preis: string;
    kosten: string;
    preis_hinweis: string;
    zubehoer: string;
    standort: string;
    menge: string;
    zugang: string;
    nutzerkreis: string;
    verantwortung: string;
    lizenzen: string;
    url: string;
    wiki: string;
    angaben: string;
    status: string;
    pruefen: boolean;
    pruef_hinweis: string;
    quelle: string;
};

type Katalog = {
    key: string;
    title: string;
    bereich: string;
    icon: string;
    description: string;
    features: string[];
    spalten: string[];
    quellen: { titel: string; url: string }[];
};

type Props = {
    katalog: Katalog;
    zeilen: Zeile[];
    felder: Record<string, string>;
    kosten: Record<string, string>;
    status: Record<string, string>;
    farben: { status: Record<string, string>; kosten: Record<string, string> };
    plugin: { name: string; enabled: boolean } | null;
    darfBearbeiten: boolean;
};

type Antwort = { rows?: Zeile[]; row?: Zeile; id?: string; message?: string };

/** Preis «1200.00» → «CHF 1'200.00». */
function chf(preis: string): string {
    if (preis === '') {
        return '';
    }
    const [ganz, rappen = '00'] = preis.split('.');

    return `CHF ${ganz.replace(/\B(?=(\d{3})+(?!\d))/g, '’')}.${rappen}`;
}

function Bild({ zeile, gross = false }: { zeile: Zeile; gross?: boolean }) {
    const klasse = gross ? 'size-16' : 'size-8';

    return zeile.bild ? (
        <img
            src={zeile.bild}
            alt=""
            loading="lazy"
            className={`${klasse} shrink-0 rounded-md border bg-white object-contain`}
        />
    ) : (
        <span
            className={`${klasse} flex shrink-0 items-center justify-center rounded-md border border-dashed text-muted-foreground`}
            title="Noch kein Bild"
        >
            <ImageOff className="size-4" />
        </span>
    );
}

function Adresse({ url, text }: { url: string; text: string }) {
    return url ? (
        <a
            href={url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-primary underline-offset-2 hover:underline"
            onClick={(e) => e.stopPropagation()}
        >
            {text} <ExternalLink className="size-3" />
        </a>
    ) : null;
}

/** Zeilen neu vom Server holen (nach neuer Zeile oder Bild). */
async function neuLaden() {
    await new Promise<void>((resolve) =>
        router.reload({ only: ['zeilen'], onFinish: () => resolve() }),
    );
}

/** Ein Katalog unter «Bestellen» als bearbeitbare Tabelle (#198). */
export default function BeschaffungKatalog({
    katalog,
    zeilen,
    felder,
    kosten,
    status,
    farben,
    plugin,
    darfBearbeiten,
}: Props) {
    const Icon = moduleIcon(katalog.icon);
    const datei = useRef<HTMLInputElement>(null);
    const [bildFuer, setBildFuer] = useState<Zeile | null>(null);
    const [loeschen, setLoeschen] = useState<{
        zeilen: Zeile[];
        clear: () => void;
    } | null>(null);
    const zuPruefen = zeilen.filter((z) => z.pruefen).length;
    const ohneBild = zeilen.filter((z) => !z.bild).length;

    const columns = useMemo<GridColumn<Zeile>[]>(() => {
        const sichtbar = new Set(katalog.spalten);
        const spalte = (
            id: keyof Zeile,
            opts: Partial<GridColumn<Zeile>> = {},
        ): GridColumn<Zeile> => ({
            id,
            title: felder[id] ?? id,
            type: 'text',
            editable: darfBearbeiten,
            hidden: !sichtbar.has(id),
            ...opts,
        });

        return [
            spalte('bild', {
                editable: false,
                render: (z) => <Bild zeile={z} />,
                className: 'w-14',
            }),
            spalte('bestellnummer'),
            spalte('bezeichnung', {
                required: true,
                hidden: false,
                render: (z) => (
                    <span className="flex items-center gap-1.5">
                        <span className="font-medium" translate="no">
                            {z.bezeichnung}
                        </span>
                        {z.pruefen && (
                            <TriangleAlert
                                className="size-3.5 shrink-0 text-warning"
                                aria-label="Prüfen"
                            />
                        )}
                    </span>
                ),
                search: (z) =>
                    [z.modell, z.beschreibung, z.angaben, z.gruppe].join(' '),
            }),
            spalte('gruppe'),
            spalte('modell'),
            spalte('beschreibung'),
            spalte('preis', {
                render: (z) => (
                    <span className="tabular-nums">{chf(z.preis)}</span>
                ),
                className: 'text-right',
            }),
            spalte('kosten', {
                type: 'select',
                options: kosten,
                colors: farben.kosten,
            }),
            spalte('preis_hinweis'),
            spalte('zubehoer'),
            spalte('standort'),
            spalte('menge'),
            spalte('zugang'),
            spalte('nutzerkreis'),
            spalte('verantwortung'),
            spalte('lizenzen'),
            spalte('url', {
                render: (z) => <Adresse url={z.url} text="Webseite" />,
            }),
            spalte('wiki', {
                render: (z) => <Adresse url={z.wiki} text="ICT-Wiki" />,
            }),
            spalte('angaben'),
            spalte('status', {
                type: 'select',
                options: status,
                colors: farben.status,
                required: true,
            }),
            spalte('pruefen', {
                type: 'bool',
                hidden: !(sichtbar.has('pruefen') || darfBearbeiten),
            }),
            spalte('pruef_hinweis', {
                hidden: !(sichtbar.has('pruefen') || darfBearbeiten),
            }),
        ];
    }, [katalog.spalten, felder, kosten, status, farben, darfBearbeiten]);

    const saveCells = async (changes: CellChange<Zeile>[]) => {
        const res = await postJson<Antwort>(
            `/beschaffung/${katalog.key}/zellen`,
            {
                changes: changes.map((c) => ({
                    id: c.id,
                    field: c.field,
                    value: c.value,
                })),
            },
        );
        if (!res.rows) {
            throw new Error(res.message ?? 'Speichern fehlgeschlagen.');
        }

        return res.rows;
    };

    const neueZeile = async () => {
        const res = await postJson<Antwort>(
            `/beschaffung/${katalog.key}/zeile`,
        );
        if (!res.id) {
            toast.error(res.message ?? 'Neue Zeile nicht möglich.');

            return null;
        }
        await neuLaden();

        return res.id;
    };

    const bildHochladen = async (zeile: Zeile, file: File) => {
        const res = await postJson<Antwort>(
            `/beschaffung/bild/${zeile.id}`,
            {},
            { bild: file },
        );
        if (!res.row) {
            toast.error(res.message ?? 'Bild konnte nicht gespeichert werden.');

            return;
        }
        toast.success('Bild gespeichert.');
        await neuLaden();
    };

    const bildEntfernen = async (zeile: Zeile) => {
        const res = await postJson<Antwort>(
            `/beschaffung/bild/${zeile.id}/entfernen`,
        );
        if (!res.row) {
            toast.error(res.message ?? 'Bild konnte nicht entfernt werden.');

            return;
        }
        toast.success('Bild entfernt.');
        await neuLaden();
    };

    return (
        <>
            <Head title={katalog.title} />
            <div className="flex min-w-0 flex-1 flex-col gap-4 p-4 md:p-6">
                <div className="flex items-start gap-4">
                    <div className="flex size-12 shrink-0 items-center justify-center rounded-xl border bg-muted text-brand">
                        <Icon className="size-6" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <PageHeader
                            title={katalog.title}
                            description={katalog.description}
                            actions={
                                <Button asChild variant="outline">
                                    <Link href="/beschaffung">
                                        <LayoutGrid /> Alle Kataloge
                                    </Link>
                                </Button>
                            }
                        />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                    <Badge variant="outline">{`${zeilen.length} Einträge`}</Badge>
                    {zuPruefen > 0 && (
                        <Badge
                            variant="outline"
                            className="border-warning/30 bg-warning-soft text-warning"
                        >
                            <TriangleAlert /> {`${zuPruefen} zu prüfen`}
                        </Badge>
                    )}
                    {ohneBild > 0 && (
                        <Badge variant="outline">{`${ohneBild} ohne Bild`}</Badge>
                    )}
                    <span>Bereich {katalog.bereich}</span>
                </div>

                <DataGrid
                    data={zeilen}
                    columns={columns}
                    storageKey={`intranet.grid.beschaffung.${katalog.key}.v1.${darfBearbeiten ? 'edit' : 'view'}`}
                    canEdit={darfBearbeiten}
                    onSaveCells={darfBearbeiten ? saveCells : undefined}
                    onAddRow={darfBearbeiten ? neueZeile : undefined}
                    selectable={darfBearbeiten}
                    searchPlaceholder="Bezeichnung, Modell, Gruppe …"
                    emptyText="Keine Einträge gefunden."
                    rowClassName={(z) =>
                        z.status === 'gestrichen' ? 'opacity-60' : ''
                    }
                    renderCard={(z) => (
                        <div className="flex items-start gap-3">
                            <Bild zeile={z} gross />
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span
                                        className="font-medium"
                                        translate="no"
                                    >
                                        {z.bezeichnung}
                                    </span>
                                    {z.preis && (
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {chf(z.preis)}
                                        </span>
                                    )}
                                    {z.pruefen && (
                                        <Badge
                                            variant="outline"
                                            className="border-warning/30 bg-warning-soft text-warning"
                                        >
                                            Prüfen
                                        </Badge>
                                    )}
                                </div>
                                {z.modell && (
                                    <p className="text-sm" translate="no">
                                        {z.modell}
                                    </p>
                                )}
                                {z.beschreibung && (
                                    <p
                                        className="text-sm text-muted-foreground"
                                        translate="no"
                                    >
                                        {z.beschreibung}
                                    </p>
                                )}
                                {z.pruef_hinweis && (
                                    <p className="text-xs text-warning">
                                        {z.pruef_hinweis}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                    rowMenu={(z) => [
                        ...(z.url
                            ? [
                                  {
                                      label: 'Webseite öffnen',
                                      icon: <ExternalLink />,
                                      onSelect: () =>
                                          window.open(
                                              z.url,
                                              '_blank',
                                              'noopener',
                                          ),
                                  },
                              ]
                            : []),
                        ...(z.wiki
                            ? [
                                  {
                                      label: 'Im ICT-Wiki öffnen',
                                      icon: <ExternalLink />,
                                      onSelect: () =>
                                          window.open(
                                              z.wiki,
                                              '_blank',
                                              'noopener',
                                          ),
                                  },
                              ]
                            : []),
                        ...(darfBearbeiten
                            ? [
                                  {
                                      label: z.bild
                                          ? 'Bild ersetzen …'
                                          : 'Bild hochladen …',
                                      icon: <ImageUp />,
                                      onSelect: () => {
                                          setBildFuer(z);
                                          window.setTimeout(
                                              () => datei.current?.click(),
                                              0,
                                          );
                                      },
                                  },
                                  ...(z.bild
                                      ? [
                                            {
                                                label: 'Bild entfernen',
                                                icon: <ImageOff />,
                                                onSelect: () =>
                                                    void bildEntfernen(z),
                                            },
                                        ]
                                      : []),
                                  {
                                      label: 'Eintrag löschen …',
                                      icon: <Trash2 />,
                                      destructive: true,
                                      onSelect: () =>
                                          setLoeschen({
                                              zeilen: [z],
                                              clear: () => undefined,
                                          }),
                                  },
                              ]
                            : []),
                    ]}
                    bulkActions={
                        darfBearbeiten
                            ? (selected, clear) => (
                                  <Button
                                      variant="destructive"
                                      size="sm"
                                      onClick={() =>
                                          setLoeschen({
                                              zeilen: selected,
                                              clear,
                                          })
                                      }
                                  >
                                      <Trash2 /> Auswahl löschen
                                  </Button>
                              )
                            : undefined
                    }
                />
                <input
                    ref={datei}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    aria-label="Bild für den Eintrag wählen"
                    onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file && bildFuer) {
                            void bildHochladen(bildFuer, file);
                        }
                        e.target.value = '';
                    }}
                />
                <AlertDialog
                    open={loeschen !== null}
                    onOpenChange={(open) => !open && setLoeschen(null)}
                    title={`${loeschen?.zeilen.length ?? 0} Einträge löschen?`}
                    description={`${(loeschen?.zeilen ?? [])
                        .slice(0, 8)
                        .map((z) => z.bezeichnung)
                        .join(
                            ', ',
                        )}${(loeschen?.zeilen.length ?? 0) > 8 ? ' …' : ''}. Hochgeladene Bilder werden mit gelöscht. Im Änderungsverlauf bleibt jede Löschung nachvollziehbar.`}
                    confirmLabel="Löschen"
                    destructive
                    onConfirm={() => {
                        const auswahl = loeschen;
                        if (!auswahl) {
                            return;
                        }
                        router.post(
                            `/beschaffung/${katalog.key}/loeschen`,
                            { ids: auswahl.zeilen.map((z) => z.id) },
                            {
                                preserveScroll: true,
                                onSuccess: () => auswahl.clear(),
                            },
                        );
                        setLoeschen(null);
                    }}
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Geplante Funktionen</CardTitle>
                            <CardDescription>
                                Erster Entwurf – wird mit dem Kollegium
                                geschärft, bevor das Bestellen gebaut wird.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-2">
                                {katalog.features.map((feature) => (
                                    <li
                                        key={feature}
                                        className="flex items-start gap-2 text-sm"
                                    >
                                        <Check className="mt-0.5 size-4 shrink-0 text-brand" />
                                        <span>{feature}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>So geht es weiter</CardTitle>
                            <CardDescription>
                                Die Liste kann bereits gepflegt werden; das
                                Bestellen selbst folgt später.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <ol className="list-decimal space-y-1 pl-5">
                                <li>Bedürfnisse und Abläufe klären</li>
                                <li>Liste prüfen und ergänzen</li>
                                <li>
                                    Bestellen im Prototyp umsetzen und testen
                                </li>
                            </ol>
                            {plugin && (
                                <div className="flex flex-wrap items-center gap-3 rounded-lg border p-3">
                                    <Plug className="size-4 text-brand" />
                                    <span className="flex-1">
                                        Später mit dem Plugin{' '}
                                        <strong>{plugin.name}</strong> (
                                        {plugin.enabled
                                            ? 'eingeschaltet'
                                            : 'ausgeschaltet'}
                                        ).
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Quellen im ICT-Wiki</CardTitle>
                        <CardDescription>
                            Aus diesen Confluence-Seiten wurde die Liste
                            übernommen. Gepflegt wird ab jetzt hier.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="space-y-2 text-sm">
                            {katalog.quellen.map((q) => (
                                <li key={q.url} className="flex flex-col">
                                    <span translate="no">{q.titel}</span>
                                    <a
                                        href={q.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 break-all text-primary underline-offset-2 hover:underline"
                                        translate="no"
                                    >
                                        {q.url}
                                        <ExternalLink className="size-3 shrink-0" />
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

BeschaffungKatalog.layout = (props: { katalog: Katalog }) => ({
    breadcrumbs: [
        { title: 'Bestellen', href: '/beschaffung' },
        {
            title: props.katalog.title,
            href: `/beschaffung/${props.katalog.key}`,
        },
    ],
});
