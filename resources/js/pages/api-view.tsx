import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useMemo } from 'react';
import ApiReference from '@/components/api-reference';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { buildOpenApi } from '@/lib/api-recorder/openapi';
import type { ApiDoc } from '@/lib/api-recorder/types';

type Props = {
    title: string;
    description: string;
    back: { href: string; label: string };
    /** Eine der drei Quellen: fertige Beschreibung, Adresse (gleicher Ursprung) oder Aufzeichnung */
    spec?: Record<string, unknown> | null;
    url?: string | null;
    doc?: ApiDoc | null;
};

/** API-Ansicht mit Scalar: eigene REST-API, Plugin-Endpunkte, aufgezeichnete APIs. */
export default function ApiView({
    title,
    description,
    back,
    spec,
    url,
    doc,
}: Props) {
    const source = useMemo<object | string>(
        () => (doc ? buildOpenApi(doc) : (spec ?? url ?? {})),
        [doc, spec, url],
    );

    return (
        <>
            <Head title={title} />
            <div className="flex flex-1 flex-col gap-4 p-4 md:p-6">
                <Button variant="ghost" size="sm" className="w-fit" asChild>
                    <Link href={back.href}>
                        <ArrowLeft /> {back.label}
                    </Link>
                </Button>
                <PageHeader title={title} description={description} />
                <ApiReference spec={source} />
                <p className="text-xs text-muted-foreground">
                    Ansicht mit Scalar (Open Source, MIT), im Intranet
                    ausgeliefert: ohne Telemetrie, ohne fremde Schriften und
                    ohne Scalar-Proxy. «Test Request» funktioniert nur, wenn der
                    Zielserver Anfragen aus dem Intranet zulässt (CORS); für die
                    eigene REST-API ein Token unter «API» erstellen.
                </p>
            </div>
        </>
    );
}

ApiView.layout = (props: {
    title: string;
    back: { href: string; label: string };
}) => ({
    breadcrumbs: [
        { title: props.back.label, href: props.back.href },
        { title: props.title, href: '#' },
    ],
});
