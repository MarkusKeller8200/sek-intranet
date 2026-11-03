import { Head, Link } from '@inertiajs/react';
import { Plus, Users, Workflow } from 'lucide-react';
import { useMemo, useState } from 'react';
import EmptyState from '@/components/empty-state';
import PageHeader from '@/components/page-header';
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
import { NativeSelect } from '@/components/ui/native-select';

type Eintrag = {
    id: string;
    titel: string;
    bereich: string;
    status: string;
    beschreibung: string;
    verantwortlich: string;
    phasen: string[];
    schritte: number;
    updated_at: string | null;
};

/** Verzeichnis der Abläufe (Checklisten-Vorlagen). */
export default function AblaeufeIndex({
    ablaeufe,
    bereiche,
    status,
    darfBearbeiten,
}: {
    ablaeufe: Eintrag[];
    bereiche: Record<string, string>;
    status: Record<string, string>;
    darfBearbeiten: boolean;
}) {
    const [q, setQ] = useState('');
    const [bereich, setBereich] = useState('');
    const [stand, setStand] = useState('');
    const liste = useMemo(() => {
        const needle = q.trim().toLowerCase();

        return ablaeufe.filter(
            (a) =>
                (bereich === '' || a.bereich === bereich) &&
                (stand === '' || a.status === stand) &&
                (needle === '' ||
                    `${a.titel} ${a.beschreibung} ${a.verantwortlich}`
                        .toLowerCase()
                        .includes(needle)),
        );
    }, [ablaeufe, q, bereich, stand]);

    return (
        <>
            <Head title="Abläufe Verwaltung" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Abläufe Verwaltung"
                    description="Verzeichnis wichtiger Abläufe als Checklisten: Schritte mit Verantwortung, Kommunikation, Dokumenten und Bemerkungen."
                />
                <div className="flex flex-wrap items-end gap-3">
                    <Input
                        type="search"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Suchen …"
                        aria-label="Abläufe durchsuchen"
                        className="max-w-xs"
                    />
                    <NativeSelect
                        value={bereich}
                        onChange={(e) => setBereich(e.target.value)}
                        aria-label="Bereich"
                    >
                        <option value="">Alle Bereiche</option>
                        {Object.entries(bereiche).map(([k, l]) => (
                            <option key={k} value={k}>
                                {l}
                            </option>
                        ))}
                    </NativeSelect>
                    {darfBearbeiten && (
                        <NativeSelect
                            value={stand}
                            onChange={(e) => setStand(e.target.value)}
                            aria-label="Status"
                        >
                            <option value="">Alle Status</option>
                            {Object.entries(status).map(([k, l]) => (
                                <option key={k} value={k}>
                                    {l}
                                </option>
                            ))}
                        </NativeSelect>
                    )}
                    {darfBearbeiten && (
                        <span className="ml-auto flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href="/ablaeufe/gruppen">
                                    <Users /> Personengruppen
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href="/ablaeufe/neu">
                                    <Plus /> Neuer Ablauf
                                </Link>
                            </Button>
                        </span>
                    )}
                </div>

                {liste.length === 0 ? (
                    <EmptyState icon={Workflow} title="Keine Abläufe gefunden">
                        {darfBearbeiten
                            ? 'Mit «Neuer Ablauf» eine Checkliste erfassen.'
                            : 'Es sind noch keine gültigen Abläufe veröffentlicht.'}
                    </EmptyState>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {liste.map((a) => (
                            <Link
                                key={a.id}
                                href={`/ablaeufe/${a.id}`}
                                className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Card className="h-full transition-colors hover:bg-muted/40">
                                    <CardHeader>
                                        <CardTitle className="flex flex-wrap items-center gap-2">
                                            <span translate="no">
                                                {a.titel}
                                            </span>
                                            {a.status !== 'gueltig' && (
                                                <Badge variant="secondary">
                                                    {status[a.status] ??
                                                        a.status}
                                                </Badge>
                                            )}
                                        </CardTitle>
                                        <CardDescription>
                                            {bereiche[a.bereich] ?? a.bereich}
                                            {a.verantwortlich && (
                                                <>
                                                    {' · '}
                                                    <span translate="no">
                                                        {a.verantwortlich}
                                                    </span>
                                                </>
                                            )}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-2 text-sm text-muted-foreground">
                                        {a.beschreibung && (
                                            <p
                                                className="line-clamp-2"
                                                translate="no"
                                            >
                                                {a.beschreibung}
                                            </p>
                                        )}
                                        <p>
                                            <span translate="no">
                                                {a.phasen.join(' · ')}
                                            </span>
                                            {` · ${a.schritte} Schritte`}
                                        </p>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AblaeufeIndex.layout = {
    breadcrumbs: [{ title: 'Abläufe Verwaltung', href: '/ablaeufe' }],
};
