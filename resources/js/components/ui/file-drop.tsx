import { FileUp, X } from 'lucide-react';
import { useId, useRef, useState } from 'react';
import type { DragEvent } from 'react';
import { cn } from '@/lib/utils';

/** Dateigrösse lesbar (kB/MB, dezimal). */
export function fileSize(bytes: number): string {
    return bytes >= 1_000_000 ? `${(bytes / 1_000_000).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1000))} kB`;
}

/**
 * Ablagefläche für Dateien: hineinziehen oder anklicken (auch mit Tastatur: Tab, dann Enter/Leertaste).
 * Dateien bleiben im Browser, bis die Seite sie bewusst sendet. GUI-Prototyp «Datei hochladen».
 */
function FileDrop({
    accept,
    multiple = false,
    maxBytes,
    files,
    onFiles,
    hint,
}: {
    accept?: string;
    multiple?: boolean;
    maxBytes?: number;
    files: File[];
    onFiles: (files: File[]) => void;
    hint?: string;
}) {
    const input = useRef<HTMLInputElement>(null);
    const id = useId();
    const [over, setOver] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const take = (list: FileList | null) => {
        const picked = Array.from(list ?? []);
        const types = (accept ?? '').split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);
        const okType = (f: File) => types.length === 0 || types.some((t) => (t.startsWith('.') ? f.name.toLowerCase().endsWith(t) : f.type === t));
        const bad = picked.find((f) => !okType(f) || (maxBytes !== undefined && f.size > maxBytes));
        if (bad) {
            setError(!okType(bad) ? `«${bad.name}»: Dateityp nicht erlaubt.` : `«${bad.name}» ist zu gross.`);

            return;
        }
        setError(null);
        onFiles(multiple ? [...files, ...picked] : picked.slice(0, 1));
    };
    const onDrop = (e: DragEvent) => {
        e.preventDefault();
        setOver(false);
        take(e.dataTransfer.files);
    };

    return (
        <div className="grid gap-2">
            <label
                htmlFor={id}
                onDragOver={(e) => {
                    e.preventDefault();
                    setOver(true);
                }}
                onDragLeave={() => setOver(false)}
                onDrop={onDrop}
                className={cn(
                    'flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed p-6 text-center text-sm transition-colors focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-accent/50',
                    over ? 'border-primary bg-primary/5' : 'border-input',
                    error && 'border-destructive',
                )}
            >
                <FileUp className="size-6 text-muted-foreground" aria-hidden />
                <span className="font-medium">{multiple ? 'Dateien hierher ziehen oder klicken' : 'Datei hierher ziehen oder klicken'}</span>
                {hint && <span className="text-xs text-muted-foreground">{hint}</span>}
                <input
                    ref={input}
                    id={id}
                    type="file"
                    accept={accept}
                    multiple={multiple}
                    className="sr-only"
                    onChange={(e) => {
                        take(e.target.files);
                        e.target.value = '';
                    }}
                />
            </label>
            {error && (
                <p className="text-sm text-destructive-foreground" role="alert">
                    {error}
                </p>
            )}
            {files.length > 0 && (
                <ul className="grid gap-1 text-sm">
                    {files.map((f, i) => (
                        <li key={`${f.name}-${i}`} className="flex items-center justify-between gap-2 rounded-md border px-3 py-1.5">
                            <span className="truncate" translate="no">
                                {f.name}
                            </span>
                            <span className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground tabular-nums">
                                {fileSize(f.size)}
                                <button type="button" onClick={() => onFiles(files.filter((_, j) => j !== i))} title="Entfernen" className="rounded p-0.5 hover:bg-accent">
                                    <X className="size-3.5" />
                                    <span className="sr-only">Entfernen</span>
                                </button>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export { FileDrop };
