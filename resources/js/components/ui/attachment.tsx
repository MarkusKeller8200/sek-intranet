import { File as FileIcon, FileText, RotateCcw, X } from 'lucide-react';
import { fileSize } from '@/components/ui/file-drop';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

export type AttachmentState = 'uploading' | 'done' | 'error';

/**
 * Anhang: Datei mit Vorschaubild (Bilder), Name, Grösse, Upload-Fortschritt und Aktionen.
 * GUI-Prototyp «Datei hochladen».
 */
function Attachment({
    name,
    size,
    type,
    preview,
    state = 'done',
    progress,
    onRemove,
    onRetry,
}: {
    name: string;
    size: number;
    type: string;
    preview?: string;
    state?: AttachmentState;
    progress?: number;
    onRemove?: () => void;
    onRetry?: () => void;
}) {
    const Icon = type === 'application/pdf' ? FileText : FileIcon;

    return (
        <div className={cn('flex items-center gap-3 rounded-lg border p-2 text-sm', state === 'error' && 'border-destructive/50')}>
            <div className="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted">
                {preview ? <img src={preview} alt="" className="size-full object-cover" /> : <Icon className="size-5 text-muted-foreground" aria-hidden />}
            </div>
            <div className="grid min-w-0 flex-1 gap-1">
                <span className="truncate font-medium" translate="no">
                    {name}
                </span>
                {state === 'uploading' ? (
                    <Progress label={`${fileSize(size)} · wird hochgeladen`} value={progress ?? null} className="text-xs" />
                ) : (
                    <span className={cn('text-xs', state === 'error' ? 'text-destructive-foreground' : 'text-muted-foreground')}>
                        {state === 'error' ? 'Hochladen fehlgeschlagen' : fileSize(size)}
                    </span>
                )}
            </div>
            {state === 'error' && onRetry && (
                <button type="button" onClick={onRetry} title="Nochmals" className="rounded p-1 hover:bg-accent">
                    <RotateCcw className="size-4" />
                    <span className="sr-only">Nochmals</span>
                </button>
            )}
            {onRemove && (
                <button type="button" onClick={onRemove} title="Entfernen" className="rounded p-1 hover:bg-accent">
                    <X className="size-4" />
                    <span className="sr-only">Entfernen</span>
                </button>
            )}
        </div>
    );
}

export { Attachment };
