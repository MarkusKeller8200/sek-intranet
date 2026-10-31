import { router, useForm } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    CircleCheck,
    CircleDashed,
    Database,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import { formatDateTime, nowInputValue, toInputValue } from '@/lib/format';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type PersonLink = {
    id: string;
    direction: 'source' | 'target';
    system: string;
    external_id: string | null;
    id_label: string | null;
    last_sync_at: string | null;
    note: string | null;
    status: 'imported' | 'unknown' | 'pending' | 'current';
};

type LinkForm = {
    direction: string;
    system: string;
    external_id: string;
    id_label: string;
    last_sync_at: string;
    note: string;
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-2 text-sm';

function Status({ link }: { link: PersonLink }) {
    switch (link.status) {
        case 'pending':
            return (
                <Badge className="border-amber-300 bg-amber-100 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                    <RefreshCw />
                    {link.last_sync_at ? 'Geändert – Abgleich nötig' : 'Noch nie abgeglichen'}
                </Badge>
            );
        case 'current':
            return (
                <Badge variant="outline" className="text-emerald-700 dark:text-emerald-400">
                    <CircleCheck /> Aktuell
                </Badge>
            );
        case 'imported':
            return (
                <Badge variant="outline">
                    <ArrowDownToLine /> Übernommen
                </Badge>
            );
        default:
            return (
                <Badge variant="outline" className="text-muted-foreground">
                    <CircleDashed /> Ohne Abgleichdatum
                </Badge>
            );
    }
}

/** Formular zum Erfassen oder Ändern eines Eintrags. */
function LinkEditor({
    personId,
    link,
    systems,
    onDone,
}: {
    personId: string;
    link: PersonLink | null;
    systems: Record<string, string>;
    onDone: () => void;
}) {
    const form = useForm<LinkForm>({
        direction: link?.direction ?? 'target',
        system: link?.system ?? Object.keys(systems)[0] ?? '',
        external_id: link?.external_id ?? '',
        id_label: link?.id_label ?? '',
        last_sync_at: toInputValue(link?.last_sync_at),
        note: link?.note ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (link) {
            form.put(`/personen/${personId}/herkunft/${link.id}`, options);
        } else {
            form.post(`/personen/${personId}/herkunft`, options);
        }
    };

    const error = (key: keyof LinkForm) =>
        form.errors[key] && (
            <span className="text-xs text-destructive">{form.errors[key]}</span>
        );

    return (
        <form onSubmit={submit} className="grid gap-3 rounded-lg border bg-muted/30 p-3">
            <div className="grid gap-3 sm:grid-cols-3">
                <label className="grid gap-1 text-sm">
                    Richtung
                    <select
                        className={selectClass}
                        value={form.data.direction}
                        onChange={(e) => form.setData('direction', e.target.value)}
                    >
                        <option value="source">Quelle (Daten kommen von dort)</option>
                        <option value="target">Ziel (Daten werden dorthin exportiert)</option>
                    </select>
                    {error('direction')}
                </label>
                <label className="grid gap-1 text-sm">
                    System
                    <select
                        className={selectClass}
                        value={form.data.system}
                        onChange={(e) => form.setData('system', e.target.value)}
                    >
                        {Object.entries(systems).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                    {error('system')}
                </label>
                <label className="grid gap-1 text-sm">
                    ID / PK im System
                    <Input
                        value={form.data.external_id}
                        onChange={(e) => form.setData('external_id', e.target.value)}
                        placeholder="z. B. T-001"
                        translate="no"
                    />
                    {error('external_id')}
                </label>
                <label className="grid gap-1 text-sm">
                    Art der ID
                    <Input
                        value={form.data.id_label}
                        onChange={(e) => form.setData('id_label', e.target.value)}
                        placeholder="z. B. Escola-ID, objectId, UPN"
                    />
                </label>
                <label className="grid gap-1 text-sm">
                    Letzter Abgleich
                    <span className="flex gap-1">
                        <Input
                            type="datetime-local"
                            value={form.data.last_sync_at}
                            onChange={(e) => form.setData('last_sync_at', e.target.value)}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-9"
                            onClick={() => form.setData('last_sync_at', nowInputValue())}
                        >
                            Jetzt
                        </Button>
                    </span>
                    {error('last_sync_at')}
                </label>
                <label className="grid gap-1 text-sm">
                    Bemerkung
                    <Input
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                    />
                </label>
            </div>
            <div className="flex gap-2">
                <Button type="submit" size="sm" disabled={form.processing}>
                    Speichern
                </Button>
                <Button type="button" size="sm" variant="outline" onClick={onDone}>
                    Abbrechen
                </Button>
            </div>
        </form>
    );
}

/**
 * Datenherkunft und Synchronisation: Quelle(n) mit ID/PK (gegen Duplikate beim Import) und
 * Zielsysteme mit ID/PK, letztem Abgleich und Hinweis, ob seither etwas geändert wurde.
 */
export default function PersonLinksCard({
    personId,
    links,
    systems,
    canManage,
}: {
    personId: string;
    links: PersonLink[];
    systems: Record<string, string>;
    canManage: boolean;
}) {
    const [editing, setEditing] = useState<string | 'new' | null>(null);
    const pending = links.filter((l) => l.status === 'pending').length;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    <Database className="size-5 text-brand" />
                    Datenherkunft & Synchronisation
                    {pending > 0 && (
                        <Badge className="border-amber-300 bg-amber-100 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            {`${pending} Abgleich offen`}
                        </Badge>
                    )}
                </CardTitle>
                <CardDescription>
                    Aus welchem System die Person stammt und in welche Systeme sie
                    exportiert wird – jeweils mit der ID des Systems. Beim Import wird
                    zuerst über diese ID gesucht, damit keine Duplikate entstehen.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {links.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Noch keine Angaben zur Datenherkunft.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Richtung</th>
                                    <th className="px-3 py-2 font-medium">System</th>
                                    <th className="px-3 py-2 font-medium">ID / PK</th>
                                    <th className="px-3 py-2 font-medium">Letzter Abgleich</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                    {canManage && <th className="px-3 py-2" />}
                                </tr>
                            </thead>
                            <tbody>
                                {links.map((link) =>
                                    editing === link.id ? (
                                        <tr key={link.id} className="border-t">
                                            <td colSpan={6} className="p-2">
                                                <LinkEditor
                                                    personId={personId}
                                                    link={link}
                                                    systems={systems}
                                                    onDone={() => setEditing(null)}
                                                />
                                            </td>
                                        </tr>
                                    ) : (
                                        <tr
                                            key={link.id}
                                            className={cn(
                                                'border-t align-top',
                                                link.status === 'pending' &&
                                                    'bg-amber-50/60 dark:bg-amber-950/20',
                                            )}
                                        >
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {link.direction === 'source' ? (
                                                    <span className="flex items-center gap-1.5">
                                                        <ArrowDownToLine className="size-4 text-muted-foreground" />
                                                        Quelle
                                                    </span>
                                                ) : (
                                                    <span className="flex items-center gap-1.5">
                                                        <ArrowUpFromLine className="size-4 text-muted-foreground" />
                                                        Ziel
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {systems[link.system] ?? link.system}
                                                {link.note && (
                                                    <div
                                                        className="text-xs text-muted-foreground"
                                                        translate="no"
                                                    >
                                                        {link.note}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                <code className="font-mono text-xs" translate="no">
                                                    {link.external_id ?? '–'}
                                                </code>
                                                {link.id_label && (
                                                    <div className="text-xs text-muted-foreground">
                                                        {link.id_label}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 whitespace-nowrap">
                                                {link.last_sync_at
                                                    ? formatDateTime(link.last_sync_at)
                                                    : '–'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Status link={link} />
                                            </td>
                                            {canManage && (
                                                <td className="px-2 py-1 text-right whitespace-nowrap">
                                                    {link.direction === 'target' &&
                                                        link.status === 'pending' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                title="Nach dem Export als abgeglichen markieren"
                                                                onClick={() =>
                                                                    router.post(
                                                                        `/personen/${personId}/herkunft/${link.id}/abgeglichen`,
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    )
                                                                }
                                                            >
                                                                <CircleCheck /> Abgeglichen
                                                            </Button>
                                                        )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        title="Bearbeiten"
                                                        aria-label="Bearbeiten"
                                                        onClick={() => setEditing(link.id)}
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="size-8"
                                                        title="Löschen"
                                                        aria-label="Löschen"
                                                        onClick={() => {
                                                            if (window.confirm(t('Eintrag zur Datenherkunft löschen?'))) {
                                                                router.delete(
                                                                    `/personen/${personId}/herkunft/${link.id}`,
                                                                    { preserveScroll: true },
                                                                );
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </td>
                                            )}
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                )}

                {canManage &&
                    (editing === 'new' ? (
                        <LinkEditor
                            personId={personId}
                            link={null}
                            systems={systems}
                            onDone={() => setEditing(null)}
                        />
                    ) : (
                        <div>
                            <Button variant="outline" size="sm" onClick={() => setEditing('new')}>
                                <Plus /> System hinzufügen
                            </Button>
                        </div>
                    ))}
            </CardContent>
        </Card>
    );
}
