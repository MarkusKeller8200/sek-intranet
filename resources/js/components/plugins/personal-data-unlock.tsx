import { router, useForm } from '@inertiajs/react';
import { LockKeyhole, LockKeyholeOpen } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type PersonalData = {
    endpoints: number;
    until: string | null;
    minutes: number;
    canUnlock: boolean;
};

/**
 * Personendaten-Endpunkte eines Plugins auf einem Server ausserhalb der Schweiz: gesperrt, für einen Test mit dem
 * Admin-PIN befristet freischaltbar (nur Administration). Der PIN wird nur geprüft, nie gespeichert.
 */
export default function PersonalDataUnlock({
    pluginId,
    data,
}: {
    pluginId: string;
    data: PersonalData;
}) {
    const form = useForm({ pin: '' });
    const until = data.until
        ? new Date(data.until).toLocaleTimeString('de-CH', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : null;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/plugins/${pluginId}/personendaten`, {
            preserveScroll: true,
            onFinish: () => form.reset('pin'),
        });
    };

    if (until) {
        return (
            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm">
                <LockKeyholeOpen className="size-4 shrink-0 text-destructive" />
                <p className="min-w-0 flex-1">
                    <strong>Personendaten freigeschaltet bis {until}</strong>{' '}
                    – {data.endpoints} Endpunkte mit Personendaten gehen
                    echt an den Dienst. Nur für den Test, danach sperren.
                </p>
                {data.canUnlock && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.delete(`/plugins/${pluginId}/personendaten`, {
                                preserveScroll: true,
                            })
                        }
                    >
                        <LockKeyhole /> Wieder sperren
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="grid gap-2 rounded-lg border p-3 text-sm">
            <p className="flex items-start gap-2">
                <LockKeyhole className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <span>
                    {data.endpoints} Endpunkte liefern Personendaten und sind
                    auf diesem Server gesperrt (auch im Endtest).
                    {data.canUnlock &&
                        ` Für einen Test mit dem Admin-PIN für ${data.minutes} Minuten freischalten (Demo-Modus aus, Endtest ein).`}
                </span>
            </p>
            {data.canUnlock && (
                <form
                    onSubmit={submit}
                    className="flex flex-wrap items-center gap-2"
                >
                    <Input
                        type="password"
                        inputMode="numeric"
                        autoComplete="off"
                        maxLength={8}
                        value={form.data.pin}
                        onChange={(e) => form.setData('pin', e.target.value)}
                        placeholder="Admin-PIN"
                        aria-label="Admin-PIN"
                        className="w-40"
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        size="sm"
                        disabled={form.processing || form.data.pin.length < 8}
                    >
                        <LockKeyholeOpen /> Für Test freischalten
                    </Button>
                    <InputError message={form.errors.pin} />
                </form>
            )}
        </div>
    );
}
