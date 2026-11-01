import { Link, router, usePage } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { NotificationSummary } from '@/types/auth';

/** Wie oft die Glocke nachfragt (Millisekunden). */
const POLL = 45_000;

/**
 * Glocke oben rechts: ungelesene Benachrichtigungen, neueste Einträge, «Alle anzeigen».
 * Fragt regelmässig nach; neue Meldungen erscheinen zusätzlich als kurze Meldung (Toast).
 */
export default function NotificationBell() {
    const initial = usePage<{ auth: { notifications?: NotificationSummary | null } }>().props.auth.notifications;
    const [state, setState] = useState<NotificationSummary | null>(initial ?? null);
    const seen = useRef<Set<string>>(new Set((initial?.latest ?? []).map((n) => n.id)));

    useEffect(() => setState(initial ?? null), [initial]);

    useEffect(() => {
        if (!initial) {
            return;
        }
        const poll = async () => {
            if (document.visibilityState !== 'visible') {
                return;
            }
            try {
                const res = await fetch('/benachrichtigungen/stand', {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) {
                    return;
                }
                const next = (await res.json()) as NotificationSummary;
                next.latest
                    .filter((n) => n.read_at === null && !seen.current.has(n.id))
                    .forEach((n) =>
                        toast.info(n.title, {
                            description: n.body,
                            action: { label: 'Öffnen', onClick: () => router.visit(`/benachrichtigungen/${n.id}`) },
                        }),
                    );
                next.latest.forEach((n) => seen.current.add(n.id));
                setState(next);
            } catch {
                // offline – beim nächsten Mal
            }
        };
        const timer = window.setInterval(() => void poll(), POLL);

        return () => window.clearInterval(timer);
    }, [initial]);

    if (!state) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" title="Benachrichtigungen" data-testid="notification-bell">
                    <Bell />
                    {state.unread > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-primary px-1 text-[10px] leading-4 font-semibold text-primary-foreground tabular-nums">
                            {state.unread > 9 ? '9+' : state.unread}
                        </span>
                    )}
                    <span className="sr-only">
                        {state.unread === 1 ? '1 ungelesene Benachrichtigung' : `${state.unread} ungelesene Benachrichtigungen`}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Benachrichtigungen</span>
                    {state.unread > 0 && (
                        <button
                            type="button"
                            className="inline-flex items-center gap-1 text-xs font-normal text-primary hover:underline"
                            onClick={() => router.post('/benachrichtigungen/gelesen', {}, { preserveScroll: true })}
                        >
                            <CheckCheck className="size-3.5" /> Alle gelesen
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {state.latest.length === 0 && <p className="px-2 py-4 text-center text-sm text-muted-foreground">Keine Benachrichtigungen.</p>}
                {state.latest.map((n) => (
                    <DropdownMenuItem key={n.id} asChild>
                        <Link href={`/benachrichtigungen/${n.id}`} className="grid gap-0.5">
                            <span className={cn('flex items-start gap-2 text-sm', n.read_at === null && 'font-semibold')}>
                                {n.read_at === null && <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" aria-hidden />}
                                <span>{n.title}</span>
                            </span>
                            {n.body && <span className="line-clamp-2 text-xs text-muted-foreground">{n.body}</span>}
                            <span className="text-[11px] text-muted-foreground tabular-nums">{formatDateTime(n.sent_at)}</span>
                        </Link>
                    </DropdownMenuItem>
                ))}
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href="/benachrichtigungen" className="justify-center text-sm">
                        Alle anzeigen
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
