import { usePage } from '@inertiajs/react';
import { RefreshCw, Sparkles } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Remote = {
    version: string;
    build: string | null;
    changes: { title: string; items: string[] }[];
};

const POLL_MS = 60_000;
const COUNTDOWN_S = 30;
const SNOOZE_MS = 10 * 60_000;
const SEEN_KEY = 'intranet.version.seen';

const keyOf = (version: string, build: string | null) =>
    `${version}+${build ?? ''}`;

function readSeen(): string | null {
    try {
        return localStorage.getItem(SEEN_KEY);
    } catch {
        return null;
    }
}

function writeSeen(value: string) {
    try {
        localStorage.setItem(SEEN_KEY, value);
    } catch {
        // ohne Speicher keine Meldung nach dem Neuladen
    }
}

/** Tippt die Person gerade etwas? Dann nicht automatisch neu laden. */
function isBusy(): boolean {
    const el = document.activeElement;
    if (!el) {
        return false;
    }

    return (
        el instanceof HTMLInputElement ||
        el instanceof HTMLTextAreaElement ||
        el instanceof HTMLSelectElement ||
        (el instanceof HTMLElement && el.isContentEditable)
    );
}

async function fetchRemote(): Promise<Remote | null> {
    try {
        const response = await fetch('/version.json', {
            cache: 'no-store',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            return null;
        }

        return (await response.json()) as Remote;
    } catch {
        // Während des Neustarts nach dem Deployment nicht erreichbar
        return null;
    }
}

function Changes({ changes }: { changes: Remote['changes'] }) {
    if (changes.length === 0) {
        return null;
    }

    return (
        <div
            className="grid max-h-64 gap-3 overflow-auto rounded-md border p-3 text-sm"
            translate="no"
        >
            {changes.map((section) => (
                <div key={section.title}>
                    <div className="mb-1 font-semibold">{section.title}</div>
                    <ul className="grid list-disc gap-0.5 pl-5">
                        {section.items.map((item) => (
                            <li key={item}>{item.replace(/\*\*|`/g, '')}</li>
                        ))}
                    </ul>
                </div>
            ))}
        </div>
    );
}

/**
 * Erkennt ein Deployment im Hintergrund (Abfrage /version.json), lädt die Seite neu und
 * meldet danach, auf welche Version aktualisiert wurde.
 */
export default function UpdateWatcher() {
    const { appVersion } = usePage().props;
    const loaded = keyOf(appVersion.version, appVersion.build);
    const [pending, setPending] = useState<Remote | null>(null);
    const [seconds, setSeconds] = useState(COUNTDOWN_S);
    const [updated, setUpdated] = useState<Remote | null>(null);
    const snoozedUntil = useRef(0);

    const reload = useCallback(() => {
        window.location.reload();
    }, []);

    // Nach dem Laden: hat sich die Version seit dem letzten Besuch geändert?
    useEffect(() => {
        const seen = readSeen();
        writeSeen(loaded);
        if (seen === null || seen === loaded) {
            return;
        }
        const seenVersion = seen.split('+')[0];
        if (seenVersion !== appVersion.version) {
            void fetchRemote().then((remote) =>
                setUpdated(
                    remote ?? {
                        version: appVersion.version,
                        build: appVersion.build,
                        changes: [],
                    },
                ),
            );
        } else {
            toast.success('Das Intranet wurde aktualisiert.');
        }
    }, [loaded, appVersion.version, appVersion.build]);

    // Regelmässig und beim Zurückkehren in den Tab prüfen
    useEffect(() => {
        let stopped = false;
        const check = async () => {
            if (Date.now() < snoozedUntil.current) {
                return;
            }
            const remote = await fetchRemote();
            if (stopped || !remote || !remote.version) {
                return;
            }
            if (keyOf(remote.version, remote.build) === loaded) {
                return;
            }
            // Tab im Hintergrund: still neu laden, die Meldung kommt danach
            if (document.hidden) {
                reload();

                return;
            }
            setPending(remote);
        };
        const onVisible = () => {
            if (!document.hidden) {
                void check();
            }
        };
        const timer = window.setInterval(() => void check(), POLL_MS);
        document.addEventListener('visibilitychange', onVisible);
        window.addEventListener('focus', onVisible);

        return () => {
            stopped = true;
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
            window.removeEventListener('focus', onVisible);
        };
    }, [loaded, reload]);

    // Countdown bis zum automatischen Neuladen (pausiert, solange jemand tippt)
    useEffect(() => {
        if (!pending) {
            return;
        }
        setSeconds(COUNTDOWN_S);
        const timer = window.setInterval(() => {
            setSeconds((s) => {
                if (isBusy()) {
                    return s;
                }
                if (s <= 1) {
                    reload();

                    return 0;
                }

                return s - 1;
            });
        }, 1000);

        return () => window.clearInterval(timer);
    }, [pending, reload]);

    return (
        <>
            <Dialog
                open={pending !== null}
                onOpenChange={(open: boolean) => {
                    if (!open) {
                        snoozedUntil.current = Date.now() + SNOOZE_MS;
                        setPending(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <RefreshCw className="size-5 text-brand" />
                            Neue Version verfügbar
                        </DialogTitle>
                        <DialogDescription>
                            {`Das Intranet wurde auf Version ${pending?.version ?? ''} aktualisiert. Die Seite wird in ${seconds} Sekunden neu geladen.`}
                        </DialogDescription>
                    </DialogHeader>
                    {pending && <Changes changes={pending.changes} />}
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                snoozedUntil.current = Date.now() + SNOOZE_MS;
                                setPending(null);
                            }}
                        >
                            Später
                        </Button>
                        <Button onClick={reload}>
                            <RefreshCw /> Jetzt neu laden
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={updated !== null}
                onOpenChange={(open: boolean) => !open && setUpdated(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Sparkles className="size-5 text-brand" />
                            {`Aktualisiert auf Version ${updated?.version ?? ''}`}
                        </DialogTitle>
                        <DialogDescription>
                            Das ist neu im Intranet:
                        </DialogDescription>
                    </DialogHeader>
                    {updated && <Changes changes={updated.changes} />}
                    <DialogFooter>
                        <Button variant="outline" asChild>
                            <a href="/versionen">Alle Versionen</a>
                        </Button>
                        <Button onClick={() => setUpdated(null)}>
                            Verstanden
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
