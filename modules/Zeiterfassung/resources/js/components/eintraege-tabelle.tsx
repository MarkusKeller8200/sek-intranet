import { Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import type { Eintrag, Kategorien, Summen } from '../lib/types';
import { buchungsartName, kategorieName, stunden } from '../lib/types';

/** Einträge eines Monats mit Summen je Kategorie. */
export default function EintraegeTabelle({
    eintraege,
    summen,
    kategorien,
    onDelete,
}: {
    eintraege: Eintrag[];
    summen: Summen;
    kategorien: Kategorien;
    onDelete?: (e: Eintrag) => void;
}) {
    if (eintraege.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Für diesen Monat gibt es noch keine Einträge.
            </p>
        );
    }

    return (
        <div className="grid gap-4">
            <div className="overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Datum</TableHead>
                            <TableHead>Buchungsart</TableHead>
                            <TableHead>Kategorie</TableHead>
                            <TableHead>Zeit</TableHead>
                            <TableHead className="text-right">
                                Stunden
                            </TableHead>
                            <TableHead>Projekt / Notiz</TableHead>
                            {onDelete && <TableHead className="w-10" />}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {eintraege.map((e) => (
                            <TableRow key={e.id}>
                                <TableCell className="whitespace-nowrap">
                                    {formatDate(e.datum)}
                                </TableCell>
                                <TableCell>
                                    {buchungsartName(
                                        kategorien,
                                        e.buchungsart,
                                    ) ||
                                        (e.art === 'abwesenheit'
                                            ? 'Abwesenheit'
                                            : 'Arbeit')}
                                </TableCell>
                                <TableCell>
                                    {e.art === 'abwesenheit'
                                        ? '–'
                                        : kategorieName(
                                              kategorien,
                                              e.kategorie,
                                          )}
                                </TableCell>
                                <TableCell
                                    className="whitespace-nowrap"
                                    translate="no"
                                >
                                    {e.beginn
                                        ? `${e.beginn}–${e.ende ?? ''}`
                                        : '–'}
                                    {e.pause_min
                                        ? ` (−${e.pause_min} min)`
                                        : ''}
                                </TableCell>
                                <TableCell
                                    className="text-right tabular-nums"
                                    translate="no"
                                >
                                    {e.stunden.toFixed(2)}
                                </TableCell>
                                <TableCell
                                    className="text-muted-foreground"
                                    translate="no"
                                >
                                    {[
                                        e.projekt,
                                        e.notiz,
                                        e.quelle === 'clicktime'
                                            ? 'ClickTime'
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </TableCell>
                                {onDelete && (
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Eintrag löschen"
                                            onClick={() => onDelete(e)}
                                        >
                                            <Trash2 />
                                        </Button>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <dl className="grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                {Object.entries(summen.buchungsarten ?? {}).map(([k, h]) => (
                    <div
                        key={`b-${k}`}
                        className="flex justify-between gap-4 border-b py-1"
                    >
                        <dt>{buchungsartName(kategorien, k)}</dt>
                        <dd className="tabular-nums" translate="no">
                            {stunden(h)}
                        </dd>
                    </div>
                ))}
                {Object.entries(summen.kategorien).map(([k, h]) => (
                    <div
                        key={k}
                        className="flex justify-between gap-4 border-b py-1"
                    >
                        <dt>{kategorieName(kategorien, k)}</dt>
                        <dd className="tabular-nums" translate="no">
                            {stunden(h)}
                        </dd>
                    </div>
                ))}
                <div className="flex justify-between gap-4 py-1 font-semibold">
                    <dt>Arbeit</dt>
                    <dd className="tabular-nums" translate="no">
                        {stunden(summen.arbeit)}
                    </dd>
                </div>
                <div className="flex justify-between gap-4 py-1 font-semibold">
                    <dt>Abwesenheit</dt>
                    <dd className="tabular-nums" translate="no">
                        {stunden(summen.abwesenheit)}
                    </dd>
                </div>
                <div className="flex justify-between gap-4 py-1 font-semibold sm:col-span-2">
                    <dt>Total</dt>
                    <dd className="tabular-nums" translate="no">
                        {stunden(summen.total)}
                    </dd>
                </div>
            </dl>
        </div>
    );
}
