import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

/** Löschen immer erst nach Bestätigung. */
export default function DeleteButton({
    url,
    title,
    description,
    size = 'sm',
}: {
    url: string;
    title: string;
    description?: string;
    size?: 'sm' | 'default' | 'icon';
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="outline" size={size} aria-label="Löschen">
                    <Trash2 />
                    {size !== 'icon' && 'Löschen'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {description ??
                            'Der Eintrag wird endgültig gelöscht. Dies kann nicht rückgängig gemacht werden.'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Abbrechen</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={processing}
                        onClick={() =>
                            router.delete(url, {
                                onStart: () => setProcessing(true),
                                onFinish: () => setProcessing(false),
                            })
                        }
                    >
                        Endgültig löschen
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
