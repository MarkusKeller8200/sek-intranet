import { useForm } from '@inertiajs/react';
import { Download, PenLine } from 'lucide-react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import type { Unterschrift } from '../lib/types';

export const UNTERSCHRIFT_STATUS: Record<string, string> = {
    entwurf: 'Entwurf',
    versendet: 'Zur Unterschrift',
    teilweise: 'Teilweise unterschrieben',
    unterschrieben: 'Unterschrieben',
    abgelehnt: 'Abgelehnt',
    zurueckgezogen: 'Zurückgezogen',
    abgelaufen: 'Abgelaufen',
    fehler: 'Fehler',
};

const PERSON_STATUS: Record<string, string> = {
    wartet: 'wartet',
    offen: 'offen',
    unterschrieben: 'unterschrieben',
    abgelehnt: 'abgelehnt',
};

/** Status der elektronischen Unterschrift als Badge. */
export function UnterschriftBadge({ status }: { status: string | null }) {
    if (!status) {
        return null;
    }

    return (
        <Badge
            variant={
                status === 'unterschrieben'
                    ? 'default'
                    : ['abgelehnt', 'zurueckgezogen', 'abgelaufen', 'fehler'].includes(
                            status,
                        )
                      ? 'destructive'
                      : 'secondary'
            }
        >
            {UNTERSCHRIFT_STATUS[status] ?? status}
        </Badge>
    );
}

/**
 * Elektronische Unterschrift eines visierten Monatsrapports (#43): Status, Unterzeichnende, Senden mit
 * Reihenfolge und optional Beobachtenden, unterschriebenes PDF herunterladen.
 */
export default function UnterschriftKarte({
    personId,
    monat,
    visiert,
    unterschrift,
}: {
    personId: string;
    monat: string;
    visiert: boolean;
    unterschrift: Unterschrift;
}) {
    const form = useForm({
        monat,
        reihenfolge: 'person_zuerst',
        beobachtende: '',
    });
    const offen =
        !unterschrift.status ||
        ['abgelehnt', 'zurueckgezogen', 'abgelaufen', 'fehler'].includes(
            unterschrift.status,
        );
    const senden = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/zeiterfassung/visum/${personId}/unterschrift`, {
            preserveScroll: true,
        });
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex flex-wrap items-center gap-2">
                    Elektronische Unterschrift
                    <UnterschriftBadge status={unterschrift.status} />
                </CardTitle>
                <CardDescription>
                    Der visierte Monatsrapport geht als PDF an die Person und
                    an die Schulleitung. Das unterschriebene PDF wird danach
                    automatisch hier abgelegt.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {unterschrift.auftrag && (
                    <ul className="grid gap-1 text-sm">
                        {unterschrift.auftrag.signees.map((s) => (
                            <li
                                key={`${s.order}-${s.email}`}
                                className="flex flex-wrap items-center gap-2"
                            >
                                <span className="tabular-nums text-muted-foreground">
                                    {s.order + 1}.
                                </span>
                                <span translate="no">{s.name}</span>
                                <Badge variant="outline">
                                    {PERSON_STATUS[s.status] ?? s.status}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}

                {unterschrift.pdf && (
                    <div>
                        <Button asChild variant="outline">
                            <a href={unterschrift.pdf}>
                                <Download /> Unterschriebenes PDF
                            </a>
                        </Button>
                    </div>
                )}

                {visiert && offen && !unterschrift.anbieter && (
                    <Alert>
                        <AlertDescription>
                            Kein Anbieter eingerichtet. Die Administration
                            schaltet unter Prototyp → Plugins das Plugin
                            DeepSign ein.
                        </AlertDescription>
                    </Alert>
                )}

                {visiert && offen && unterschrift.anbieter && (
                    <form
                        onSubmit={senden}
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        <FormField id="u-reihenfolge" label="Reihenfolge">
                            <NativeSelect
                                id="u-reihenfolge"
                                value={form.data.reihenfolge}
                                onChange={(e) =>
                                    form.setData('reihenfolge', e.target.value)
                                }
                            >
                                <option value="person_zuerst">
                                    Zuerst die Person, dann die Schulleitung
                                </option>
                                <option value="schulleitung_zuerst">
                                    Zuerst die Schulleitung, dann die Person
                                </option>
                                <option value="gleichzeitig">
                                    Gleichzeitig
                                </option>
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="u-beobachtende"
                            label="Zur Kenntnis an (optional)"
                            hint="Z. B. Personalabteilung: sieht das Dokument, unterschreibt nicht."
                            error={form.errors.beobachtende}
                        >
                            <Input
                                id="u-beobachtende"
                                type="email"
                                value={form.data.beobachtende}
                                onChange={(e) =>
                                    form.setData('beobachtende', e.target.value)
                                }
                            />
                        </FormField>
                        {unterschrift.anbieter.demo && (
                            <p className="text-sm text-muted-foreground sm:col-span-2">
                                Demo-Modus: Es geht nichts an DeepSign. Die
                                Unterschriften lassen sich unter Verwaltung →
                                Unterschriften simulieren.
                            </p>
                        )}
                        <div className="sm:col-span-2">
                            <Button type="submit" disabled={form.processing}>
                                <PenLine /> Zur Unterschrift senden
                            </Button>
                        </div>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}
