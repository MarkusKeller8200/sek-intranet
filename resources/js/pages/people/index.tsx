import { Head, Link, usePage } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import type {
    ColumnDef,
    HeaderContext,
    SortingState,
} from '@tanstack/react-table';
import {
    ArrowUpDown,
    Mail,
    Pencil,
    Phone,
    Plus,
    Search,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DeleteButton from '@/components/delete-button';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { label, useOptions } from '@/hooks/use-options';
import type { Person } from '@/types';

export default function PeopleIndex({ people }: { people: Person[] }) {
    // TanStack Table arbeitet mit veränderlichen Objekten: React Compiler hier aus.
    'use no memo';

    const { auth } = usePage().props;
    const options = useOptions();
    const [sorting, setSorting] = useState<SortingState>([
        { id: 'name', desc: false },
    ]);
    const [globalFilter, setGlobalFilter] = useState('');
    const canManage = auth.can.manageUsers;

    const columns = useMemo<ColumnDef<Person>[]>(() => {
        const sortable = (title: string) =>
            function SortHeader({ column }: HeaderContext<Person, unknown>) {
                return (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-3"
                        onClick={() =>
                            column.toggleSorting(column.getIsSorted() === 'asc')
                        }
                    >
                        {title} <ArrowUpDown className="size-3.5" />
                    </Button>
                );
            };

        const base: ColumnDef<Person>[] = [
            {
                accessorKey: 'name',
                header: sortable('Name'),
                cell: ({ row }) => (
                    <div className="flex items-center gap-2">
                        <span className="font-medium">{row.original.name}</span>
                        {!row.original.active && (
                            <Badge variant="outline">inaktiv</Badge>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'function',
                header: sortable('Funktion'),
                cell: ({ row }) => row.original.function ?? '',
            },
            {
                id: 'school',
                accessorFn: (person) => label(options.schools, person.school),
                header: sortable('Schulhaus'),
            },
            {
                accessorKey: 'email',
                header: 'E-Mail',
                cell: ({ row }) => (
                    <a
                        className="hover:underline"
                        href={`mailto:${row.original.email}`}
                    >
                        {row.original.email}
                    </a>
                ),
            },
            {
                accessorKey: 'phone',
                header: 'Telefon',
                cell: ({ row }) => row.original.phone ?? '',
            },
        ];

        if (!canManage) {
            return base;
        }

        return [
            ...base,
            {
                id: 'role',
                accessorFn: (person) => label(options.roles, person.role),
                header: sortable('Rolle'),
            },
            {
                id: 'actions',
                header: '',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex justify-end gap-1">
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href={`/personen/${row.original.id}/bearbeiten`}
                                aria-label="Bearbeiten"
                            >
                                <Pencil />
                            </Link>
                        </Button>
                        {row.original.id !== auth.user.id && (
                            <DeleteButton
                                url={`/personen/${row.original.id}`}
                                title={`${row.original.name} löschen?`}
                                size="icon"
                            />
                        )}
                    </div>
                ),
            },
        ];
    }, [auth.user.id, canManage, options.roles, options.schools]);

    const table = useReactTable({
        data: people,
        columns,
        state: { sorting, globalFilter },
        onSortingChange: setSorting,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
    });

    const rows = table.getRowModel().rows;

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

                <div className="relative md:w-72">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        value={globalFilter}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        placeholder="Name, Funktion, E-Mail …"
                        className="pl-8"
                        aria-label="Personen durchsuchen"
                    />
                </div>

                {rows.length === 0 && (
                    <EmptyState icon={Users} title="Keine Personen gefunden" />
                )}

                {/* Desktop: Tabelle */}
                {rows.length > 0 && (
                    <div className="hidden rounded-xl border md:block">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((group) => (
                                    <TableRow key={group.id}>
                                        {group.headers.map((header) => (
                                            <TableHead
                                                key={header.id}
                                                className="px-3"
                                            >
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(
                                                          header.column
                                                              .columnDef.header,
                                                          header.getContext(),
                                                      )}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className={
                                            row.original.active
                                                ? ''
                                                : 'opacity-60'
                                        }
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className="px-3"
                                            >
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {/* Mobile: Karten */}
                <div className="grid gap-3 md:hidden">
                    {rows.map(({ original: person }) => (
                        <div
                            key={person.id}
                            className="grid gap-1 rounded-xl border bg-card p-4"
                        >
                            <div className="flex items-start justify-between gap-2">
                                <div>
                                    <p className="font-medium">{person.name}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {[
                                            person.function,
                                            label(
                                                options.schools,
                                                person.school,
                                            ),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
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
            </div>
        </>
    );
}

PeopleIndex.layout = {
    breadcrumbs: [{ title: 'Personen', href: '/personen' }],
};
