import { cn } from '@/lib/utils';

/** Vermerk unter dem Logo: wer das Intranet entwickelt (in Eigeninitiative für die SekBülach). */
export default function AppCredit({ className }: { className?: string }) {
    return (
        <p className={cn('text-right leading-snug', className)}>
            <span className="block text-xs font-semibold text-foreground" translate="no">
                Developed by Markus Keller ®
            </span>
            <span className="block text-[11px] text-muted-foreground">In Eigeninitiative für die SekBülach</span>
        </p>
    );
}
