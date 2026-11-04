import { Link } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import GlossaryTags from '@/components/glossary-tags';
import { Badge } from '@/components/ui/badge';
import type { GlossaryRow } from '@/lib/glossary';

/**
 * Kachel eines Glossar-Eintrags (#88): kompakte Höhe, einspaltig. Abkürzung und Bezeichnung, Labels, gekürzte
 * Kurzbeschreibung (ganze als Tooltip), Status (wenn nicht aktuell), Link zur Quelle.
 */
export default function GlossaryCard({
    row,
    colors,
    status,
}: {
    row: GlossaryRow;
    colors: {
        kategorien: Record<string, string>;
        herkunft: Record<string, string>;
    };
    status: Record<string, string>;
}) {
    const bezeichnung = row.bez_eigen || row.bez_offiziell;

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
                {!row.begriff && bezeichnung && (
                    <span className="min-w-0 truncate text-sm" translate="no">
                        {bezeichnung}
                    </span>
                )}
                {row.status && row.status !== 'aktuell' && (
                    <Badge variant="outline">
                        {status[row.status] ?? row.status}
                    </Badge>
                )}
                <span className="ml-auto flex flex-wrap items-center gap-1">
                    <GlossaryTags
                        value={row.kategorien}
                        colors={colors.kategorien}
                    />
                    <GlossaryTags
                        value={row.herkunft}
                        colors={colors.herkunft}
                    />
                </span>
            </div>
            {(row.kurzbeschreibung || row.url) && (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {row.kurzbeschreibung && (
                        <p
                            className="min-w-0 flex-1 truncate"
                            title={row.kurzbeschreibung}
                            translate="no"
                        >
                            {row.kurzbeschreibung}
                        </p>
                    )}
                    {row.url && (
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
        </div>
    );
}
