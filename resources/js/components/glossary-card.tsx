import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import type { ReactNode } from 'react';
import GlossaryTags from '@/components/glossary-tags';
import { Badge } from '@/components/ui/badge';
import type { GlossaryRow } from '@/lib/glossary';

/** Weniger wichtige Angaben (unten in der Kachel, klein), in dieser Reihenfolge. */
const DETAILS = [
    'abk_offiziell',
    'abk_eigen',
    'aliase_offiziell',
    'alias_eigen',
    'bez_offiziell',
    'bez_eigen',
    'genutzt_in',
    'suchbegriffe',
    'gueltig_ab',
    'gueltig_bis',
] as const;

/** Lange Texte: eigene Zeile, auf drei Zeilen gekürzt (ganzer Text als Tooltip). */
const TEXTS = ['beschreibung', 'notiz'] as const;

/**
 * Kachel eines Glossar-Eintrags (#88, #90): einspaltig. Welche Angaben erscheinen, bestimmt die Spaltenauswahl der
 * Tabelle (`visible`). Oben das Wichtigste (Abkürzung, Bezeichnung, Status, Labels), darunter die Kurzbeschreibung,
 * unten die weniger wichtigen Angaben – ausgeblendete Angaben brauchen keinen Platz.
 */
export default function GlossaryCard({
    row,
    visible,
    colors,
    status,
    felder,
}: {
    row: GlossaryRow;
    visible: Set<string>;
    colors: {
        kategorien: Record<string, string>;
        herkunft: Record<string, string>;
    };
    status: Record<string, string>;
    felder: Record<string, string>;
}) {
    const show = (id: string) => visible.has(id);
    const bezeichnung = row.bez_eigen || row.bez_offiziell;
    const showBez =
        !row.begriff &&
        bezeichnung !== '' &&
        (show('bez_offiziell') || show('bez_eigen'));
    // Werte, die schon im Titel stehen, nicht wiederholen
    const inTitle = new Set([row.anzeige, showBez ? bezeichnung : '']);
    const details = DETAILS.filter(
        (id) => show(id) && row[id] !== '' && !inTitle.has(row[id]),
    );
    const texts = TEXTS.filter((id) => show(id) && row[id] !== '');
    const tags =
        (show('kategorien') && row.kategorien !== '') ||
        (show('herkunft') && row.herkunft !== '');
    const kurz = show('kurzbeschreibung') && row.kurzbeschreibung !== '';
    const url = show('url') && row.url !== '';

    return (
        <div className="grid gap-1">
            <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <Link
                    href={`/glossar/${row.id}`}
                    className="font-semibold text-primary underline-offset-2 hover:underline focus-visible:underline"
                >
                    <span
                        translate="no"
                        className={row.begriff ? 'italic' : undefined}
                    >
                        {row.anzeige || '(neu)'}
                        {row.mehrdeutig && <sup>{row.bedeutungen}</sup>}
                    </span>
                </Link>
                {showBez && (
                    <span className="min-w-0 truncate text-sm" translate="no">
                        {bezeichnung}
                    </span>
                )}
                {!row.aktiv && <Badge variant="outline">inaktiv</Badge>}
                {show('status') && row.status && row.status !== 'aktuell' && (
                    <Badge variant="outline">
                        {status[row.status] ?? row.status}
                    </Badge>
                )}
                {tags && (
                    <span className="ml-auto flex flex-wrap items-center gap-1">
                        {show('kategorien') && (
                            <GlossaryTags
                                value={row.kategorien}
                                colors={colors.kategorien}
                            />
                        )}
                        {show('herkunft') && (
                            <GlossaryTags
                                value={row.herkunft}
                                colors={colors.herkunft}
                            />
                        )}
                    </span>
                )}
            </div>
            {(kurz || url) && (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {kurz && (
                        <p
                            className="min-w-0 flex-1 truncate"
                            title={row.kurzbeschreibung}
                            translate="no"
                        >
                            {row.kurzbeschreibung}
                        </p>
                    )}
                    {url && (
                        <a
                            href={row.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="ml-auto inline-flex shrink-0 items-center gap-1 text-primary hover:underline"
                            title={row.url}
                        >
                            Quelle{' '}
                            <ExternalLink className="size-3" aria-hidden />
                        </a>
                    )}
                </div>
            )}
            {texts.map((id) => (
                <p
                    key={id}
                    className="line-clamp-3 text-xs whitespace-pre-line text-muted-foreground"
                    title={row[id]}
                    translate="no"
                >
                    <Label>{felder[id] ?? id}</Label> {row[id]}
                </p>
            ))}
            {details.length > 0 && (
                <dl className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                    {details.map((id) => (
                        <div key={id} className="flex min-w-0 gap-1">
                            <dt>
                                <Label>{felder[id] ?? id}</Label>
                            </dt>
                            <dd className="truncate" translate="no">
                                {row[id]}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </div>
    );
}

function Label({ children }: { children: ReactNode }) {
    return <span className="font-medium text-foreground/70">{children}:</span>;
}
