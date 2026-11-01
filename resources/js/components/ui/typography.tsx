import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Typografie: einheitliche Überschriften, Absätze, Listen, Zitate und Code im Text.
 * `Prose` gestaltet ganze Texte (z. B. Mitteilungen, Handbuch). GUI-Prototyp «Typografie».
 */
type P = { children: ReactNode; className?: string };

export const H1 = ({ children, className }: P) => <h1 className={cn('text-2xl font-semibold tracking-tight md:text-3xl', className)}>{children}</h1>;
export const H2 = ({ children, className }: P) => <h2 className={cn('border-b pb-1 text-xl font-semibold tracking-tight', className)}>{children}</h2>;
export const H3 = ({ children, className }: P) => <h3 className={cn('text-lg font-semibold', className)}>{children}</h3>;
export const H4 = ({ children, className }: P) => <h4 className={cn('text-base font-semibold', className)}>{children}</h4>;
export const Lead = ({ children, className }: P) => <p className={cn('text-lg text-muted-foreground', className)}>{children}</p>;
export const Text = ({ children, className }: P) => <p className={cn('leading-7', className)}>{children}</p>;
export const Muted = ({ children, className }: P) => <p className={cn('text-sm text-muted-foreground', className)}>{children}</p>;
export const Small = ({ children, className }: P) => <small className={cn('text-xs font-medium', className)}>{children}</small>;
export const InlineCode = ({ children, className }: P) => (
    <code className={cn('rounded bg-muted px-1.5 py-0.5 font-mono text-[0.85em]', className)} translate="no">
        {children}
    </code>
);
export const Blockquote = ({ children, className }: P) => (
    <blockquote className={cn('border-l-4 border-brand/40 pl-4 text-muted-foreground italic', className)}>{children}</blockquote>
);
export const List = ({ children, className, ordered = false }: P & { ordered?: boolean }) =>
    ordered ? (
        <ol className={cn('ml-6 list-decimal space-y-1', className)}>{children}</ol>
    ) : (
        <ul className={cn('ml-6 list-disc space-y-1', className)}>{children}</ul>
    );

/** Ganzer Text mit Standardabständen (für HTML aus Mitteilungen, Handbuch usw.). */
export const Prose = ({ children, className }: P) => (
    <div
        className={cn(
            'max-w-prose space-y-4 leading-7 [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-brand/40 [&_blockquote]:pl-4 [&_blockquote]:italic [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_h2]:mt-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:text-lg [&_h3]:font-semibold [&_ol]:ml-6 [&_ol]:list-decimal [&_ul]:ml-6 [&_ul]:list-disc',
            className,
        )}
    >
        {children}
    </div>
);
