import {
    AtSign,
    Bold,
    Copy,
    Download,
    FileSpreadsheet,
    FileText,
    Italic,
    LayoutGrid,
    List as ListIcon,
    Mail,
    MoreHorizontal,
    Pencil,
    Plug,
    Search,
    Trash2,
    Underline,
    User,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import FormField from '@/components/form-field';
import { Demo } from '@/components/gui-demos-extra';
import { AlertDialog } from '@/components/ui/alert-dialog';
import { Attachment } from '@/components/ui/attachment';
import type { AttachmentState } from '@/components/ui/attachment';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { CodeBlock } from '@/components/ui/code-block';
import { ContextMenu } from '@/components/ui/context-menu';
import { DonutChart } from '@/components/ui/donut-chart';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FieldGroup, FieldRow, FieldSet } from '@/components/ui/field';
import { FileDrop } from '@/components/ui/file-drop';
import { HoverCard } from '@/components/ui/hover-card';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Item, ItemList } from '@/components/ui/item';
import { KbdGroup, Kbd } from '@/components/ui/kbd';
import { LineChart } from '@/components/ui/line-chart';
import { NativeSelect } from '@/components/ui/native-select';
import {
    NavigationMenu,
    NavigationMenuContent,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    NavigationMenuTrigger,
} from '@/components/ui/navigation-menu';
import { Questionnaire } from '@/components/ui/questionnaire';
import { ResizablePanels } from '@/components/ui/resizable';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Toggle } from '@/components/ui/toggle';
import {
    Blockquote,
    H1,
    H2,
    H3,
    H4,
    InlineCode,
    Lead,
    List,
    Muted,
    Small,
    Text,
} from '@/components/ui/typography';

function TypographyDemo() {
    return (
        <>
            <Demo title="Überschriften und Text" className="block">
                <div className="grid gap-3">
                    <H1>Mitteilungen</H1>
                    <Lead>Neuigkeiten für Lehrpersonen und Mitarbeitende.</Lead>
                    <H2>Sporttag 2027</H2>
                    <Text>
                        Der Sporttag findet am Donnerstag statt. Bei schlechtem
                        Wetter gilt das Ersatzdatum.
                    </Text>
                    <H3>Ablauf</H3>
                    <List ordered>
                        <li>Besammlung beim Schulhaus</li>
                        <li>Posten nach Klassen</li>
                        <li>Rangverkündigung</li>
                    </List>
                    <H4>Mitnehmen</H4>
                    <List>
                        <li>Sportkleider und Trinkflasche</li>
                        <li>Sonnenschutz</li>
                    </List>
                    <Blockquote>Bewegung macht Schule.</Blockquote>
                    <Muted>Leiser Text für Hinweise und Quellen.</Muted>
                    <Small>Kleiner Text, z. B. Stand 01.11.2026</Small>
                </div>
            </Demo>
            <Demo title="Code im Text, Tasten und Trennlinie" className="grid">
                <Text>
                    <span>Geräte-Nr. im Feld </span>
                    <InlineCode>device_inventory</InlineCode>
                    <span> speichern.</span>
                </Text>
                <p className="flex flex-wrap items-center gap-2 text-sm">
                    <span>Suchen mit</span>
                    <KbdGroup keys={['Ctrl', 'K']} />
                    <span>Speichern mit</span>
                    <Kbd>Enter</Kbd>
                </p>
                <Separator />
                <div className="flex h-5 items-center gap-3 text-sm">
                    <span>Start</span>
                    <Separator orientation="vertical" />
                    <span>Personen</span>
                    <Separator orientation="vertical" />
                    <span>Dokumente</span>
                </div>
            </Demo>
        </>
    );
}

function InputGroupDemo() {
    const [query, setQuery] = useState('Muster');
    const [copied, setCopied] = useState(false);

    return (
        <Demo title="Eingabegruppe" className="grid gap-4 md:grid-cols-2">
            <FormField id="gui-ig-search" label="Suche">
                <InputGroup>
                    <InputGroupAddon>
                        <Search />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="gui-ig-search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Name, E-Mail …"
                    />
                    {query && (
                        <InputGroupButton
                            onClick={() => setQuery('')}
                            title="Leeren"
                        >
                            <X />
                            <span className="sr-only">Leeren</span>
                        </InputGroupButton>
                    )}
                </InputGroup>
            </FormField>
            <FormField
                id="gui-ig-mail"
                label="E-Mail"
                hint="Nur der Teil vor dem @."
            >
                <InputGroup>
                    <InputGroupAddon>
                        <AtSign />
                    </InputGroupAddon>
                    <InputGroupInput
                        id="gui-ig-mail"
                        defaultValue="anna.muster"
                    />
                    <InputGroupAddon>@sekbuelach.ch</InputGroupAddon>
                </InputGroup>
            </FormField>
            <FormField id="gui-ig-chf" label="Betrag">
                <InputGroup>
                    <InputGroupAddon>CHF</InputGroupAddon>
                    <InputGroupInput
                        id="gui-ig-chf"
                        inputMode="decimal"
                        defaultValue="49.90"
                    />
                </InputGroup>
            </FormField>
            <FormField id="gui-ig-link" label="Link zum Teilen">
                <InputGroup>
                    <InputGroupInput
                        id="gui-ig-link"
                        readOnly
                        value="https://intranet.sekbuelach.ch/termine"
                    />
                    <InputGroupButton
                        onClick={() => {
                            setCopied(true);
                            toast.success('Link kopiert.');
                        }}
                    >
                        <Copy /> {copied ? 'Kopiert' : 'Kopieren'}
                    </InputGroupButton>
                </InputGroup>
            </FormField>
        </Demo>
    );
}

function ButtonGroupDemo() {
    const [view, setView] = useState('liste');

    return (
        <>
            <Demo
                title="Export"
                hint="Zusammengehörende Aktionen ohne Abstand."
            >
                <ButtonGroup label="Exportieren">
                    <Button variant="outline">
                        <Download /> CSV
                    </Button>
                    <Button variant="outline">
                        <FileSpreadsheet /> Excel
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="outline"
                                size="icon"
                                title="Weitere"
                            >
                                <MoreHorizontal />
                                <span className="sr-only">Weitere</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuItem>PDF</DropdownMenuItem>
                            <DropdownMenuItem>Drucken</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </ButtonGroup>
            </Demo>
            <Demo
                title="Ansicht und Format"
                hint="Auswahl mit aria-pressed; Toggle für Format."
            >
                <ButtonGroup label="Ansicht">
                    <Button
                        variant={view === 'liste' ? 'secondary' : 'outline'}
                        aria-pressed={view === 'liste'}
                        onClick={() => setView('liste')}
                    >
                        <ListIcon /> Liste
                    </Button>
                    <Button
                        variant={view === 'kacheln' ? 'secondary' : 'outline'}
                        aria-pressed={view === 'kacheln'}
                        onClick={() => setView('kacheln')}
                    >
                        <LayoutGrid /> Kacheln
                    </Button>
                </ButtonGroup>
                <ButtonGroup label="Textformat">
                    <Toggle variant="outline" aria-label="Fett">
                        <Bold />
                    </Toggle>
                    <Toggle variant="outline" aria-label="Kursiv">
                        <Italic />
                    </Toggle>
                    <Toggle variant="outline" aria-label="Unterstrichen">
                        <Underline />
                    </Toggle>
                </ButtonGroup>
            </Demo>
        </>
    );
}

function ItemDemo() {
    return (
        <>
            <Demo title="Liste von Einträgen" className="block">
                <ItemList>
                    <Item
                        variant="plain"
                        media={<User />}
                        title={
                            <>
                                <span translate="no">Muster Anna</span>
                                <Badge variant="secondary">Lehrperson</Badge>
                            </>
                        }
                        description="anna.muster@example.test · Schulhaus Hinterbirch"
                        meta="seit 2019"
                        actions={
                            <Button
                                size="icon"
                                variant="ghost"
                                title="Bearbeiten"
                            >
                                <Pencil />
                                <span className="sr-only">Bearbeiten</span>
                            </Button>
                        }
                    />
                    <Item
                        variant="plain"
                        media={<FileText />}
                        title="Reglement Geräteausgabe.pdf"
                        description="Dokumente & Links · 240 kB"
                        meta="12.10.2026"
                        actions={
                            <Button
                                size="icon"
                                variant="ghost"
                                title="Herunterladen"
                            >
                                <Download />
                                <span className="sr-only">Herunterladen</span>
                            </Button>
                        }
                    />
                    <Item
                        variant="plain"
                        media={<Plug />}
                        title={
                            <>
                                <span>Jamf Pro</span>
                                <Badge variant="outline">aus</Badge>
                            </>
                        }
                        description="Geräteverwaltung – nicht eingerichtet"
                        actions={
                            <Switch
                                checked={false}
                                onCheckedChange={() => undefined}
                                aria-label="Jamf Pro einschalten"
                            />
                        }
                    />
                </ItemList>
            </Demo>
            <Demo title="Einzeln, gedämpft" className="block">
                <Item
                    variant="muted"
                    media={<Mail />}
                    title="3 neue Mitteilungen"
                    description="Seit deinem letzten Besuch."
                />
            </Demo>
        </>
    );
}

function QuestionnaireDemo() {
    const [key, setKey] = useState(0);

    return (
        <Demo
            title="Umfrage"
            hint="Erfundene Fragen; nichts wird gespeichert."
            className="block max-w-xl"
        >
            <Questionnaire
                key={key}
                questions={[
                    {
                        id: 'q1',
                        type: 'single',
                        title: 'Wie zufrieden bist du mit den iPads?',
                        options: [
                            'Sehr zufrieden',
                            'Zufrieden',
                            'Eher unzufrieden',
                            'Unzufrieden',
                        ],
                        required: true,
                    },
                    {
                        id: 'q2',
                        type: 'multiple',
                        title: 'Welche Apps brauchst du im Unterricht?',
                        hint: 'Mehrere möglich.',
                        options: [
                            'Keynote',
                            'Pages',
                            'OneNote',
                            'Lernapp LMVZ',
                        ],
                    },
                    {
                        id: 'q3',
                        type: 'text',
                        title: 'Was sollen wir verbessern?',
                    },
                ]}
                onSubmit={() => {
                    toast.success('Beispiel: Antworten gesendet.');
                    setKey((k) => k + 1);
                }}
            />
        </Demo>
    );
}

function AlertDialogDemo() {
    const [open, setOpen] = useState(false);
    const [open2, setOpen2] = useState(false);

    return (
        <Demo
            title="Rückfrage"
            hint="Schliesst nicht mit Klick daneben; Fokus zuerst auf «Abbrechen»."
        >
            <Button variant="destructive" onClick={() => setOpen(true)}>
                <Trash2 /> Person löschen …
            </Button>
            <AlertDialog
                open={open}
                onOpenChange={setOpen}
                title="Person löschen?"
                description="Muster Anna wird mit allen Angaben gelöscht. Das lässt sich nicht rückgängig machen."
                confirmLabel="Löschen"
                destructive
                onConfirm={() => toast.success('Beispiel: gelöscht.')}
            />
            <Button variant="outline" onClick={() => setOpen2(true)}>
                Freigeben …
            </Button>
            <AlertDialog
                open={open2}
                onOpenChange={setOpen2}
                title="Version freigeben?"
                description="Die Version wird verbindlich; alle verwendenden Seiten werden zur Überarbeitung vorgemerkt."
                confirmLabel="Freigeben"
                onConfirm={() => toast.success('Beispiel: freigegeben.')}
            />
        </Demo>
    );
}

function ContextMenuDemo() {
    const actions = [
        {
            label: 'Öffnen',
            icon: <FileText />,
            onSelect: () => toast.success('Beispiel: öffnen'),
        },
        {
            label: 'Umbenennen',
            icon: <Pencil />,
            onSelect: () => toast.success('Beispiel: umbenennen'),
        },
        {
            label: 'Herunterladen',
            icon: <Download />,
            onSelect: () => toast.success('Beispiel: herunterladen'),
        },
        {
            label: 'Löschen',
            icon: <Trash2 />,
            destructive: true,
            separator: true,
            onSelect: () => toast.success('Beispiel: löschen'),
        },
    ];

    return (
        <Demo
            title="Kontextmenü"
            hint="Rechtsklick, langes Drücken (iPad), Shift+F10 oder Menütaste. Dieselben Aktionen stehen im Knopf «…»."
            className="block"
        >
            <ContextMenu actions={actions} label="Aktionen zu Reglement.pdf">
                <div className="flex items-center justify-between gap-3 rounded-md border p-3 text-sm">
                    <span className="flex items-center gap-2">
                        <FileText className="size-4 text-muted-foreground" />
                        <span>Reglement Geräteausgabe.pdf</span>
                    </span>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                size="icon"
                                variant="ghost"
                                title="Aktionen"
                            >
                                <MoreHorizontal />
                                <span className="sr-only">Aktionen</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {actions.map((a) => (
                                <DropdownMenuItem
                                    key={a.label}
                                    onSelect={a.onSelect}
                                    variant={
                                        a.destructive
                                            ? 'destructive'
                                            : 'default'
                                    }
                                >
                                    {a.icon} {a.label}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </ContextMenu>
        </Demo>
    );
}

function HoverCardDemo() {
    return (
        <Demo
            title="Vorschaukarte"
            hint="Mit Maus überfahren oder mit Tab fokussieren."
        >
            <p className="text-sm">
                <span>Klassenlehrperson: </span>
                <HoverCard
                    trigger={
                        <a
                            href="#person"
                            className="text-primary underline"
                            translate="no"
                        >
                            Muster Anna
                        </a>
                    }
                >
                    <div className="flex gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-sm font-medium">
                            AM
                        </span>
                        <div className="grid gap-0.5">
                            <span className="font-medium" translate="no">
                                Muster Anna
                            </span>
                            <span className="text-xs text-muted-foreground">
                                Lehrperson · M_A1a
                            </span>
                            <span
                                className="text-xs text-muted-foreground"
                                translate="no"
                            >
                                anna.muster@example.test
                            </span>
                        </div>
                    </div>
                </HoverCard>
            </p>
        </Demo>
    );
}

function ScrollAreaDemo() {
    const rows = Array.from({ length: 30 }, (_, i) => `Mitteilung ${i + 1}`);

    return (
        <Demo
            title="Scrollbereich"
            hint="Mit Tab hineinspringen, dann Pfeiltasten."
            className="grid gap-4 md:grid-cols-2"
        >
            <ScrollArea label="Mitteilungen" className="h-48">
                <ul className="divide-y text-sm">
                    {rows.map((r) => (
                        <li key={r} className="px-3 py-2">
                            {r}
                        </li>
                    ))}
                </ul>
            </ScrollArea>
            <ScrollArea label="Klassen" orientation="horizontal">
                <div className="flex w-max gap-2 p-3">
                    {[
                        'M_A1a',
                        'M_A1b',
                        'M_B1a',
                        'M_B1b',
                        'H_A1a',
                        'H_A2a',
                        'H_B2a',
                        'M_C3a',
                        'M_C3b',
                        'H_C3a',
                    ].map((c) => (
                        <Badge
                            key={c}
                            variant="outline"
                            className="px-3 py-1"
                            translate="no"
                        >
                            {c}
                        </Badge>
                    ))}
                </div>
            </ScrollArea>
        </Demo>
    );
}

function CodeBlockDemo() {
    return (
        <>
            <Demo title="Code mit Zeilennummern" className="block">
                <CodeBlock
                    title="Geräteausgabe.ps1"
                    lineNumbers
                    code={`Connect-MgGraph -Scopes "User.ReadWrite.All"\n$users = Import-Csv .\\zugaenge.csv\nforeach ($u in $users) {\n    Update-MgUser -UserId $u.Mail -PasswordProfile @{ Password = $u.Passwort }\n}`}
                />
            </Demo>
            <Demo title="Konsolenausgabe und Vergleich" className="grid gap-3">
                <CodeBlock
                    language="Konsole"
                    code={
                        '$ php artisan intranet:upgrade\nUpgrade fertig (0 Änderungen).'
                    }
                />
                <CodeBlock
                    title="plugin.json"
                    diff
                    code={
                        '  "version": "0.1",\n- "status": "planned",\n+ "status": "partial",\n  "api": 1'
                    }
                />
            </Demo>
        </>
    );
}

function ResizableDemo() {
    return (
        <Demo
            title="Geteilte Ansicht"
            hint="Trennlinie ziehen oder fokussieren und Pfeiltasten (Shift = grössere Schritte), Doppelklick = zurück."
            className="block"
        >
            <ResizablePanels
                first={
                    <ul className="divide-y text-sm">
                        {['Muster Anna', 'Beispiel Ben', 'Probe Carla'].map(
                            (n) => (
                                <li
                                    key={n}
                                    className="px-3 py-2"
                                    translate="no"
                                >
                                    {n}
                                </li>
                            ),
                        )}
                    </ul>
                }
                second={
                    <div className="grid gap-1 p-3 text-sm">
                        <span className="font-medium" translate="no">
                            Muster Anna
                        </span>
                        <span className="text-muted-foreground">
                            Details zur gewählten Person.
                        </span>
                    </div>
                }
            />
        </Demo>
    );
}

/* Ergänzungen bestehender Bausteine */

function MenuExtra() {
    return (
        <Demo
            title="Navigationsmenü (oben)"
            hint="Für Bereiche mit wenigen Hauptpunkten, z. B. eine öffentliche Seite. Im Intranet gilt die Seitenleiste."
        >
            <NavigationMenu>
                <NavigationMenuList>
                    <NavigationMenuItem>
                        <NavigationMenuTrigger>Schule</NavigationMenuTrigger>
                        <NavigationMenuContent>
                            <ul className="grid w-64 gap-1 p-2 text-sm">
                                {[
                                    'Schulhaus Hinterbirch',
                                    'Schulhaus Mettmenriet',
                                    'Schulverwaltung',
                                ].map((s) => (
                                    <li key={s}>
                                        <NavigationMenuLink href="#schule">
                                            {s}
                                        </NavigationMenuLink>
                                    </li>
                                ))}
                            </ul>
                        </NavigationMenuContent>
                    </NavigationMenuItem>
                    <NavigationMenuItem>
                        <NavigationMenuLink href="#termine">
                            Termine
                        </NavigationMenuLink>
                    </NavigationMenuItem>
                </NavigationMenuList>
            </NavigationMenu>
        </Demo>
    );
}

function SheetExtra() {
    return (
        <Demo
            title="Von unten (Drawer)"
            hint="Auf dem Handy für Aktionen und kurze Formulare."
        >
            <Sheet>
                <SheetTrigger asChild>
                    <Button variant="outline">Aktionen</Button>
                </SheetTrigger>
                <SheetContent side="bottom">
                    <SheetHeader>
                        <SheetTitle>Aktionen</SheetTitle>
                        <SheetDescription>
                            Reglement Geräteausgabe.pdf
                        </SheetDescription>
                    </SheetHeader>
                    <div className="grid gap-2 px-4 pb-4">
                        <Button variant="outline">
                            <Download /> Herunterladen
                        </Button>
                        <Button variant="outline">
                            <Pencil /> Umbenennen
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>
        </Demo>
    );
}

function DropdownExtra() {
    return (
        <Demo
            title="Einfaches Auswahlfeld (Browser)"
            hint="Für kurze Listen und Tabellenzellen; auf dem Handy öffnet sich die Auswahl des Geräts."
            className="grid max-w-sm"
        >
            <FormField id="gui-native" label="Schulhaus">
                <NativeSelect id="gui-native" defaultValue="hinterbirch">
                    <option value="hinterbirch">Schulhaus Hinterbirch</option>
                    <option value="mettmenriet">Schulhaus Mettmenriet</option>
                    <option value="verwaltung">Schulverwaltung</option>
                </NativeSelect>
            </FormField>
        </Demo>
    );
}

function FormExtra() {
    const [notify, setNotify] = useState(true);

    return (
        <Demo
            title="Feldgruppen"
            hint="Zusammengehörende Felder mit Überschrift; Einstellungen als Zeile."
            className="block"
        >
            <div className="grid gap-4">
                <FieldSet
                    legend="Kontakt Schule"
                    description="Wird im Telefonbuch angezeigt."
                >
                    <FieldGroup>
                        <FormField id="gui-fs-mail" label="E-Mail">
                            <Input
                                id="gui-fs-mail"
                                defaultValue="anna.muster@example.test"
                            />
                        </FormField>
                        <FormField id="gui-fs-tel" label="Telefon">
                            <Input
                                id="gui-fs-tel"
                                defaultValue="044 000 00 00"
                            />
                        </FormField>
                    </FieldGroup>
                </FieldSet>
                <FieldSet legend="Benachrichtigungen">
                    <FieldRow
                        htmlFor="gui-fs-notify"
                        label="E-Mail bei neuen Mitteilungen"
                        description="Höchstens eine pro Tag."
                    >
                        <Switch
                            id="gui-fs-notify"
                            checked={notify}
                            onCheckedChange={setNotify}
                        />
                    </FieldRow>
                </FieldSet>
            </div>
        </Demo>
    );
}

type Upload = {
    id: string;
    name: string;
    size: number;
    type: string;
    preview?: string;
    state: AttachmentState;
    progress: number;
};

function UploadExtra() {
    const [files, setFiles] = useState<File[]>([]);
    const [uploads, setUploads] = useState<Upload[]>([]);

    useEffect(() => {
        if (!uploads.some((u) => u.state === 'uploading')) {
            return;
        }
        const t = window.setInterval(() => {
            setUploads((list) =>
                list.map((u) =>
                    u.state !== 'uploading'
                        ? u
                        : u.progress >= 100
                          ? {
                                ...u,
                                state: u.name.includes('fehler')
                                    ? 'error'
                                    : 'done',
                            }
                          : { ...u, progress: u.progress + 20 },
                ),
            );
        }, 300);

        return () => window.clearInterval(t);
    }, [uploads]);

    useEffect(
        () => () =>
            uploads.forEach((u) => u.preview && URL.revokeObjectURL(u.preview)),
        [uploads],
    );

    return (
        <Demo
            title="Anhänge mit Vorschau und Fortschritt"
            hint="Bilder zeigen ein Vorschaubild. Hochladen ist hier nur simuliert."
            className="grid gap-3"
        >
            <FileDrop
                accept="image/png,image/jpeg,.pdf"
                multiple
                files={files}
                onFiles={(next) => {
                    const added = next.slice(files.length);
                    setFiles([]);
                    setUploads((u) => [
                        ...u,
                        ...added.map((f, i) => ({
                            id: `${Date.now()}-${i}`,
                            name: f.name,
                            size: f.size,
                            type: f.type,
                            preview: f.type.startsWith('image/')
                                ? URL.createObjectURL(f)
                                : undefined,
                            state: 'uploading' as const,
                            progress: 0,
                        })),
                    ]);
                }}
                hint="PNG, JPG oder PDF"
            />
            {[
                ...uploads,
                {
                    id: 'b1',
                    name: 'Klassenfoto_M_A1a.jpg',
                    size: 2_400_000,
                    type: 'image/jpeg',
                    state: 'done' as const,
                    progress: 100,
                },
                {
                    id: 'b2',
                    name: 'Elternbrief_fehler.pdf',
                    size: 180_000,
                    type: 'application/pdf',
                    state: 'error' as const,
                    progress: 0,
                },
            ].map((u) => (
                <Attachment
                    key={u.id}
                    name={u.name}
                    size={u.size}
                    type={u.type}
                    preview={'preview' in u ? u.preview : undefined}
                    state={u.state}
                    progress={u.progress}
                    onRemove={() =>
                        setUploads((list) => list.filter((x) => x.id !== u.id))
                    }
                    onRetry={() =>
                        toast.success('Beispiel: nochmals hochladen')
                    }
                />
            ))}
        </Demo>
    );
}

function ChartExtra() {
    return (
        <>
            <Demo title="Verlauf (Linien)" className="block">
                <LineChart
                    title="Tickets pro Monat (erfunden)"
                    labels={['Aug', 'Sep', 'Okt', 'Nov']}
                    series={[
                        { label: 'Eröffnet', values: [12, 30, 22, 18] },
                        { label: 'Erledigt', values: [10, 24, 25, 20] },
                    ]}
                />
            </Demo>
            <Demo title="Anteile (Ring)" className="block">
                <DonutChart
                    title="iPads nach Zustand (erfunden)"
                    unit="Stk."
                    data={[
                        { label: 'Ausgegeben', value: 640 },
                        { label: 'Im Lager', value: 120 },
                        { label: 'In Reparatur', value: 24 },
                        { label: 'Ausgemustert', value: 16 },
                    ]}
                />
            </Demo>
        </>
    );
}

export const MORE_DEMOS: Record<string, () => ReactNode> = {
    typografie: TypographyDemo,
    eingabegruppe: InputGroupDemo,
    knopfgruppe: ButtonGroupDemo,
    eintrag: ItemDemo,
    fragebogen: QuestionnaireDemo,
    rueckfrage: AlertDialogDemo,
    kontextmenue: ContextMenuDemo,
    vorschaukarte: HoverCardDemo,
    scrollbereich: ScrollAreaDemo,
    codeblock: CodeBlockDemo,
    geteilt: ResizableDemo,
};

/** Zusätzliche Beispiele, die an bestehende Bausteine angehängt werden. */
export const DEMO_EXTENSIONS: Record<string, () => ReactNode> = {
    menue: MenuExtra,
    seitenpanel: SheetExtra,
    dropdown: DropdownExtra,
    formular: FormExtra,
    upload: UploadExtra,
    diagramm: ChartExtra,
};
