import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Copy,
    Eye,
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    SquarePen,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import DataGrid from '@/components/data-grid';
import type { CellChange, GridColumn, SavedView } from '@/components/data-grid';
import type { SaveSource } from '@/components/grid/types';
import DeleteButton from '@/components/delete-button';
import EmptyState from '@/components/empty-state';
import OptionBadge from '@/components/option-badge';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useOptions } from '@/hooks/use-options';
import { postJson } from '@/lib/http';
import {
    DEFAULT_VISIBLE,
    gridType,
    STUDENT_VISIBLE,
} from '@/lib/person-fields';
import type { Person, PersonGroup } from '@/types';

type BulkChange = {
    field: 'school' | 'role' | 'active' | 'delete';
    value: string | boolean;
    label: string;
    people: Person[];
    clear: () => void;
};

type CellsResponse = { people?: Person[]; status?: string; message?: string };

/** Gefilterte Ansicht derselben Tabelle (z. B. «Schüler:innen»). */
type Scope = {
    type: string;
    title: string;
    description: string;
    href: string;
} | null;

async function saveCells(
    changes: CellChange<Person>[],
    source: SaveSource,
): Promise<Person[]> {
    const res = await postJson<CellsResponse>('/personen/zellen', {
        source,
        changes: changes.map((c) => ({
            id: c.id,
            field: c.field,
            value: c.value,
        })),
    });
    if (!res.people) {
        throw new Error(res.message ?? 'Speichern fehlgeschlagen.');
    }

    return res.people;
}

/** Export als Datei herunterladen (Server prüft Felder und Berechtigung). */
async function exportPeople(
    format: 'csv' | 'xlsx',
    ids: string[],
    columns: string[],
) {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );
    const response = await fetch('/personen/export', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify({ format, ids, columns }),
    });
    if (!response.ok) {
        toast.error(`Export nicht möglich (HTTP ${response.status}).`);

        return;
    }
    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `personen-${new Date().toISOString().slice(0, 10)}.${format}`;
    link.click();
    URL.revokeObjectURL(url);
    toast.success(`${ids.length} Personen exportiert.`);
}

export default function PeopleIndex({
    people,
    groups,
    views,
    scope = null,
}: {
    people: Person[];
    groups: PersonGroup[];
    views: SavedView[];
    scope?: Scope;
}) {
    const { auth } = usePage().props;
    const options = useOptions();
    const canManage = auth.can.manageUsers;
    const [bulk, setBulk] = useState<BulkChange | null>(null);
    const [busy, setBusy] = useState(false);
    const [mobileSearch, setMobileSearch] = useState('');

    const visible = scope ? STUDENT_VISIBLE : DEFAULT_VISIBLE;
    const gridKey = scope ? 'students' : 'people';

    // Alle Felder aus dem Schema als Spalten; nicht standardmässige ausgeblendet
    const schemaColumns: GridColumn<Person>[] = groups
        .flatMap((group) => group.fields)
        .map((field): GridColumn<Person> => {
            const column: GridColumn<Person> = {
                id: field.key,
                title: field.label,
                type: gridType(field),
                options: field.options ?? undefined,
                editable: !field.computed,
                required: field.required,
                hidden: !visible.includes(field.key),
            };

            switch (field.key) {
                case 'last_name':
                    return {
                        ...column,
                        render: (p) => (
                            <span className="flex items-center gap-2">
                                <span className="truncate font-medium">
                                    {p.last_name ?? p.name}
                                </span>
                                {!p.active && (
                                    <Badge variant="outline">inaktiv</Badge>
                                )}
                            </span>
                        ),
                    };
                case 'school':
                    return {
                        ...column,
                        render: (p) => (
                            <OptionBadge list="schools" value={p.school} dot />
                        ),
                    };
                case 'role':
                    return {
                        ...column,
                        render: (p) => (
                            <OptionBadge list="roles" value={p.role} />
                        ),
                    };
                case 'person_type':
                    return {
                        ...column,
                        render: (p) => (
                            <OptionBadge
                                list="personTypes"
                                value={p.person_type}
                            />
                        ),
                    };
                case 'email':
                    return canManage
                        ? column
                        : {
                              ...column,
                              render: (p) => (
                                  <a
                                      className="hover:underline"
                                      href={`mailto:${p.email}`}
                                  >
                                      {p.email}
                                  </a>
                              ),
                          };
                default:
                    return column;
            }
        });

    // Abgleich mit Zielsystemen (Datenherkunft) – nur für die Administration
    const columns: GridColumn<Person>[] = canManage
        ? [
              ...schemaColumns,
              {
                  id: 'sync_pending',
                  title: 'Abgleich offen',
                  type: 'text',
                  editable: false,
                  required: false,
                  hidden: true,
                  render: (p) =>
                      p.sync_pending ? (
                          <Badge variant="outline">
                              {`${p.sync_pending} offen`}
                          </Badge>
                      ) : null,
              },
          ]
        : schemaColumns;

    const applyBulk = async () => {
        if (!bulk) {
            return;
        }
        setBusy(true);
        const ids = bulk.people.map((p) => p.id);
        const res = await postJson<{ message?: string; status?: string }>(
            bulk.field === 'delete'
                ? '/personen/sammelloeschung'
                : '/personen/sammelaenderung',
            bulk.field === 'delete'
                ? { ids }
                : { ids, field: bulk.field, value: bulk.value },
        );
        setBusy(false);
        if (res.status === 'error') {
            toast.error(res.message ?? 'Änderung fehlgeschlagen.');

            return;
        }
        toast.success(res.message ?? 'Geändert.');
        bulk.clear();
        setBulk(null);
        router.reload({ only: ['people'] });
    };

    const emails = (selected: Person[]) => selected.map((p) => p.email);

    const bulkActions = (selected: Person[], clear: () => void) => (
        <>
            <Button variant="outline" size="sm" asChild>
                <a href={`mailto:?bcc=${emails(selected).join(',')}`}>
                    <Mail /> E-Mail an Auswahl
                </a>
            </Button>
            <Button
                variant="outline"
                size="sm"
                onClick={() =>
                    void navigator.clipboard
                        .writeText(emails(selected).join('; '))
                        .then(() =>
                            toast.success(
                                `${selected.length} Adressen kopiert.`,
                            ),
                        )
                        .catch(() => toast.error('Kopieren nicht möglich.'))
                }
            >
                <Copy /> Adressen kopieren
            </Button>
            {canManage && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button size="sm">
                            <SquarePen /> Ändern{' '}
                            <ChevronDown className="opacity-60" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                Schulhaus setzen
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                {Object.entries(options.schools).map(
                                    ([key, name]) => (
                                        <DropdownMenuItem
                                            key={key}
                                            onSelect={() =>
                                                setBulk({
                                                    field: 'school',
                                                    value: key,
                                                    label: `Schulhaus «${name}»`,
                                                    people: selected,
                                                    clear,
                                                })
                                            }
                                        >
                                            {name}
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSub>
                            <DropdownMenuSubTrigger>
                                Rolle setzen
                            </DropdownMenuSubTrigger>
                            <DropdownMenuSubContent>
                                {Object.entries(options.roles).map(
                                    ([key, name]) => (
                                        <DropdownMenuItem
                                            key={key}
                                            onSelect={() =>
                                                setBulk({
                                                    field: 'role',
                                                    value: key,
                                                    label: `Rolle «${name}»`,
                                                    people: selected,
                                                    clear,
                                                })
                                            }
                                        >
                                            {name}
                                        </DropdownMenuItem>
                                    ),
                                )}
                            </DropdownMenuSubContent>
                        </DropdownMenuSub>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() =>
                                setBulk({
                                    field: 'active',
                                    value: true,
                                    label: 'aktiv',
                                    people: selected,
                                    clear,
                                })
                            }
                        >
                            Aktiv setzen
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onSelect={() =>
                                setBulk({
                                    field: 'active',
                                    value: false,
                                    label: 'inaktiv',
                                    people: selected,
                                    clear,
                                })
                            }
                        >
                            Inaktiv setzen
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}
            {canManage && (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() =>
                        setBulk({
                            field: 'delete',
                            value: true,
                            label: '',
                            people: selected.filter(
                                (p) => p.id !== auth.user.id,
                            ),
                            clear,
                        })
                    }
                >
                    <Trash2 /> Löschen
                </Button>
            )}
        </>
    );

    const mobileRows = people.filter((p) =>
        `${p.name} ${p.function ?? ''} ${p.email}`
            .toLowerCase()
            .includes(mobileSearch.toLowerCase().trim()),
    );

    return (
        <>
            <Head title={scope?.title ?? 'Personen'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={scope?.title ?? 'Personen'}
                    description={
                        scope?.description ??
                        'Verzeichnis der Mitarbeitenden, Schüler:innen und Kontakte'
                    }
                    actions={
                        canManage && (
                            <Button asChild>
                                <Link
                                    href={
                                        scope
                                            ? `/personen/neu?art=${scope.type}`
                                            : '/personen/neu'
                                    }
                                >
                                    <Plus />{' '}
                                    {scope
                                        ? 'Neue:r Schüler:in'
                                        : 'Neue Person'}
                                </Link>
                            </Button>
                        )
                    }
                />

                {people.length === 0 && (
                    <EmptyState
                        icon={Users}
                        title="Noch keine Personen erfasst"
                    />
                )}

                {/* Desktop: bearbeitbare Tabelle */}
                {people.length > 0 && (
                    <div className="hidden md:block">
                        <DataGrid
                            data={people}
                            columns={columns}
                            storageKey={`intranet.grid.${gridKey}.${canManage ? 'admin' : 'all'}`}
                            canEdit={canManage}
                            onSaveCells={saveCells}
                            gridId={gridKey}
                            views={views}
                            canShareViews={canManage}
                            onExport={(format, ids, cols) =>
                                void exportPeople(format, ids, cols)
                            }
                            bulkActions={bulkActions}
                            searchPlaceholder="Name, Funktion, E-Mail …"
                            emptyText="Keine Personen gefunden."
                            rowClassName={(p) => (p.active ? '' : 'opacity-60')}
                            rowActions={(p) => (
                                <div className="flex justify-end gap-0.5">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        asChild
                                    >
                                        <Link
                                            href={`/personen/${p.id}`}
                                            aria-label="Details anzeigen"
                                            title="Details"
                                        >
                                            <Eye />
                                        </Link>
                                    </Button>
                                    {canManage && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            asChild
                                        >
                                            <Link
                                                href={`/personen/${p.id}/bearbeiten`}
                                                aria-label="Im Formular bearbeiten"
                                                title="Bearbeiten"
                                            >
                                                <Pencil />
                                            </Link>
                                        </Button>
                                    )}
                                    {canManage && p.id !== auth.user.id && (
                                        <DeleteButton
                                            url={`/personen/${p.id}`}
                                            title={`${p.name} löschen?`}
                                            size="icon"
                                        />
                                    )}
                                </div>
                            )}
                        />
                    </div>
                )}

                {/* Mobile: Karten */}
                <div className="grid gap-3 md:hidden">
                    <div className="relative">
                        <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                        <Input
                            value={mobileSearch}
                            onChange={(e) => setMobileSearch(e.target.value)}
                            placeholder="Name, Funktion, E-Mail …"
                            className="pl-8"
                            aria-label="Personen durchsuchen"
                        />
                    </div>
                    {mobileRows.map((person) => (
                        <div
                            key={person.id}
                            className="grid gap-1 rounded-xl border bg-card p-4"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <Link
                                        href={`/personen/${person.id}`}
                                        className="font-medium hover:underline"
                                    >
                                        {person.name}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        {person.function}
                                    </p>
                                    <OptionBadge
                                        list="schools"
                                        value={person.school}
                                        dot
                                        className="mt-1"
                                    />
                                </div>
                                {canManage && (
                                    <Button variant="ghost" size="icon" asChild>
                                        <Link
                                            href={`/personen/${person.id}/bearbeiten`}
                                            aria-label="Bearbeiten"
                                        >
                                            <Pencil />
                                        </Link>
                                    </Button>
                                )}
                            </div>
                            <a
                                href={`mailto:${person.email}`}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Mail className="size-4 text-muted-foreground" />{' '}
                                {person.email}
                            </a>
                            {person.phone && (
                                <a
                                    href={`tel:${person.phone.replace(/\s/g, '')}`}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Phone className="size-4 text-muted-foreground" />{' '}
                                    {person.phone}
                                </a>
                            )}
                        </div>
                    ))}
                </div>

                <Dialog
                    open={bulk !== null}
                    onOpenChange={(open: boolean) => !open && setBulk(null)}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {bulk?.people.length} Personen{' '}
                                {bulk?.field === 'delete'
                                    ? 'löschen'
                                    : 'ändern'}
                                ?
                            </DialogTitle>
                            <DialogDescription>
                                {bulk?.field === 'delete'
                                    ? 'Die ausgewählten Personen werden endgültig aus dem Intranet gelöscht. Das eigene Konto ist ausgenommen.'
                                    : `Für alle ausgewählten Personen wird ${bulk?.label ?? ''} gesetzt. Das eigene Konto behält Rolle und Status.`}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Abbrechen</Button>
                            </DialogClose>
                            <Button
                                disabled={busy}
                                variant={
                                    bulk?.field === 'delete'
                                        ? 'destructive'
                                        : 'default'
                                }
                                onClick={() => void applyBulk()}
                            >
                                {busy
                                    ? 'Speichert …'
                                    : bulk?.field === 'delete'
                                      ? 'Ja, löschen'
                                      : 'Ja, ändern'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

PeopleIndex.layout = (props: { scope?: Scope }) => ({
    breadcrumbs: props.scope
        ? [
              { title: 'Personen', href: '/personen' },
              { title: props.scope.title, href: props.scope.href },
          ]
        : [{ title: 'Personen', href: '/personen' }],
});
