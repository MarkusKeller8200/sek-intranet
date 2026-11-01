import { useRef } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

/**
 * Rückfrage (Alert Dialog): muss bewusst beantwortet werden – schliesst nicht mit Klick daneben,
 * der Fokus liegt zuerst auf «Abbrechen». Für Löschen und andere unumkehrbare Schritte.
 * GUI-Prototyp «Rückfrage».
 */
function AlertDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Bestätigen',
    cancelLabel = 'Abbrechen',
    destructive = false,
    onConfirm,
    children,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    destructive?: boolean;
    onConfirm: () => void;
    children?: ReactNode;
}) {
    const cancel = useRef<HTMLButtonElement>(null);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                role="alertdialog"
                onInteractOutside={(e: Event) => e.preventDefault()}
                onOpenAutoFocus={(e: Event) => {
                    e.preventDefault();
                    cancel.current?.focus();
                }}
                className="sm:max-w-md [&>button:last-child]:hidden"
            >
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                {children}
                <DialogFooter>
                    <Button ref={cancel} variant="outline" onClick={() => onOpenChange(false)}>
                        {cancelLabel}
                    </Button>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={() => {
                            onConfirm();
                            onOpenChange(false);
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export { AlertDialog };
