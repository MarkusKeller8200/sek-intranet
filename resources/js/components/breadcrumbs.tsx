import { Link } from '@inertiajs/react';
import { Fragment } from 'react';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { useSidebar } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * Brotkrumen ohne eigene Seite (href «#», z. B. «Fachanwendungen») öffnen das Seitenmenü –
 * nützlich, wenn es beim Öffnen einer Fachanwendung eingeklappt wurde.
 */
function OpenMenuCrumb({ title }: { title: string }) {
    const { isMobile, setOpen, setOpenMobile } = useSidebar();

    return (
        <BreadcrumbLink asChild>
            <button
                type="button"
                className="cursor-pointer"
                title="Menü einblenden"
                onClick={() => (isMobile ? setOpenMobile(true) : setOpen(true))}
            >
                {title}
            </button>
        </BreadcrumbLink>
    );
}

export function Breadcrumbs({
    breadcrumbs,
}: {
    breadcrumbs: BreadcrumbItemType[];
}) {
    return (
        <>
            {breadcrumbs.length > 0 && (
                <Breadcrumb>
                    <BreadcrumbList>
                        {breadcrumbs.map((item, index) => {
                            const isLast = index === breadcrumbs.length - 1;

                            return (
                                <Fragment key={index}>
                                    <BreadcrumbItem>
                                        {isLast ? (
                                            <BreadcrumbPage>
                                                {item.title}
                                            </BreadcrumbPage>
                                        ) : item.href === '#' ? (
                                            <OpenMenuCrumb title={item.title} />
                                        ) : (
                                            <BreadcrumbLink asChild>
                                                <Link href={item.href}>
                                                    {item.title}
                                                </Link>
                                            </BreadcrumbLink>
                                        )}
                                    </BreadcrumbItem>
                                    {!isLast && <BreadcrumbSeparator />}
                                </Fragment>
                            );
                        })}
                    </BreadcrumbList>
                </Breadcrumb>
            )}
        </>
    );
}
