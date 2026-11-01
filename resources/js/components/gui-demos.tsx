import {
    AlertTriangle,
    Bell,
    ChevronDown,
    ChevronRight,
    Download,
    FileText,
    Home,
    Info,
    Inbox,
    MoreHorizontal,
    Pencil,
    Plus,
    Save,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import DataGrid from '@/components/data-grid';
import type { GridColumn } from '@/components/data-grid';
import EmptyState from '@/components/empty-state';
import FormField from '@/components/form-field';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import SelectField from '@/components/select-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Toggle } from '@/components/ui/toggle';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/** Ein Beispiel mit Titel, kurzer Erklärung und Vorschau. */
function Demo({
    title,
    hint,
    children,
    className,
}: {
    title: string;
    hint?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className="grid gap-2">
            <div>
                <h3 className="text-sm font-semibold">{title}</h3>
                {hint && (
                    <p className="text-xs text-muted-foreground">{hint}</p>
                )}
            </div>
            <div
                className={cn(
                    'flex flex-wrap items-center gap-3 rounded-lg border bg-background p-4',
                    className,
                )}
            >
                {children}
            </div>
        </section>
    );
}

function MenuDemo() {
    const [active, setActive] = useState('mitteilungen');
    const [open, setOpen] = useState(true);
    const items = [
        { key: 'start', label: 'Start', icon: Home },
        { key: 'mitteilungen', label: 'Mitteilungen', icon: Bell },
        { key: 'dokumente', label: 'Dokumente & Links', icon: FileText },
    ];

    return (
        <>
            <Demo
                title="Seitenleiste mit Hauptmenü und Untermenü"
                hint="Hauptmenü klappt auf und zu; der aktive Eintrag ist hervorgehoben."
                className="block"
            >
                <nav className="w-64 rounded-md border bg-sidebar p-2 text-sm text-sidebar-foreground">
                    <button
                        type="button"
                        className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase hover:bg-sidebar-accent"
                        onClick={() => setOpen((o) => !o)}
                        aria-expanded={open}
                    >
                        {open ? (
                            <ChevronDown className="size-3.5" />
                        ) : (
                            <ChevronRight className="size-3.5" />
                        )}
                        <span>Kommunikation</span>
                    </button>
                    {open && (
                        <ul className="mt-1 grid gap-0.5">
                            {items.map((item) => (
                                <li key={item.key}>
                                    <button
                                        type="button"
                                        onClick={() => setActive(item.key)}
                                        className={cn(
                                            'flex w-full items-center gap-2 rounded-md px-2 py-1.5 hover:bg-sidebar-accent',
                                            active === item.key &&
                                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                                        )}
                                        aria-current={
                                            active === item.key
                                                ? 'page'
                                                : undefined
                                        }
                                    >
                                        <item.icon className="size-4" />
                                        <span>{item.label}</span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </nav>
            </Demo>
            <Demo
                title="Aktionsmenü (Dropdown-Menü)"
                hint="Für Aktionen zu einer Zeile oder Seite; Gefährliches zuletzt und rot."
            >
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="icon" title="Aktionen">
                            <MoreHorizontal />
                            <span className="sr-only">Aktionen</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Aktionen</DropdownMenuLabel>
                        <DropdownMenuItem>
                            <Pencil /> Bearbeiten
                        </DropdownMenuItem>
                        <DropdownMenuItem>
                            <Download /> Exportieren
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem variant="destructive">
                            <Trash2 /> Löschen
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </Demo>
            <Demo
                title="Breadcrumbs"
                hint="Zeigen, wo man ist; der erste Eintrag öffnet das Menü wieder."
            >
                <Breadcrumb>
                    <BreadcrumbList>
                        <BreadcrumbItem>
                            <BreadcrumbLink href="#">Personen</BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbPage>Muster Anna</BreadcrumbPage>
                        </BreadcrumbItem>
                    </BreadcrumbList>
                </Breadcrumb>
            </Demo>
        </>
    );
}

type DemoPerson = {
    id: string;
    name: string;
    person_type: string;
    school: string;
    email: string;
    active: boolean;
    birthday: string;
};

const DEMO_PEOPLE: DemoPerson[] = [
    ['Muster Anna', 'lehrperson', 'hinterbirch', '1980-03-12', true],
    ['Beispiel Ben', 'schulleitung', 'mettmenriet', '1975-11-02', true],
    ['Probe Carla', 'mitarbeitende', 'verwaltung', '1990-06-21', true],
    ['Fiktiv Dario', 'lehrperson', 'mettmenriet', '1985-01-30', false],
    ['Vorlage Elena', 'extern', 'gesamt', '1992-09-09', true],
    ['Platzhalter Finn', 'lehrperson', 'hinterbirch', '1988-04-17', true],
].map(([name, type, school, birthday, active], i) => ({
    id: `demo-${i + 1}`,
    name: String(name),
    person_type: String(type),
    school: String(school),
    email: `${String(name).toLowerCase().replace(/\s+/g, '.')}@example.test`,
    active: Boolean(active),
    birthday: String(birthday),
}));

function TableDemo() {
    const [rows, setRows] = useState(DEMO_PEOPLE);
    const columns: GridColumn<DemoPerson>[] = [
        { id: 'name', title: 'Name', editable: true, required: true },
        {
            id: 'person_type',
            title: 'Personenart',
            type: 'select',
            editable: true,
            options: {
                lehrperson: 'Lehrperson',
                schulleitung: 'Schulleitung',
                mitarbeitende: 'Mitarbeiter:in',
                extern: 'Externe Fachperson',
            },
            render: (p) => <OptionBadge list="personTypes" value={p.person_type} />,
        },
        {
            id: 'school',
            title: 'Schulhaus',
            type: 'select',
            editable: true,
            options: {
                gesamt: 'Gesamte Schule',
                hinterbirch: 'Schulhaus Hinterbirch',
                mettmenriet: 'Schulhaus Mettmenriet',
                verwaltung: 'Schulverwaltung',
            },
            render: (p) => <OptionBadge list="schools" value={p.school} dot />,
        },
        { id: 'email', title: 'E-Mail', type: 'email', editable: true },
        { id: 'birthday', title: 'Geburtstag', type: 'date', hidden: true },
        { id: 'active', title: 'Aktiv', type: 'bool', editable: true },
    ];

    return (
        <>
            <Demo
                title="Bearbeitbare Tabelle (wie Personen)"
                hint="Erfundene Daten, Änderungen bleiben nur in diesem Fenster. Suche, Filter je Spalte, Sortierung, Spalten, Auswahl, Bearbeiten wie in Excel."
                className="block p-0"
            >
                <DataGrid
                    data={rows}
                    columns={columns}
                    storageKey="intranet.grid.gui-demo"
                    canEdit
                    onSaveCells={(changes) => {
                        const next = rows.map((r) => {
                            const own = changes.filter((c) => c.id === r.id);

                            return own.reduce(
                                (acc, c) => ({ ...acc, [c.field]: c.value }),
                                r,
                            );
                        });
                        setRows(next);

                        return Promise.resolve(
                            next.filter((r) =>
                                changes.some((c) => c.id === r.id),
                            ),
                        );
                    }}
                    searchPlaceholder="Name, E-Mail …"
                    emptyText="Keine Personen gefunden."
                    rowClassName={(p) => (p.active ? '' : 'opacity-60')}
                />
            </Demo>
            <Demo
                title="Einfache Tabelle"
                hint="Für kurze, nur lesbare Listen ohne Bearbeiten."
                className="block p-0"
            >
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Schulhaus</TableHead>
                            <TableHead className="text-right">
                                Klassen
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {DEMO_PEOPLE.slice(0, 3).map((p, i) => (
                            <TableRow key={p.id}>
                                <TableCell translate="no">{p.name}</TableCell>
                                <TableCell>
                                    <OptionBadge
                                        list="schools"
                                        value={p.school}
                                        dot
                                    />
                                </TableCell>
                                <TableCell className="text-right tabular-nums">
                                    {i + 2}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Demo>
        </>
    );
}

const CLASSES = {
    m_a1a: 'M_A1a',
    m_b1b: 'M_B1b',
    h_a1a: 'H_A1a',
    h_a2a: 'H_A2a',
};

function DropdownDemo() {
    const [empty, setEmpty] = useState('');
    const [preset, setPreset] = useState('h_a1a');
    const [many, setMany] = useState<string[]>(['m_a1a']);

    return (
        <>
            <Demo
                title="Ohne Vorauswahl"
                hint="Zeigt «Bitte wählen», bis etwas gewählt ist."
            >
                <div className="grid w-64 gap-1.5">
                    <Label htmlFor="gui-dd-empty">Klasse</Label>
                    <SelectField
                        id="gui-dd-empty"
                        value={empty}
                        options={CLASSES}
                        onChange={setEmpty}
                    />
                </div>
            </Demo>
            <Demo
                title="Mit Vorauswahl"
                hint="Ein sinnvoller Wert ist bereits gewählt."
            >
                <div className="grid w-64 gap-1.5">
                    <Label htmlFor="gui-dd-preset">Klasse</Label>
                    <SelectField
                        id="gui-dd-preset"
                        value={preset}
                        options={CLASSES}
                        onChange={setPreset}
                    />
                </div>
            </Demo>
            <Demo title="Mit Fehler" hint="Pflichtfeld nicht ausgefüllt.">
                <FormField
                    id="gui-dd-error"
                    label="Schulhaus *"
                    error="Bitte ein Schulhaus wählen."
                    className="w-64"
                >
                    <SelectField
                        id="gui-dd-error"
                        value=""
                        options={{ hinterbirch: 'Schulhaus Hinterbirch' }}
                        onChange={() => undefined}
                        invalid
                    />
                </FormField>
            </Demo>
            <Demo
                title="Mehrfachauswahl"
                hint="Mehrere Werte; die Anzahl steht im Knopf."
            >
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="w-64 justify-between">
                            <span>
                                {many.length === 0
                                    ? 'Klassen wählen'
                                    : many.length === 1
                                      ? '1 Klasse gewählt'
                                      : `${many.length} Klassen gewählt`}
                            </span>
                            <ChevronDown className="opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent className="w-64">
                        {Object.entries(CLASSES).map(([key, name]) => (
                            <DropdownMenuCheckboxItem
                                key={key}
                                checked={many.includes(key)}
                                onSelect={(e: Event) => e.preventDefault()}
                                onCheckedChange={(checked: boolean) =>
                                    setMany((m) =>
                                        checked
                                            ? [...m, key]
                                            : m.filter((k) => k !== key),
                                    )
                                }
                            >
                                <span translate="no">{name}</span>
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </Demo>
        </>
    );
}

function ButtonDemo() {
    const [loading, setLoading] = useState(false);

    return (
        <>
            <Demo
                title="Varianten"
                hint="Pro Bereich höchstens ein Haupt-Button; Löschen immer als Gefahr-Button mit Rückfrage."
            >
                <Button>Speichern</Button>
                <Button variant="secondary">Abbrechen</Button>
                <Button variant="outline">Exportieren</Button>
                <Button variant="ghost">Mehr anzeigen</Button>
                <Button variant="destructive">Löschen</Button>
                <Button variant="link">Zur Übersicht</Button>
            </Demo>
            <Demo title="Grössen">
                <Button size="sm">Klein</Button>
                <Button>Normal</Button>
                <Button size="lg">Gross</Button>
            </Demo>
            <Demo
                title="Mit Symbol und nur Symbol"
                hint="Ein Knopf nur mit Symbol braucht einen Titel (Tooltip, Bildschirmleser)."
            >
                <Button>
                    <Plus /> Neue Person
                </Button>
                <Button variant="outline">
                    <Download /> CSV
                </Button>
                <Button variant="ghost" size="icon" title="Bearbeiten">
                    <Pencil />
                    <span className="sr-only">Bearbeiten</span>
                </Button>
            </Demo>
            <Demo title="Zustände" hint="Lädt: gesperrt mit Spinner; gesperrt: nicht klickbar.">
                <Button
                    disabled={loading}
                    onClick={() => {
                        setLoading(true);
                        window.setTimeout(() => setLoading(false), 1500);
                    }}
                >
                    {loading ? <Spinner /> : <Save />}
                    <span>{loading ? 'Speichert …' : 'Speichern'}</span>
                </Button>
                <Button disabled>Gesperrt</Button>
            </Demo>
        </>
    );
}

function FormDemo() {
    const [agree, setAgree] = useState(true);

    return (
        <>
            <Demo title="Textfelder" className="grid md:grid-cols-2">
                <FormField id="gui-f-name" label="Name *" hint="Wie im Ausweis.">
                    <Input id="gui-f-name" defaultValue="Muster Anna" />
                </FormField>
                <FormField
                    id="gui-f-mail"
                    label="E-Mail"
                    error="Diese E-Mail wird bereits verwendet."
                >
                    <Input
                        id="gui-f-mail"
                        type="email"
                        defaultValue="anna.muster@example.test"
                        aria-invalid
                    />
                </FormField>
                <FormField id="gui-f-number" label="Anzahl">
                    <Input id="gui-f-number" type="number" defaultValue={24} />
                </FormField>
                <FormField id="gui-f-date" label="Datum">
                    <Input id="gui-f-date" type="date" defaultValue="2026-11-01" />
                </FormField>
                <FormField
                    id="gui-f-text"
                    label="Bemerkung"
                    className="md:col-span-2"
                >
                    <Textarea id="gui-f-text" rows={3} placeholder="Optional" />
                </FormField>
                <FormField id="gui-f-off" label="Gesperrt">
                    <Input id="gui-f-off" disabled defaultValue="nicht änderbar" />
                </FormField>
            </Demo>
            <Demo title="Kontrollkästchen">
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="gui-f-check"
                        checked={agree}
                        onCheckedChange={(c: boolean | 'indeterminate') =>
                            setAgree(c === true)
                        }
                    />
                    <div className="grid gap-0.5">
                        <Label htmlFor="gui-f-check">Im Telefonbuch anzeigen</Label>
                        <p className="text-xs text-muted-foreground">
                            Nur Schul-Telefon, nie private Nummern.
                        </p>
                    </div>
                </div>
            </Demo>
        </>
    );
}

function BadgeDemo() {
    return (
        <>
            <Demo title="Grundformen">
                <Badge>Neu</Badge>
                <Badge variant="secondary">Entwurf</Badge>
                <Badge variant="outline">Vorgabe</Badge>
                <Badge variant="destructive">Fehler</Badge>
                <Badge variant="secondary" className="tabular-nums">
                    12
                </Badge>
            </Demo>
            <Demo
                title="Auswahlwerte im Farbkonzept"
                hint="Farbe kommt aus config/intranet.php → colors, nie fest im Code."
            >
                <OptionBadge list="guiStatuses" value="entwicklung" />
                <OptionBadge list="guiStatuses" value="pruefung" />
                <OptionBadge list="guiStatuses" value="freigegeben" />
                <OptionBadge list="roles" value="lehrperson" />
                <OptionBadge list="schools" value="hinterbirch" dot />
            </Demo>
        </>
    );
}

function MessageDemo() {
    return (
        <>
            <Demo title="Hinweisboxen" className="grid">
                <Alert>
                    <Info />
                    <AlertTitle>Prototyp</AlertTitle>
                    <AlertDescription>
                        Nur erfundene Testdaten verwenden.
                    </AlertDescription>
                </Alert>
                <Alert variant="destructive">
                    <AlertTriangle />
                    <AlertTitle>Speichern fehlgeschlagen</AlertTitle>
                    <AlertDescription>
                        Bitte später erneut versuchen.
                    </AlertDescription>
                </Alert>
            </Demo>
            <Demo
                title="Rückfrage-Dialog und Toast"
                hint="Vor Löschen oder unumkehrbaren Schritten immer nachfragen; danach kurze Meldung."
            >
                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="destructive">
                            <Trash2 /> Löschen …
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Eintrag löschen?</DialogTitle>
                            <DialogDescription>
                                Das lässt sich nicht rückgängig machen.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="secondary">Abbrechen</Button>
                            </DialogClose>
                            <DialogClose asChild>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        toast.success('Beispiel: gelöscht.')
                                    }
                                >
                                    Löschen
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
                <Button
                    variant="outline"
                    onClick={() => toast.success('Gespeichert.')}
                >
                    Toast Erfolg
                </Button>
                <Button
                    variant="outline"
                    onClick={() => toast.error('Etwas ist schiefgelaufen.')}
                >
                    Toast Fehler
                </Button>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button variant="ghost" size="icon">
                            <Info />
                            <span className="sr-only">Info</span>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>Tooltip: kurze Erklärung</TooltipContent>
                </Tooltip>
            </Demo>
        </>
    );
}

function CardDemo() {
    return (
        <>
            <Demo title="Seitenkopf" className="block">
                <PageHeader
                    title="Mitteilungen"
                    description="Neuigkeiten für Lehrpersonen und Mitarbeitende."
                    actions={
                        <Button>
                            <Plus /> Neue Mitteilung
                        </Button>
                    }
                />
            </Demo>
            <Demo title="Karten und Kennzahlen" className="grid md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Karte</CardTitle>
                        <CardDescription>Mit Kopf, Inhalt und Fuss.</CardDescription>
                    </CardHeader>
                    <CardContent className="text-sm">
                        Inhalt der Karte.
                    </CardContent>
                    <CardFooter>
                        <Button size="sm" variant="outline">
                            Öffnen
                        </Button>
                    </CardFooter>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription className="flex items-center gap-2">
                            <Users className="size-4" />
                            <span>Personen</span>
                        </CardDescription>
                        <CardTitle className="text-3xl tabular-nums">142</CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardContent className="pt-6">
                        <EmptyState icon={Inbox} title="Noch nichts da">
                            Sobald Einträge erfasst sind, erscheinen sie hier.
                        </EmptyState>
                    </CardContent>
                </Card>
            </Demo>
        </>
    );
}

function ToggleDemo() {
    const [tab, setTab] = useState('uebersicht');
    const [bold, setBold] = useState(false);
    const [open, setOpen] = useState(false);

    return (
        <>
            <Demo title="Reiter (Umschaltgruppe)" className="grid">
                <ToggleGroup
                    type="single"
                    variant="outline"
                    value={tab}
                    onValueChange={(v: string) => v && setTab(v)}
                    className="justify-start"
                >
                    <ToggleGroupItem value="uebersicht" className="px-4">
                        Übersicht
                    </ToggleGroupItem>
                    <ToggleGroupItem value="einstellungen" className="px-4">
                        Einstellungen
                    </ToggleGroupItem>
                    <ToggleGroupItem value="protokoll" className="px-4">
                        Protokoll
                    </ToggleGroupItem>
                </ToggleGroup>
                <p className="text-sm text-muted-foreground">
                    {tab === 'uebersicht'
                        ? 'Inhalt «Übersicht»'
                        : tab === 'einstellungen'
                          ? 'Inhalt «Einstellungen»'
                          : 'Inhalt «Protokoll»'}
                </p>
            </Demo>
            <Demo title="Ein/Aus-Schalter">
                <Toggle
                    pressed={bold}
                    onPressedChange={setBold}
                    variant="outline"
                    aria-label="Nur aktive"
                >
                    Nur aktive
                </Toggle>
            </Demo>
            <Demo title="Aufklappbarer Bereich" className="block">
                <Collapsible open={open} onOpenChange={setOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="ghost" size="sm">
                            {open ? <ChevronDown /> : <ChevronRight />}
                            <span>Weitere Angaben</span>
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="px-3 pt-2 text-sm text-muted-foreground">
                        Selten gebrauchte Felder stehen hier.
                    </CollapsibleContent>
                </Collapsible>
            </Demo>
        </>
    );
}

function LoadingDemo() {
    return (
        <>
            <Demo title="Spinner">
                <Spinner />
                <span className="text-sm text-muted-foreground">Lädt …</span>
            </Demo>
            <Demo
                title="Platzhalter (Skeleton)"
                hint="Zeigt die Form des Inhalts, bis die Daten da sind."
                className="grid"
            >
                <Skeleton className="h-5 w-48" />
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-3/4" />
            </Demo>
        </>
    );
}

const DEMOS: Record<string, () => ReactNode> = {
    menue: MenuDemo,
    tabelle: TableDemo,
    dropdown: DropdownDemo,
    button: ButtonDemo,
    formular: FormDemo,
    badge: BadgeDemo,
    meldungen: MessageDemo,
    karten: CardDemo,
    umschalter: ToggleDemo,
    laden: LoadingDemo,
};

/** Live-Beispiele eines GUI-Bausteins (verwenden die echten Komponenten). */
export default function GuiDemo({ componentKey }: { componentKey: string }) {
    const Component = DEMOS[componentKey];

    return Component ? (
        <div className="grid gap-6">
            <Component />
        </div>
    ) : (
        <p className="text-sm text-muted-foreground">
            Für diesen Baustein gibt es noch keine Beispiele.
        </p>
    );
}
