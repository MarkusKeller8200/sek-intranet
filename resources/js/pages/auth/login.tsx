import { Head, useForm } from '@inertiajs/react';
import { LogIn, ShieldAlert } from 'lucide-react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/hooks/use-initials';

type DemoUser = {
    id: string;
    name: string;
    function: string | null;
    role: string;
};

const roleLabels: Record<string, string> = {
    admin: 'Administration',
    redaktion: 'Redaktion',
    mitarbeitende: 'Mitarbeitende',
};

export default function Login({
    demoLogin,
    users,
}: {
    demoLogin: boolean;
    users: DemoUser[];
}) {
    const getInitials = useInitials();
    const form = useForm({ user_id: '' });

    const signIn = (id: string) => {
        form.transform(() => ({ user_id: id }));
        form.post('/anmelden');
    };

    return (
        <>
            <Head title="Anmelden" />

            {!demoLogin ? (
                <Alert>
                    <ShieldAlert />
                    <AlertTitle>Anmeldung nicht eingerichtet</AlertTitle>
                    <AlertDescription>
                        Die Demo-Anmeldung ist ausgeschaltet. Die Anmeldung mit
                        dem Schulkonto (Entra ID / Edulog) folgt.
                    </AlertDescription>
                </Alert>
            ) : (
                <div className="grid gap-2">
                    {users.map((user) => (
                        <Button
                            key={user.id}
                            variant="outline"
                            className="h-auto justify-start gap-3 py-2.5 text-left"
                            disabled={form.processing}
                            onClick={() => signIn(user.id)}
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                {getInitials(user.name)}
                            </span>
                            <span className="grid flex-1 leading-tight">
                                <span className="font-medium">{user.name}</span>
                                <span className="truncate text-xs font-normal text-muted-foreground">
                                    {user.function}
                                </span>
                            </span>
                            <Badge variant="secondary">
                                {roleLabels[user.role] ?? user.role}
                            </Badge>
                            <LogIn className="text-muted-foreground" />
                        </Button>
                    ))}
                    <InputError message={form.errors.user_id} />
                    <p className="pt-2 text-center text-xs text-muted-foreground">
                        Prototyp: Demo-Personen mit erfundenen Daten. Später
                        Anmeldung mit dem Schulkonto.
                    </p>
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'SekBülach Intranet',
    description: 'Als welche Person möchtest du den Prototyp testen?',
};
