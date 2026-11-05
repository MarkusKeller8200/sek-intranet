import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Eintrag, Kategorien, Summen } from '../lib/types';
import {
    buchungsartName,
    herkunft,
    hhmm,
    kategorieName,
    kurzDatum,
    notizen,
    stunden,
    wochentag,
    zeitraum,
} from '../lib/types';

/** Einträge eines Monats mit Summen je Kategorie. */
export default function EintraegeTabelle({
    eintraege,
    summen,
    kategorien,
    onEdit,
    onDelete,
}: {
    eintraege: Eintrag[];
    summen: Summen;
    kategorien: Kategorien;
    /** nur für Einträge aus dem Intranet; aus Fremdsystemen nur löschen (#102) */
    onEdit?: (e: Eintrag) => void;
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
                            <TableHead>Tag</TableHead>
                            <TableHead>Datum</TableHead>
                            <TableHead>Buchungsart</TableHead>
                            <TableHead>Kategorie</TableHead>
                            <TableHead>Zeit</TableHead>
                            <TableHead className="text-right">Soll</TableHead>
                            <TableHead className="text-right">Ist</TableHead>
                            <TableHead className="text-right">Delta</TableHead>
                            <TableHead>Herkunft</TableHead>
                            <TableHead>Notizen</TableHead>
                            {(onEdit || onDelete) && (
                                <TableHead className="w-20" />
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {eintraege.map((e) => (
                            <TableRow key={e.id}>
                                <TableCell className="font-medium">
                                    {wochentag(e.datum)}
                                </TableCell>
                                <TableCell
                                    className="whitespace-nowrap tabular-nums"
                                    translate="no"
                                >
                                    {kurzDatum(e.datum)}
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
                                    className="whitespace-nowrap tabular-nums"
                                    translate="no"
                                >
                                    {zeitraum(e)}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground">
                                    –
                                </TableCell>
                                <TableCell
                                    className="text-right tabular-nums"
                                    translate="no"
                                >
                                    {hhmm(e.stunden)}
                                </TableCell>
                                <TableCell className="text-right text-muted-foreground">
                                    –
                                </TableCell>
                                <TableCell>
                                    {herkunft(e.quelle)}
                                </TableCell>
                                <TableCell
                                    className="text-muted-foreground"
                                    translate="no"
                                >
                                    {notizen(e)}
                                </TableCell>
                                {(onEdit || onDelete) && (
                                    <TableCell className="whitespace-nowrap text-right">
                                        {onEdit && !e.quelle && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Eintrag bearbeiten"
                                                onClick={() => onEdit(e)}
                                            >
                                                <Pencil />
                                            </Button>
                                        )}
                                        {onDelete && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Eintrag löschen"
                                                onClick={() => onDelete(e)}
                                            >
                                                <Trash2 />
                                            </Button>
                                        )}
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
