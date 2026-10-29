import { cn } from '@/lib/utils';

/** Offizieller Schriftzug «Sekundarschule Bülach» mit Ringen, hell und dunkel. */
export default function AppWordmark({ className }: { className?: string }) {
    return (
        <>
            <img
                src="/images/sekbuelach-logo.svg"
                alt="Sekundarschule Bülach"
                className={cn('h-auto dark:hidden', className)}
            />
            <img
                src="/images/sekbuelach-logo-dark.svg"
                alt="Sekundarschule Bülach"
                className={cn('hidden h-auto dark:block', className)}
            />
        </>
    );
}
