import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Copy,
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    SquarePen,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import DataGrid from '@/components/data-grid';
import type { GridColumn } from '@/components/data-grid';
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
import type { Person } from '@/types';

type BulkChange = {
    field: 'school' | 'role' | 'active';
    value: string | boolean;
    label: string;
    people: Person[];
    clear: () => void;
};

type FieldResponse = { person?: Person; status?: string; message?: string };

async function saveField(
    person: Person,
    field: string,
    value: unknown,
): Promise<Person> {
    const res = await postJson<FieldResponse>(`/personen/${person.id}/feld`, {
        field,
        value,
    });
    if (!res.person) {
        throw new Error(res.message ?? 'Speichern fehlgeschlagen.');
    }

    return res.person;
}

export default function PeopleIndex({ people }: { people: Person[] }) {
    const { auth } = usePage().props;
    const options = useOptions();
    const canManage = auth.can.manageUsers;
    const [bulk, setBulk] = useState<BulkChange | null>(null);
    const [busy, setBusy] = useState(false);
    const [mobileSearch, setMobileSearch] = useState('');

    const columns: GridColumn<Person>[] = [
        {
            id: 'name',
            title: 'Name',
            type: 'text',
            editable: true,
            render: (p) => (
                <span className="flex items-center gap-2">
                    <span className="truncate font-medium">{p.name}</span>
                    {!p.active && <Badge variant="outline">inaktiv</Badge>}
                </span>
            ),
        },
        { id: 'function', title: 'Funktion', type: 'text', editable: true },
        {
            id: 'school',
            title: 'Schulhaus',
            type: 'select',
            options: options.schools,
            editable: true,
            render: (p) => <OptionBadge list="schools" value={p.school} dot />,
        },
        {
            id: 'email',
            title: 'E-Mail',
            type: 'email',
            editable: true,
            render: canManage
                ? undefined
                : (p) => (
                      <a className="hover:underline" href={`mailto:${p.email}`}>
                          {p.email}
                      </a>
                  ),
        },
        { id: 'phone', title: 'Telefon', type: 'tel', editable: true },
        ...(canManage
            ? ([
                  {
                      id: 'role',
                      title: 'Rolle',
                      type: 'select',
                      options: options.roles,
                      editable: true,
                      render: (p) => (
                          <OptionBadge list="roles" value={p.role} />
                      ),
                  },
                  {
                      id: 'active',
                      title: 'Aktiv',
                      type: 'bool',
                      editable: true,
                  },
              ] satisfies GridColumn<Person>[])
            : []),
    ];

    const applyBulk = async () => {
        if (!bulk) {
            return;
        }
        setBusy(true);
        const res = await postJson<{ message?: string; status?: string }>(
            '/personen/sammelaenderung',
            {
                ids: bulk.people.map((p) => p.id),
                field: bulk.field,
                value: bulk.value,
            },
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
        </>
    );

    const mobileRows = people.filter((p) =>
        `${p.name} ${p.function ?? ''} ${p.email}`
            .toLowerCase()
            .includes(mobileSearch.toLowerCase().trim()),
    );

    return (
        <>
            <Head title="Personen" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Personen"
                    description="Verzeichnis der Mitarbeitenden"
                    actions={
                        canManage && (
                            <Button asChild>
                                <Link href="/personen/neu">
                                    <Plus /> Neue Person
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
                            storageKey={`intranet.grid.people.${canManage ? 'admin' : 'all'}`}
                            canEdit={canManage}
                            onSave={saveField}
                            bulkActions={bulkActions}
                            searchPlaceholder="Name, Funktion, E-Mail …"
                            emptyText="Keine Personen gefunden."
                            rowClassName={(p) => (p.active ? '' : 'opacity-60')}
                            rowActions={
                                canManage
                                    ? (p) => (
                                          <div className="flex justify-end gap-1">
                                              <Button
                                                  variant="ghost"
                                                  size="icon"
                                                  asChild
                                              >
                                                  <Link
                                                      href={`/personen/${p.id}/bearbeiten`}
                                                      aria-label="Im Formular bearbeiten"
                                                  >
                                                      <Pencil />
                                                  </Link>
                                              </Button>
                                              {p.id !== auth.user.id && (
                                                  <DeleteButton
                                                      url={`/personen/${p.id}`}
                                                      title={`${p.name} löschen?`}
                                                      size="icon"
                                                  />
                                              )}
                                          </div>
                                      )
                                    : undefined
                            }
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
                                    <p className="font-medium">{person.name}</p>
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
                                {bulk?.people.length} Personen ändern?
                            </DialogTitle>
                            <DialogDescription>
                                Für alle ausgewählten Personen wird{' '}
                                {bulk?.label} gesetzt. Das eigene Konto behält
                                Rolle und Status.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Abbrechen</Button>
                            </DialogClose>
                            <Button
                                disabled={busy}
                                onClick={() => void applyBulk()}
                            >
                                {busy ? 'Speichert …' : 'Ja, ändern'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [{ title: 'Personen', href: '/personen' }],
};
