import { cn } from '@/lib/utils';

/** Vermerk unter dem Logo: wer das Intranet entwickelt (in Eigeninitiative für die SekBülach). */
export default function AppCredit({ className }: { className?: string }) {
    return (
        <p className={cn('text-right text-[10px] leading-tight', className)}>
            <span
                className="block font-semibold text-foreground"
                translate="no"
            >
                © 2026 developed by Markus Keller
            </span>
            <span className="block text-muted-foreground">
                in Eigeninitiative für die SekBülach
            </span>
        </p>
    );
}
