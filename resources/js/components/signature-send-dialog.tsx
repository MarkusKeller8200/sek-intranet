import { useForm } from '@inertiajs/react';
import { FilePlus2, Plus, Send, X } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import FormField from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Person = { name: string; email: string };

type Form = {
    datei: File | null;
    titel: string;
    unterzeichnende: Person[];
    gleichzeitig: boolean;
    beobachtende: string;
    nachricht: string;
};

/**
 * Dokument zur Unterschrift senden (Verwaltung → Unterschriften): PDF wählen, Unterzeichnende in Reihenfolge,
 * optional zur Kenntnis und Nachricht; startet den Signaturprozess beim Anbieter (DeepSign).
 */
export default function SignatureSendDialog({
    disabled,
    demo,
}: {
    disabled: boolean;
    demo: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm<Form>({
        datei: null,
        titel: '',
        unterzeichnende: [{ name: '', email: '' }],
        gleichzeitig: false,
        beobachtende: '',
        nachricht: '',
    });
    const errors = form.errors as Record<string, string | undefined>;
    const setPerson = (i: number, key: keyof Person, value: string) =>
        form.setData(
            'unterzeichnende',
            form.data.unterzeichnende.map((p, j) =>
                j === i ? { ...p, [key]: value } : p,
            ),
        );

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/unterschriften', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setOpen(false);
            },
        });
    };

    return (
        <>
            <Button disabled={disabled} onClick={() => setOpen(true)}>
                <FilePlus2 /> Dokument zur Unterschrift senden
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                Dokument zur Unterschrift senden
                            </DialogTitle>
                            <DialogDescription>
                                {demo
                                    ? 'Demo-Modus: Es geht nichts an DeepSign, der Ablauf lässt sich danach simulieren.'
                                    : 'Das PDF geht an DeepSign, die Unterzeichnenden erhalten eine E-Mail mit dem Link zum Unterschreiben.'}
                            </DialogDescription>
                        </DialogHeader>

                        <FormField
                            id="s-datei"
                            label="Dokument (PDF, höchstens 20 MB) *"
                            error={errors.datei}
                        >
                            <Input
                                id="s-datei"
                                type="file"
                                accept=".pdf,application/pdf"
                                onChange={(e) =>
                                    form.setData(
                                        'datei',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="s-titel"
                            label="Titel"
                            hint="Erscheint in der Einladung und in der Übersicht. Leer = Dateiname."
                            error={errors.titel}
                        >
                            <Input
                                id="s-titel"
                                value={form.data.titel}
                                onChange={(e) =>
                                    form.setData('titel', e.target.value)
                                }
                            />
                        </FormField>

                        <fieldset className="grid gap-2">
                            <legend className="mb-1 text-sm font-medium">
                                Unterzeichnende *
                            </legend>
                            {form.data.unterzeichnende.map((p, i) => (
                                <div
                                    key={i}
                                    className="grid grid-cols-[auto_1fr_1fr_auto] items-start gap-2"
                                >
                                    <span className="pt-2 text-sm text-muted-foreground tabular-nums">
                                        {form.data.gleichzeitig
                                            ? '•'
                                            : `${i + 1}.`}
                                    </span>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label={`Name ${i + 1}`}
                                            placeholder="Name"
                                            value={p.name}
                                            onChange={(e) =>
                                                setPerson(
                                                    i,
                                                    'name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors[
                                            `unterzeichnende.${i}.name`
                                        ] && (
                                            <span className="text-xs text-destructive">
                                                {
                                                    errors[
                                                        `unterzeichnende.${i}.name`
                                                    ]
                                                }
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label={`E-Mail ${i + 1}`}
                                            type="email"
                                            placeholder="E-Mail"
                                            value={p.email}
                                            onChange={(e) =>
                                                setPerson(
                                                    i,
                                                    'email',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors[
                                            `unterzeichnende.${i}.email`
                                        ] && (
                                            <span className="text-xs text-destructive">
                                                {
                                                    errors[
                                                        `unterzeichnende.${i}.email`
                                                    ]
                                                }
                                            </span>
                                        )}
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`Person ${i + 1} entfernen`}
                                        disabled={
                                            form.data.unterzeichnende.length ===
                                            1
                                        }
                                        onClick={() =>
                                            form.setData(
                                                'unterzeichnende',
                                                form.data.unterzeichnende.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            )
                                        }
                                    >
                                        <X />
                                    </Button>
                                </div>
                            ))}
                            {errors.unterzeichnende && (
                                <span className="text-xs text-destructive">
                                    {errors.unterzeichnende}
                                </span>
                            )}
                            <div className="flex flex-wrap items-center gap-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        form.data.unterzeichnende.length >= 20
                                    }
                                    onClick={() =>
                                        form.setData('unterzeichnende', [
                                            ...form.data.unterzeichnende,
                                            { name: '', email: '' },
                                        ])
                                    }
                                >
                                    <Plus /> Person hinzufügen
                                </Button>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="s-gleichzeitig"
                                        checked={form.data.gleichzeitig}
                                        onCheckedChange={(
                                            c: boolean | 'indeterminate',
                                        ) =>
                                            form.setData(
                                                'gleichzeitig',
                                                c === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="s-gleichzeitig">
                                        Alle gleichzeitig (sonst nacheinander
                                        wie aufgelistet)
                                    </Label>
                                </div>
                            </div>
                        </fieldset>

                        <FormField
                            id="s-beobachtende"
                            label="Zur Kenntnis an (optional)"
                            hint="E-Mail-Adressen, mehrere mit Komma. Sieht das Dokument, unterschreibt nicht."
                            error={errors.beobachtende}
                        >
                            <Input
                                id="s-beobachtende"
                                value={form.data.beobachtende}
                                onChange={(e) =>
                                    form.setData('beobachtende', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="s-nachricht"
                            label="Nachricht an die Unterzeichnenden"
                            error={errors.nachricht}
                        >
                            <Textarea
                                id="s-nachricht"
                                rows={2}
                                value={form.data.nachricht}
                                onChange={(e) =>
                                    form.setData('nachricht', e.target.value)
                                }
                            />
                        </FormField>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Abbrechen
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                <Send />{' '}
                                {form.processing
                                    ? 'Sendet …'
                                    : 'Senden und starten'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
