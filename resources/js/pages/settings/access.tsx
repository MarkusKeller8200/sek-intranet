import { Head, useForm } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import DeleteButton from '@/components/delete-button';
import FormField from '@/components/form-field';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';

type Rolle = { label: string; set: boolean; updated_at: string | null };

const digits = (v: string) => v.replace(/\D/g, '').slice(0, 8);

/** PIN einer Rolle setzen: zweimal eingeben, nie angezeigt. */
function PinSetzen({ rolle, onDone }: { rolle: string; onDone: () => void }) {
    const form = useForm({ pin: '', pin_confirmation: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/einstellungen/zugang/${rolle}`, {
            preserveScroll: true,
            onSuccess: () => onDone(),
            onFinish: () => form.setData({ pin: '', pin_confirmation: '' }),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-start gap-2">
            <FormField id={`pin-${rolle}`} label="Neuer PIN" error={form.errors.pin}>
                <Input
                    id={`pin-${rolle}`}
                    type="password"
                    inputMode="numeric"
                    autoComplete="new-password"
                    maxLength={8}
                    className="w-32"
                    value={form.data.pin}
                    onChange={(e) => form.setData('pin', digits(e.target.value))}
                />
            </FormField>
            <FormField id={`pin2-${rolle}`} label="Wiederholen">
                <Input
                    id={`pin2-${rolle}`}
                    type="password"
                    inputMode="numeric"
                    autoComplete="new-password"
                    maxLength={8}
                    className="w-32"
                    value={form.data.pin_confirmation}
                    onChange={(e) =>
                        form.setData('pin_confirmation', digits(e.target.value))
                    }
                />
            </FormField>
            <div className="flex gap-2 self-end">
                <Button
                    type="submit"
                    size="sm"
                    disabled={form.processing || form.data.pin.length !== 8}
                >
                    Speichern
                </Button>
                <Button type="button" size="sm" variant="ghost" onClick={onDone}>
                    Abbrechen
                </Button>
            </div>
        </form>
    );
}

/** Einstellungen → Zugang: PIN je Rolle für die Anmeldung im Prototyp (#69). */
export default function AccessSettings({
    rollen,
    aktiv,
    basicAuth,
}: {
    rollen: Record<string, Rolle>;
    aktiv: boolean;
    basicAuth: boolean;
}) {
    const [offen, setOffen] = useState<string | null>(null);

    return (
        <>
            <Head title="Zugang (PIN)" />
            <div className="flex max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Zugang (PIN)"
                    description="Anmeldung im Prototyp: Rolle wählen und 8-stelligen PIN eingeben. Gespeichert wird nur ein Hash – der PIN ist nirgends lesbar. Jede Rolle braucht einen eigenen PIN."
                />
                {aktiv ? (
                    <Alert>
                        <ShieldCheck />
                        <AlertTitle>PIN-Anmeldung aktiv</AlertTitle>
                        <AlertDescription>
                            Das gemeinsame Passwort (Basic Auth) ist ausgeschaltet.
                            Ohne PIN der Administration wäre es wieder aktiv.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert variant="destructive">
                        <KeyRound />
                        <AlertTitle>Noch nicht aktiv</AlertTitle>
                        <AlertDescription>
                            {basicAuth
                                ? 'Sobald der PIN «Administrator» gesetzt ist, gilt die PIN-Anmeldung und das gemeinsame Passwort (Basic Auth) entfällt.'
                                : 'Sobald der PIN «Administrator» gesetzt ist, gilt die PIN-Anmeldung.'}
                        </AlertDescription>
                    </Alert>
                )}
                <Card>
                    <CardContent className="overflow-x-auto pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Rolle</TableHead>
                                    <TableHead>PIN</TableHead>
                                    <TableHead>Geändert</TableHead>
                                    <TableHead className="sr-only">Aktionen</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {Object.entries(rollen).map(([key, r]) => (
                                    <TableRow key={key} className="align-top">
                                        <TableCell className="font-medium">{r.label}</TableCell>
                                        <TableCell>
                                            {offen === key ? (
                                                <PinSetzen rolle={key} onDone={() => setOffen(null)} />
                                            ) : r.set ? (
                                                <Badge variant="secondary">gesetzt</Badge>
                                            ) : (
                                                <Badge variant="outline">nicht gesetzt – keine Anmeldung</Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {r.updated_at ? formatDateTime(r.updated_at) : '–'}
                                        </TableCell>
                                        <TableCell>
                                            {offen !== key && (
                                                <span className="flex justify-end gap-1">
                                                    <Button size="sm" variant="outline" onClick={() => setOffen(key)}>
                                                        <KeyRound /> {r.set ? 'Ändern' : 'Setzen'}
                                                    </Button>
                                                    {r.set && (
                                                        <DeleteButton
                                                            url={`/einstellungen/zugang/${key}`}
                                                            title={`PIN «${r.label}» entfernen?`}
                                                            size="icon"
                                                        />
                                                    )}
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AccessSettings.layout = {
    breadcrumbs: [{ title: 'Zugang (PIN)', href: '/einstellungen/zugang' }],
};
