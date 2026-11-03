import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, LogIn, ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import OptionBadge from '@/components/option-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';

type DemoUser = {
    id: string;
    name: string;
    function: string | null;
    role: string;
};

type PinRole = { label: string; set: boolean };

/** Rolle wählen und 8-stelligen PIN eingeben (#69). */
function PinForm({ roles }: { roles: Record<string, PinRole> }) {
    const form = useForm({ role: '', pin: '' });
    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/anmelden/pin', {
            preserveScroll: true,
            onFinish: () => form.setData('pin', ''),
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-4">
            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-medium">Rolle</legend>
                <div className="grid grid-cols-2 gap-2">
                    {Object.entries(roles).map(([key, r]) => (
                        <Button
                            key={key}
                            type="button"
                            variant={form.data.role === key ? 'default' : 'outline'}
                            disabled={!r.set}
                            aria-pressed={form.data.role === key}
                            title={r.set ? undefined : 'Für diese Rolle ist noch kein PIN gesetzt'}
                            onClick={() => {
                                form.setData('role', key);
                                form.clearErrors();
                            }}
                        >
                            {r.label}
                        </Button>
                    ))}
                </div>
            </fieldset>
            {form.data.role !== '' && (
                <div className="grid gap-2">
                    <Label htmlFor="pin">PIN (8 Ziffern)</Label>
                    <Input
                        id="pin"
                        type="password"
                        inputMode="numeric"
                        autoComplete="off"
                        maxLength={8}
                        pattern="\d{8}"
                        autoFocus
                        value={form.data.pin}
                        onChange={(e) =>
                            form.setData('pin', e.target.value.replace(/\D/g, ''))
                        }
                    />
                    <InputError message={form.errors.pin ?? form.errors.role} />
                    <Button
                        type="submit"
                        disabled={form.processing || form.data.pin.length !== 8}
                    >
                        <KeyRound /> Weiter
                    </Button>
                </div>
            )}
        </form>
    );
}

export default function Login({
    demoLogin,
    pinMode = false,
    roles = {},
    role = null,
    users,
}: {
    demoLogin: boolean;
    pinMode?: boolean;
    roles?: Record<string, PinRole>;
    role?: string | null;
    users: DemoUser[];
}) {
    const getInitials = useInitials();
    const form = useForm({ user_id: '' });
    const [wechseln, setWechseln] = useState(false);

    const signIn = (id: string) => {
        form.transform(() => ({ user_id: id }));
        form.post('/anmelden');
    };

    if (!demoLogin) {
        return (
            <>
                <Head title="Anmelden" />
                <Alert>
                    <ShieldAlert />
                    <AlertTitle>Anmeldung nicht eingerichtet</AlertTitle>
                    <AlertDescription>
                        Die Demo-Anmeldung ist ausgeschaltet. Die Anmeldung mit
                        dem Schulkonto (Entra ID / Edulog) folgt.
                    </AlertDescription>
                </Alert>
            </>
        );
    }

    if (pinMode && role === null) {
        return (
            <>
                <Head title="Anmelden" />
                <PinForm roles={roles} />
                <p className="pt-4 text-center text-xs text-muted-foreground">
                    Prototyp mit erfundenen Daten. Später Anmeldung mit dem
                    Schulkonto.
                </p>
            </>
        );
    }

    return (
        <>
            <Head title="Anmelden" />
            <div className="grid gap-2">
                {pinMode && role !== null && (
                    <div className="mb-2 flex items-center justify-between gap-2 text-sm">
                        <span>
                            Rolle:{' '}
                            <span className="font-medium">{roles[role]?.label ?? role}</span>
                        </span>
                        <Button
                            variant="ghost"
                            size="sm"
                            disabled={wechseln}
                            onClick={() => {
                                setWechseln(true);
                                router.post('/anmelden/rolle-wechseln', {}, { onFinish: () => setWechseln(false) });
                            }}
                        >
                            Andere Rolle
                        </Button>
                    </div>
                )}
                {users.map((user) => (
                    <Button
                        key={user.id}
                        variant="outline"
                        className="h-auto justify-start gap-3 py-2.5 text-left"
                        disabled={form.processing}
                        onClick={() => signIn(user.id)}
                    >
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-info-soft text-xs font-semibold text-primary">
                            {getInitials(user.name)}
                        </span>
                        <span className="grid flex-1 leading-tight">
                            <span className="font-medium">{user.name}</span>
                            <span className="truncate text-xs font-normal text-muted-foreground">
                                {user.function}
                            </span>
                        </span>
                        <OptionBadge list="roles" value={user.role} />
                        <LogIn className="text-muted-foreground" />
                    </Button>
                ))}
                {users.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Für diese Rolle gibt es noch keine Demo-Person.
                    </p>
                )}
                <InputError message={form.errors.user_id} />
                <p className="pt-2 text-center text-xs text-muted-foreground">
                    Prototyp: Demo-Personen mit erfundenen Daten. Später
                    Anmeldung mit dem Schulkonto.
                </p>
            </div>
        </>
    );
}

Login.layout = {
    title: 'SekBülach Intranet',
    description: 'Mit welcher Rolle möchtest du den Prototyp testen?',
};
