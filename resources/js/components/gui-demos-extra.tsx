import { Bell, Filter, HelpCircle, Mail, Settings, Users } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { toast } from 'sonner';
import FormField from '@/components/form-field';
import { Accordion } from '@/components/ui/accordion';
import { Banner } from '@/components/ui/banner';
import { BarChart } from '@/components/ui/bar-chart';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/calendar';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import type { ComboOption } from '@/components/ui/combobox';
import {
    CommandPalette,
    useCommandShortcut,
} from '@/components/ui/command-palette';
import type { Command } from '@/components/ui/command-palette';
import { DragBoard } from '@/components/ui/drag-board';
import type { BoardCard } from '@/components/ui/drag-board';
import { FileDrop } from '@/components/ui/file-drop';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import { Pagination } from '@/components/ui/pagination';
import { PopoverButton } from '@/components/ui/popover';
import { Progress } from '@/components/ui/progress';
import { RadioGroup } from '@/components/ui/radio-group';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Slider } from '@/components/ui/slider';
import { SortableList } from '@/components/ui/sortable-list';
import type { SortableItem } from '@/components/ui/sortable-list';
import { Stepper } from '@/components/ui/stepper';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Timeline } from '@/components/ui/timeline';
import { cn } from '@/lib/utils';

/** Ein Beispiel mit Titel, kurzer Erklärung und Vorschau. */
export function Demo({
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

/** Erfundene Personen für die Beispiele. */
const PEOPLE: ComboOption[] = [
    'Muster Anna',
    'Beispiel Ben',
    'Probe Carla',
    'Fiktiv Dario',
    'Vorlage Elena',
    'Platzhalter Finn',
    'Exempel Gina',
    'Testfall Hugo',
    'Attrappe Ines',
    'Modell Jonas',
    'Entwurf Kim',
    'Skizze Lea',
].map((name, i) => ({
    value: `p${i + 1}`,
    label: name,
    hint:
        i % 3 === 0
            ? 'Lehrperson'
            : i % 3 === 1
              ? 'Schüler:in M_A1a'
              : 'Schüler:in H_A2a',
}));

const CLASSES: ComboOption[] = [
    'M_A1a',
    'M_B1b',
    'H_A1a',
    'H_A2a',
    'M_C3a',
].map((c) => ({ value: c.toLowerCase(), label: c }));

function SheetDemo() {
    return (
        <>
            <Demo
                title="Details von rechts"
                hint="Für Details oder Bearbeiten, ohne die Liste zu verlassen."
            >
                <Sheet>
                    <SheetTrigger asChild>
                        <Button variant="outline">Details öffnen</Button>
                    </SheetTrigger>
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>Muster Anna</SheetTitle>
                            <SheetDescription>
                                Lehrperson · Schulhaus Hinterbirch
                            </SheetDescription>
                        </SheetHeader>
                        <div className="grid gap-3 px-4 text-sm">
                            <FormField id="gui-sheet-mail" label="E-Mail">
                                <Input
                                    id="gui-sheet-mail"
                                    defaultValue="anna.muster@example.test"
                                />
                            </FormField>
                        </div>
                        <SheetFooter>
                            <SheetClose asChild>
                                <Button>Speichern</Button>
                            </SheetClose>
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
            </Demo>
            <Demo
                title="Von links"
                hint="Auf dem Handy für das Menü; sonst für Filter."
            >
                <Sheet>
                    <SheetTrigger asChild>
                        <Button variant="outline">
                            <Filter /> Filter
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left">
                        <SheetHeader>
                            <SheetTitle>Filter</SheetTitle>
                            <SheetDescription>
                                Nur aktive Personen anzeigen.
                            </SheetDescription>
                        </SheetHeader>
                    </SheetContent>
                </Sheet>
            </Demo>
        </>
    );
}

function TabsDemo() {
    const [tab, setTab] = useState('uebersicht');

    return (
        <Demo
            title="Reiter"
            hint="Immer ein Reiter sichtbar. Tastatur: Pfeiltasten, Anfang und Ende."
            className="block"
        >
            <Tabs value={tab} onValueChange={setTab}>
                <TabsList label="Bereiche der Person">
                    <TabsTrigger value="uebersicht">
                        <Users /> Übersicht
                    </TabsTrigger>
                    <TabsTrigger value="kontakt">
                        <Mail /> Kontakt
                    </TabsTrigger>
                    <TabsTrigger value="einstellungen">
                        <Settings /> Einstellungen
                    </TabsTrigger>
                    <TabsTrigger value="gesperrt" disabled>
                        Gesperrt
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="uebersicht" className="text-sm">
                    Inhalt «Übersicht»
                </TabsContent>
                <TabsContent value="kontakt" className="text-sm">
                    Inhalt «Kontakt»
                </TabsContent>
                <TabsContent value="einstellungen" className="text-sm">
                    Inhalt «Einstellungen»
                </TabsContent>
            </Tabs>
        </Demo>
    );
}

function PaginationDemo() {
    const [page, setPage] = useState(4);

    return (
        <Demo
            title="Seitenweise Anzeige"
            hint="20 Seiten; Anfang, Ende und Nachbarseiten sind immer sichtbar."
            className="grid"
        >
            <p className="text-sm tabular-nums">{`Seite ${page} von 20`}</p>
            <Pagination page={page} pages={20} onChange={setPage} />
        </Demo>
    );
}

const STEPS = [
    { title: 'Klassen wählen', description: 'Jahrgang und Klassen' },
    { title: 'Zugangsdaten', description: 'Passwörter erzeugen' },
    { title: 'Prüfen', description: 'Liste kontrollieren' },
    { title: 'Abschliessen', description: 'Kärtchen drucken' },
];

function StepperDemo() {
    const [step, setStep] = useState(1);

    return (
        <Demo
            title="Ablauf Geräteausgabe"
            hint="Erledigt, aktuell und offen sind auch ohne Farbe unterscheidbar."
            className="grid gap-4"
        >
            <Stepper steps={STEPS} current={step} />
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    disabled={step === 0}
                    onClick={() => setStep((s) => s - 1)}
                >
                    Zurück
                </Button>
                <Button
                    disabled={step === STEPS.length}
                    onClick={() => setStep((s) => s + 1)}
                >
                    Weiter
                </Button>
            </div>
        </Demo>
    );
}

function CommandDemo() {
    const [open, setOpen] = useState(false);
    useCommandShortcut(setOpen);
    const go = (label: string) => () => toast.success(`Beispiel: ${label}`);
    const commands: Command[] = [
        { id: 'start', label: 'Start', group: 'Seiten', onSelect: go('Start') },
        {
            id: 'personen',
            label: 'Personen',
            group: 'Seiten',
            onSelect: go('Personen'),
        },
        {
            id: 'termine',
            label: 'Termine',
            group: 'Seiten',
            onSelect: go('Termine'),
        },
        {
            id: 'p1',
            label: 'Muster Anna',
            group: 'Personen',
            hint: 'Lehrperson',
            onSelect: go('Muster Anna'),
        },
        {
            id: 'p2',
            label: 'Beispiel Ben',
            group: 'Personen',
            hint: 'Schulleitung',
            onSelect: go('Beispiel Ben'),
        },
        {
            id: 'neu',
            label: 'Neue Mitteilung',
            group: 'Aktionen',
            onSelect: go('Neue Mitteilung'),
        },
        {
            id: 'export',
            label: 'Personen exportieren',
            group: 'Aktionen',
            onSelect: go('Export'),
        },
    ];

    return (
        <Demo
            title="Befehlspalette"
            hint="Ctrl+K (Mac: Cmd+K) oder Knopf. Erfundene Einträge."
        >
            <Button variant="outline" onClick={() => setOpen(true)}>
                <span>Suchen …</span>
                <kbd className="ml-2 rounded border bg-muted px-1.5 text-xs">
                    Ctrl K
                </kbd>
            </Button>
            <CommandPalette
                open={open}
                onOpenChange={setOpen}
                commands={commands}
            />
        </Demo>
    );
}

function RadioDemo() {
    const [audience, setAudience] = useState('alle');
    const [delivery, setDelivery] = useState('digital');

    return (
        <>
            <Demo title="Untereinander mit Hinweis" className="block">
                <RadioGroup
                    label="Zielgruppe"
                    value={audience}
                    onChange={setAudience}
                    options={[
                        {
                            value: 'alle',
                            label: 'Alle',
                            hint: 'Lehrpersonen und Mitarbeitende',
                        },
                        { value: 'lehrpersonen', label: 'Nur Lehrpersonen' },
                        { value: 'verwaltung', label: 'Nur Verwaltung' },
                        {
                            value: 'eltern',
                            label: 'Eltern',
                            hint: 'erst mit ePost',
                            disabled: true,
                        },
                    ]}
                />
            </Demo>
            <Demo title="Nebeneinander" className="block">
                <RadioGroup
                    label="Versand"
                    orientation="horizontal"
                    value={delivery}
                    onChange={setDelivery}
                    options={[
                        { value: 'digital', label: 'Digital' },
                        { value: 'email', label: 'E-Mail' },
                        { value: 'brief', label: 'Brief' },
                    ]}
                />
            </Demo>
        </>
    );
}

function SwitchDemo() {
    const [mail, setMail] = useState(true);
    const [push, setPush] = useState(false);

    return (
        <Demo
            title="Benachrichtigungen"
            hint="Schalter gelten sofort. In Formularen mit «Speichern» Kontrollkästchen verwenden."
            className="grid max-w-md"
        >
            {[
                {
                    id: 'gui-sw-mail',
                    label: 'E-Mail bei neuen Mitteilungen',
                    hint: 'Höchstens eine pro Tag.',
                    checked: mail,
                    set: setMail,
                },
                {
                    id: 'gui-sw-push',
                    label: 'Mitteilung auf dem Gerät',
                    hint: 'Nur in der installierten App.',
                    checked: push,
                    set: setPush,
                },
            ].map((s) => (
                <div
                    key={s.id}
                    className="flex items-start justify-between gap-4"
                >
                    <div className="grid gap-0.5">
                        <Label htmlFor={s.id}>{s.label}</Label>
                        <span
                            id={`${s.id}-hint`}
                            className="text-xs text-muted-foreground"
                        >
                            {s.hint}
                        </span>
                    </div>
                    <Switch
                        id={s.id}
                        checked={s.checked}
                        aria-describedby={`${s.id}-hint`}
                        onCheckedChange={(v) => {
                            s.set(v);
                            toast.success(
                                v ? 'Eingeschaltet.' : 'Ausgeschaltet.',
                            );
                        }}
                    />
                </div>
            ))}
            <div className="flex items-center justify-between gap-4">
                <Label htmlFor="gui-sw-off">Gesperrt</Label>
                <Switch
                    id="gui-sw-off"
                    checked
                    onCheckedChange={() => undefined}
                    disabled
                />
            </div>
        </Demo>
    );
}

function ComboboxDemo() {
    const [person, setPerson] = useState('');
    const [preset, setPreset] = useState('p4');

    return (
        <Demo
            title="Person wählen"
            hint="Tippen filtert, auch ohne Akzente. Pfeiltasten, Enter, Esc."
            className="grid gap-4 md:grid-cols-2"
        >
            <FormField id="gui-cb" label="Klassenlehrperson">
                <Combobox
                    id="gui-cb"
                    value={person}
                    onChange={setPerson}
                    options={PEOPLE}
                    placeholder="Person suchen …"
                />
            </FormField>
            <FormField id="gui-cb2" label="Mit Vorauswahl">
                <Combobox
                    id="gui-cb2"
                    value={preset}
                    onChange={setPreset}
                    options={PEOPLE}
                />
            </FormField>
        </Demo>
    );
}

function MultiSelectDemo() {
    const [values, setValues] = useState(['m_a1a', 'h_a2a']);

    return (
        <Demo
            title="Klassen wählen"
            hint="Suche, Alle / Keine, gewählte Klassen einzeln entfernen."
            className="block max-w-sm"
        >
            <FormField id="gui-ms" label="Klassen">
                <MultiSelect
                    id="gui-ms"
                    values={values}
                    onChange={setValues}
                    options={CLASSES}
                />
            </FormField>
        </Demo>
    );
}

function DateDemo() {
    const [date, setDate] = useState('2026-11-12');
    const [from, setFrom] = useState('');

    return (
        <Demo
            title="Datum"
            hint="Eintippen (TT.MM.JJJJ) oder Kalender. Woche beginnt am Montag."
            className="grid gap-4 md:grid-cols-2"
        >
            <FormField id="gui-date" label="Sporttag">
                <DatePicker id="gui-date" value={date} onChange={setDate} />
            </FormField>
            <FormField
                id="gui-date2"
                label="Abwesend ab"
                hint="Nur heute bis Ende Schuljahr wählbar."
            >
                <DatePicker
                    id="gui-date2"
                    value={from}
                    onChange={setFrom}
                    min={new Date().toISOString().slice(0, 10)}
                    max="2027-07-09"
                />
            </FormField>
        </Demo>
    );
}

function UploadDemo() {
    const [list, setList] = useState<File[]>([]);
    const [docs, setDocs] = useState<File[]>([]);

    return (
        <>
            <Demo
                title="Eine Datei"
                hint="Klassenliste als Excel oder CSV."
                className="block"
            >
                <FileDrop
                    accept=".xlsx,.csv"
                    files={list}
                    onFiles={setList}
                    hint="Excel (.xlsx) oder CSV · wird nur im Browser gelesen"
                />
            </Demo>
            <Demo
                title="Mehrere Dateien"
                hint="Höchstens 10 MB je Datei."
                className="block"
            >
                <FileDrop
                    accept=".pdf"
                    multiple
                    maxBytes={10_000_000}
                    files={docs}
                    onFiles={setDocs}
                    hint="PDF, höchstens 10 MB"
                />
            </Demo>
        </>
    );
}

function SliderDemo() {
    const [count, setCount] = useState(24);
    const [share, setShare] = useState(50);

    return (
        <Demo title="Schieberegler" className="grid max-w-md gap-4">
            <FormField id="gui-slider" label="Anzahl iPads">
                <Slider
                    id="gui-slider"
                    value={count}
                    onChange={setCount}
                    min={0}
                    max={30}
                    unit="Stk."
                />
            </FormField>
            <FormField id="gui-slider2" label="Anteil Präsenz">
                <Slider
                    id="gui-slider2"
                    value={share}
                    onChange={setShare}
                    step={10}
                    unit="%"
                />
            </FormField>
        </Demo>
    );
}

const START_LINKS: SortableItem[] = [
    { id: 'l1', label: 'Lehrplan 21' },
    { id: 'l2', label: 'Escola' },
    { id: 'l3', label: 'Stundenplan' },
    { id: 'l4', label: 'Handbuch ICT' },
    { id: 'l5', label: 'Reservationen' },
];

function DragDropDemo() {
    const [links, setLinks] = useState(START_LINKS);
    const [pupils, setPupils] = useState<BoardCard[]>([
        { id: 's1', title: 'Attrappe Ines', column: 'offen' },
        { id: 's2', title: 'Modell Jonas', column: 'offen' },
        { id: 's3', title: 'Entwurf Kim', column: 'm_a1a' },
        { id: 's4', title: 'Skizze Lea', column: 'm_b1b' },
        { id: 's5', title: 'Testfall Hugo', column: 'offen' },
    ]);
    const [tickets, setTickets] = useState<BoardCard[]>([
        {
            id: 't1',
            title: 'Beamer Zimmer 104',
            column: 'offen',
            meta: 'Ticket · 28.10.',
        },
        {
            id: 't2',
            title: 'iPad 2a-07 Display',
            column: 'arbeit',
            meta: 'Reparatur · 29.10.',
        },
        {
            id: 't3',
            title: 'Drucker Lehrerzimmer',
            column: 'erledigt',
            meta: 'Ticket · 25.10.',
        },
        {
            id: 't4',
            title: 'Lizenz Lernapp',
            column: 'offen',
            meta: 'Bestellung · 30.10.',
        },
    ]);
    const move =
        (set: (f: (cards: BoardCard[]) => BoardCard[]) => void) =>
        (card: string, column: string) =>
            set((cards) =>
                cards.map((c) => (c.id === card ? { ...c, column } : c)),
            );

    return (
        <>
            <Demo
                title="Liste umsortieren"
                hint="Am Griff ziehen (Maus, iPad: kurz halten), mit Tastatur (Griff fokussieren, Leertaste, Pfeile, Leertaste) oder mit den Pfeilknöpfen."
                className="block max-w-lg"
            >
                <SortableList items={links} onChange={setLinks} />
                <p
                    className="mt-2 text-xs text-muted-foreground"
                    translate="no"
                >
                    {links.map((l) => l.label).join(' · ')}
                </p>
            </Demo>
            <Demo
                title="Zuordnen: Schüler:innen → Klassen"
                hint="Karten in eine Klasse ziehen oder unter «Nach» die Klasse wählen."
                className="block"
            >
                <DragBoard
                    columns={[
                        { id: 'offen', title: 'Ohne Klasse' },
                        { id: 'm_a1a', title: 'M_A1a' },
                        { id: 'm_b1b', title: 'M_B1b' },
                    ]}
                    cards={pupils}
                    onMove={move(setPupils)}
                />
            </Demo>
            <Demo
                title="Kanban: Tickets und Bestellungen"
                hint="Status durch Ziehen oder «Nach» ändern."
                className="block"
            >
                <DragBoard
                    columns={[
                        { id: 'offen', title: 'Offen' },
                        { id: 'arbeit', title: 'In Arbeit' },
                        { id: 'erledigt', title: 'Erledigt' },
                    ]}
                    cards={tickets}
                    onMove={move(setTickets)}
                />
            </Demo>
            <Demo
                title="Weitere Stellen"
                hint="Dateien: Baustein «Datei hochladen». Tabelle: Spalten am Griff ziehen oder im Spaltenmenü «Nach links / Nach rechts»."
                className="text-sm text-muted-foreground"
            >
                <span>
                    Regel: Alles, was man ziehen kann, geht auch ohne Ziehen.
                </span>
            </Demo>
        </>
    );
}

function AccordionDemo() {
    return (
        <>
            <Demo title="Fragen & Antworten (einer offen)" className="block">
                <Accordion
                    defaultOpen={['a1']}
                    items={[
                        {
                            id: 'a1',
                            title: 'Wie melde ich ein defektes Gerät?',
                            content:
                                'Im Menü «Tickets» ein Ticket erfassen; die Fachstelle ICT meldet sich.',
                        },
                        {
                            id: 'a2',
                            title: 'Wo finde ich den Stundenplan?',
                            content:
                                'Unter «Personen & Organisation» → «Stundenpläne».',
                        },
                        {
                            id: 'a3',
                            title: 'Wer darf Mitteilungen schreiben?',
                            content:
                                'Schulleitung, Redaktion und Administration.',
                        },
                    ]}
                />
            </Demo>
            <Demo title="Mehrere offen" className="block">
                <Accordion
                    multiple
                    items={[
                        {
                            id: 'b1',
                            title: 'Kontakt',
                            content: 'Schul-E-Mail und Telefon.',
                        },
                        {
                            id: 'b2',
                            title: 'Organisation',
                            content: 'Schulhaus, Klasse, Funktion.',
                        },
                    ]}
                />
            </Demo>
        </>
    );
}

function ProgressDemo() {
    const [value, setValue] = useState(35);

    return (
        <Demo title="Fortschritt" className="grid max-w-md gap-4">
            <Progress label="Import Klassenliste" value={value} />
            <div className="flex gap-2">
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setValue((v) => Math.max(0, v - 10))}
                >
                    −10 %
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setValue((v) => Math.min(100, v + 10))}
                >
                    +10 %
                </Button>
            </div>
            <Progress label="Versand wird vorbereitet" value={null} />
            <Progress
                label="Lizenzen zugewiesen: 412 von 500"
                value={412}
                max={500}
            />
        </Demo>
    );
}

function ChartDemo() {
    return (
        <>
            <Demo title="Einfache Balken" className="block">
                <BarChart
                    title="iPads je Schulhaus"
                    unit="Stk."
                    valueLabel="Geräte"
                    data={[
                        { label: 'Hinterbirch', value: 412 },
                        { label: 'Mettmenriet', value: 368 },
                        { label: 'Verwaltung', value: 24 },
                    ]}
                />
            </Demo>
            <Demo title="Zwei Werte je Zeile" className="block">
                <BarChart
                    title="Lizenzen je Lehrmittel (erfunden)"
                    valueLabel="Zugewiesen"
                    secondaryLabel="Gekauft"
                    data={[
                        { label: 'Mathematik 1', value: 180, secondary: 200 },
                        { label: 'Deutsch 2', value: 95, secondary: 150 },
                        { label: 'Englisch 1', value: 140, secondary: 140 },
                    ]}
                />
            </Demo>
        </>
    );
}

function TimelineDemo() {
    return (
        <Demo title="Verlauf einer Bestellung" className="block">
            <Timeline
                entries={[
                    {
                        id: 'e4',
                        at: '31.10.2026, 09:12',
                        title: 'Geliefert',
                        description: 'An Fachstelle ICT',
                        tone: 'success',
                    },
                    {
                        id: 'e3',
                        at: '28.10.2026, 14:40',
                        title: 'Bestellt',
                        description: 'Beim Lieferanten ausgelöst',
                        tone: 'info',
                    },
                    {
                        id: 'e2',
                        at: '27.10.2026, 08:05',
                        title: 'Rückfrage',
                        description: 'Menge geklärt',
                        tone: 'warning',
                    },
                    {
                        id: 'e1',
                        at: '26.10.2026, 16:30',
                        title: 'Erfasst',
                        description: 'Durch Muster Anna',
                    },
                ]}
            />
        </Demo>
    );
}

function PopoverDemo() {
    const [onlyActive, setOnlyActive] = useState(true);

    return (
        <Demo
            title="Popover"
            hint="Schliesst mit Esc oder Klick daneben; der Fokus geht zurück zum Knopf."
        >
            <PopoverButton
                label={
                    <>
                        <HelpCircle />
                        <span className="sr-only">Hilfe zur Geräte-Nr.</span>
                    </>
                }
                variant="ghost"
                size="icon"
                title="Hilfe"
            >
                <p className="font-medium">Geräte-Nr.</p>
                <p className="text-muted-foreground">
                    Steht auf der Rückseite des iPads, z. B. «2a-07».
                </p>
            </PopoverButton>
            <PopoverButton
                label={
                    <>
                        <Filter /> Filter
                    </>
                }
            >
                {(close) => (
                    <>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="gui-pop-active"
                                checked={onlyActive}
                                onCheckedChange={(
                                    c: boolean | 'indeterminate',
                                ) => setOnlyActive(c === true)}
                            />
                            <Label htmlFor="gui-pop-active">Nur aktive</Label>
                        </div>
                        <Button
                            size="sm"
                            onClick={() => {
                                close();
                                toast.success('Filter übernommen.');
                            }}
                        >
                            Übernehmen
                        </Button>
                    </>
                )}
            </PopoverButton>
        </Demo>
    );
}

function BannerDemo() {
    const [visible, setVisible] = useState(true);

    return (
        <Demo
            title="Banner"
            hint="Über die ganze Breite oben auf der Seite."
            className="grid p-0"
        >
            <Banner tone="warning">
                Wartung heute ab 18 Uhr – das Intranet ist kurz nicht
                erreichbar.
            </Banner>
            <Banner
                tone="info"
                action={
                    <Button size="sm" variant="outline">
                        Neuerungen
                    </Button>
                }
            >
                Neue Version 0.28.0 ist da.
            </Banner>
            {visible ? (
                <Banner tone="success" onClose={() => setVisible(false)}>
                    Alle Geräte sind zurückgegeben.
                </Banner>
            ) : (
                <div className="p-3">
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => setVisible(true)}
                    >
                        <Bell /> Banner wieder zeigen
                    </Button>
                </div>
            )}
        </Demo>
    );
}

export const EXTRA_DEMOS: Record<string, () => ReactNode> = {
    seitenpanel: SheetDemo,
    tabs: TabsDemo,
    pagination: PaginationDemo,
    stepper: StepperDemo,
    befehlspalette: CommandDemo,
    radio: RadioDemo,
    switch: SwitchDemo,
    combobox: ComboboxDemo,
    mehrfachauswahl: MultiSelectDemo,
    datum: DateDemo,
    upload: UploadDemo,
    slider: SliderDemo,
    dragdrop: DragDropDemo,
    akkordeon: AccordionDemo,
    fortschritt: ProgressDemo,
    diagramm: ChartDemo,
    zeitleiste: TimelineDemo,
    popover: PopoverDemo,
    banner: BannerDemo,
};
