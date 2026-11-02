import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { monatName, verschiebeMonat } from '../lib/types';

/** Monat wechseln (vorheriger / nächster Monat). */
export default function MonatNavigation({
    monat,
    basis,
}: {
    monat: string;
    basis: string;
}) {
    const url = (m: string) =>
        `${basis}${basis.includes('?') ? '&' : '?'}monat=${m}`;

    return (
        <div className="flex items-center gap-2">
            <Button
                asChild
                variant="outline"
                size="icon"
                aria-label="Vorheriger Monat"
            >
                <Link href={url(verschiebeMonat(monat, -1))} preserveScroll>
                    <ChevronLeft />
                </Link>
            </Button>
            <span className="min-w-40 text-center font-semibold">
                {monatName(monat)}
            </span>
            <Button
                asChild
                variant="outline"
                size="icon"
                aria-label="Nächster Monat"
            >
                <Link href={url(verschiebeMonat(monat, 1))} preserveScroll>
                    <ChevronRight />
                </Link>
            </Button>
        </div>
    );
}
