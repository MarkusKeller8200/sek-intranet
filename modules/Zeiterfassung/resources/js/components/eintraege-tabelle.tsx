import { ChevronDown, ChevronRight, Pencil, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Eintrag, Kategorien, Summen } from '../lib/types';
import {
    buchungsartName,
    herkunft,
    hhmm,
    kategorieName,
    kurzDatum,
    notizen,
    stunden,
    tageImMonat,
    tagesZeit,
    wochenende,
    wochentag,
    zeitraum,
} from '../lib/types';

type Aktionen = {
    /** nur für Einträge aus dem Intranet; aus Fremdsystemen nur löschen */
    onEdit?: (e: Eintrag) => void;
    onDelete?: (e: Eintrag) => void;
};

/**
 * Einträge eines Monats (#102): je Tag eine Totalzeile (Wochenende und Feiertage anders eingefärbt), aufklappbar zu
 * den einzelnen Buchungen in kleiner Schrift; darunter die Summen.
 */
export default function EintraegeTabelle({
    monat,
    eintraege,
    summen,
    kategorien,
    feiertage = {},
    onEdit,
    onDelete,
}: Aktionen & {
    monat: string;
    eintraege: Eintrag[];
    summen: Summen;
    kategorien: Kategorien;
    /** Datum → Name des Feiertags */
    feiertage?: Record<string, string>;
}) {
    const [offen, setOffen] = useState<Set<string>>(new Set());
    const proTag = new Map<string, Eintrag[]>();
    for (const e of eintraege) {
        proTag.set(e.datum, [...(proTag.get(e.datum) ?? []), e]);
    }
    const tage = tageImMonat(monat);
    const mitBuchungen = tage.filter((t) => proTag.has(t));
    const alleOffen =
        mitBuchungen.length > 0 && mitBuchungen.every((t) => offen.has(t));
    const aktionen = Boolean(onEdit || onDelete);

    const umschalten = (tag: string) => {
        const neu = new Set(offen);
        if (neu.has(tag)) {
            neu.delete(tag);
        } else {
            neu.add(tag);
        }
        setOffen(neu);
    };
    const alle = () => setOffen(alleOffen ? new Set() : new Set(mitBuchungen));

    return (
        <div className="grid gap-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                    {eintraege.length === 0
                        ? 'Für diesen Monat gibt es noch keine Einträge.'
                        : 'Pro Tag das Total; aufklappen zeigt die einzelnen Buchungen.'}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={mitBuchungen.length === 0}
                    onClick={alle}
                >
                    {alleOffen ? <ChevronDown /> : <ChevronRight />}
                    {alleOffen ? 'Alle zuklappen' : 'Alle aufklappen'}
                </Button>
            </div>
            <div className="overflow-x-auto rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-10" />
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
                            {aktionen && <TableHead className="w-20" />}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {tage.map((tag) => (
                            <Fragment key={tag}>
                                <TagesZeile
                                    tag={tag}
                                    liste={proTag.get(tag) ?? []}
                                    feiertag={feiertage[tag]}
                                    auf={offen.has(tag)}
                                    aktionen={aktionen}
                                    onToggle={() => umschalten(tag)}
                                />
                                {offen.has(tag) &&
                                    (proTag.get(tag) ?? []).map((e) => (
                                        <BuchungsZeile
                                            key={e.id}
                                            e={e}
                                            kategorien={kategorien}
                                            onEdit={onEdit}
                                            onDelete={onDelete}
                                        />
                                    ))}
                            </Fragment>
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

/** Totalzeile eines Tages: Ist-Summe, erste bis letzte Zeit, Feiertag; Wochenende und Feiertage anders eingefärbt. */
function TagesZeile({
    tag,
    liste,
    feiertag,
    auf,
    aktionen,
    onToggle,
}: {
    tag: string;
    liste: Eintrag[];
    feiertag?: string;
    auf: boolean;
    aktionen: boolean;
    onToggle: () => void;
}) {
    const frei = Boolean(feiertag) || wochenende(tag);
    const ist = liste.reduce((s, e) => s + e.stunden, 0);

    return (
        <TableRow
            className={cn(
                'font-medium',
                frei
                    ? 'bg-warning-soft hover:bg-warning-soft'
                    : 'bg-secondary/60 hover:bg-secondary',
            )}
        >
            <TableCell className="py-1">
                {liste.length > 0 && (
                    <Button
                        variant="ghost"
                        size="icon"
                        className="size-7"
                        aria-expanded={auf}
                        aria-label={
                            auf ? 'Buchungen ausblenden' : 'Buchungen anzeigen'
                        }
                        onClick={onToggle}
                    >
                        {auf ? <ChevronDown /> : <ChevronRight />}
                    </Button>
                )}
            </TableCell>
            <TableCell>{wochentag(tag)}</TableCell>
            <TableCell className="tabular-nums" translate="no">
                {kurzDatum(tag)}
            </TableCell>
            <TableCell colSpan={2} translate="no">
                {feiertag ?? ''}
            </TableCell>
            <TableCell
                className="whitespace-nowrap tabular-nums"
                translate="no"
            >
                {tagesZeit(liste)}
            </TableCell>
            <TableCell className="text-right text-muted-foreground">
                –
            </TableCell>
            <TableCell className="text-right tabular-nums" translate="no">
                {liste.length > 0 ? hhmm(ist) : ''}
            </TableCell>
            <TableCell className="text-right text-muted-foreground">
                –
            </TableCell>
            <TableCell colSpan={aktionen ? 3 : 2} />
        </TableRow>
    );
}

/** Einzelne Buchung (aufgeklappt) in kleiner Schrift. */
function BuchungsZeile({
    e,
    kategorien,
    onEdit,
    onDelete,
}: Aktionen & { e: Eintrag; kategorien: Kategorien }) {
    const name =
        buchungsartName(kategorien, e.buchungsart) ||
        (e.art === 'abwesenheit' ? 'Abwesenheit' : 'Arbeit');

    return (
        <TableRow className="text-xs [&>td]:py-1">
            <TableCell colSpan={3} />
            <TableCell>{name}</TableCell>
            <TableCell>
                {e.art === 'abwesenheit'
                    ? '–'
                    : kategorieName(kategorien, e.kategorie)}
            </TableCell>
            <TableCell
                className="whitespace-nowrap tabular-nums"
                translate="no"
            >
                {zeitraum(e)}
            </TableCell>
            <TableCell />
            <TableCell className="text-right tabular-nums" translate="no">
                {hhmm(e.stunden)}
            </TableCell>
            <TableCell />
            <TableCell>{herkunft(e.quelle)}</TableCell>
            <TableCell className="text-muted-foreground" translate="no">
                {notizen(e)}
            </TableCell>
            {(onEdit || onDelete) && (
                <TableCell className="text-right whitespace-nowrap">
                    {onEdit && !e.quelle && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-7"
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
                            className="size-7"
                            aria-label="Eintrag löschen"
                            onClick={() => onDelete(e)}
                        >
                            <Trash2 />
                        </Button>
                    )}
                </TableCell>
            )}
        </TableRow>
    );
}
