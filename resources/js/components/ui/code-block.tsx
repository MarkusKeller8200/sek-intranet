import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Codeblock: Code, Konsolenausgabe oder Vergleich (+/−) mit Kopfzeile, Zeilennummern und Kopieren.
 * GUI-Prototyp «Codeblock».
 */
function CodeBlock({
    code,
    title,
    language,
    lineNumbers = false,
    diff = false,
    className,
}: {
    code: string;
    title?: string;
    language?: string;
    lineNumbers?: boolean;
    diff?: boolean;
    className?: string;
}) {
    const [copied, setCopied] = useState(false);
    const lines = code.replace(/\n$/, '').split('\n');
    const copy = async () => {
        const text = diff ? lines.filter((l) => !l.startsWith('-')).map((l) => l.replace(/^[+ ]/, '')).join('\n') : code;
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            setCopied(false);
        }
    };

    return (
        <figure className={cn('overflow-hidden rounded-lg border bg-muted/40 text-sm', className)}>
            <figcaption className="flex items-center justify-between gap-2 border-b bg-muted/60 px-3 py-1.5 text-xs text-muted-foreground">
                <span className="truncate" translate="no">
                    {title ?? language ?? 'Code'}
                </span>
                <button type="button" onClick={() => void copy()} className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 hover:bg-accent" aria-live="polite">
                    {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
                    <span>{copied ? 'Kopiert' : 'Kopieren'}</span>
                </button>
            </figcaption>
            <pre className="overflow-x-auto p-3 font-mono text-xs leading-5" translate="no">
                <code>
                    {lines.map((line, i) => {
                        const kind = diff ? (line.startsWith('+') ? 'add' : line.startsWith('-') ? 'del' : null) : null;

                        return (
                            <span
                                key={i}
                                className={cn('block', kind === 'add' && 'bg-success-soft text-success', kind === 'del' && 'bg-destructive/10 text-destructive-foreground')}
                            >
                                {lineNumbers && <span className="mr-3 inline-block w-6 text-right text-muted-foreground select-none">{i + 1}</span>}
                                {line || ' '}
                            </span>
                        );
                    })}
                </code>
            </pre>
        </figure>
    );
}

export { CodeBlock };
