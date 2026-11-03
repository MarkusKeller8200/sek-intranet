import { cn } from '@/lib/utils';

/** Vermerk unter dem Logo: wer das Intranet entwickelt (Freizeitprojekt für die Sek Bülach). */
export default function AppCredit({ className }: { className?: string }) {
    return (
        <p className={cn('text-right text-[11px] leading-snug text-muted-foreground', className)}>
            <span className="block">
                Entwickelt von{' '}
                <span translate="no" className="whitespace-nowrap font-medium">
                    Markus Keller
                </span>
            </span>
            <span className="block">in der Freizeit für die Sek Bülach</span>
        </p>
    );
}
