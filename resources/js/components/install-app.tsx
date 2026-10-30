import { Download, Share, SquarePlus } from 'lucide-react';
import { useState, useSyncExternalStore } from 'react';
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
import { useSidebar } from '@/components/ui/sidebar';
import { installState, promptInstall, subscribeInstall } from '@/lib/pwa';
import type { InstallState } from '@/lib/pwa';
import { cn } from '@/lib/utils';

export function useInstallState(): InstallState {
    return useSyncExternalStore(subscribeInstall, installState, (): InstallState => 'unsupported');
}

/** Anleitung für iPhone/iPad (Safari kennt keinen Installationsdialog). */
function IosHint({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Intranet als App installieren</DialogTitle>
                    <DialogDescription>So geht es auf iPhone und iPad (Safari):</DialogDescription>
                </DialogHeader>
                <ol className="grid list-decimal gap-3 pl-5 text-sm">
                    <li>
                        Unten bzw. oben auf <Share className="inline size-4 align-text-bottom" /> «Teilen» tippen.
                    </li>
                    <li>
                        <SquarePlus className="inline size-4 align-text-bottom" /> «Zum Home-Bildschirm» wählen.
                    </li>
                    <li>Mit «Hinzufügen» bestätigen. Das Intranet startet danach wie eine App.</li>
                </ol>
                <DialogFooter>
                    <Button onClick={() => onOpenChange(false)}>Verstanden</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Knopf «App installieren» – nur sichtbar, wenn der Browser es kann und die App noch nicht installiert ist. */
export function InstallAppButton({ variant = 'sidebar' }: { variant?: 'sidebar' | 'settings' }) {
    const state = useInstallState();
    const [iosOpen, setIosOpen] = useState(false);
    const { isMobile, setOpenMobile } = useSidebar();

    if (variant === 'settings') {
        return (
            <div className="grid gap-2 text-sm">
                {state === 'installed' && <p>Das Intranet ist auf diesem Gerät als App installiert.</p>}
                {state === 'unsupported' && (
                    <p className="text-muted-foreground">
                        Dieser Browser bietet keine Installation an. In Chrome, Edge oder Safari (iPhone/iPad) ist es
                        möglich; im Browsermenü «App installieren» bzw. «Zum Home-Bildschirm».
                    </p>
                )}
                {(state === 'available' || state === 'ios') && (
                    <div>
                        <Button
                            onClick={() =>
                                state === 'ios'
                                    ? setIosOpen(true)
                                    : void promptInstall().then((ok) => ok && toast.success('Intranet wird installiert.'))
                            }
                        >
                            <Download /> App installieren
                        </Button>
                    </div>
                )}
                <IosHint open={iosOpen} onOpenChange={setIosOpen} />
            </div>
        );
    }

    if (state !== 'available' && state !== 'ios') {
        return null;
    }

    return (
        <>
            <button
                type="button"
                onClick={() => {
                    if (state === 'ios') {
                        if (isMobile) {
                            setOpenMobile(false);
                        }
                        setIosOpen(true);
                    } else {
                        void promptInstall().then((ok) => ok && toast.success('Intranet wird installiert.'));
                    }
                }}
                className={cn(
                    'flex items-center gap-2 rounded-md border border-sidebar-border px-3 py-2 text-xs font-medium text-sidebar-foreground hover:bg-sidebar-accent group-data-[collapsible=icon]:hidden',
                )}
                title="Intranet als App auf diesem Gerät installieren"
            >
                <Download className="size-3.5 shrink-0" />
                App installieren
            </button>
            <IosHint open={iosOpen} onOpenChange={setIosOpen} />
        </>
    );
}
