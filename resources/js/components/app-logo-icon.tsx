import { usePage } from '@inertiajs/react';
import type { ImgHTMLAttributes } from 'react';

/** Signet der Instanz (#216): eigenes Signet oder das Icon «EduKSX-Intranet». */
export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    const { branding } = usePage().props;

    return (
        <img
            src={
                branding?.signet ??
                '/images/brands/eduksx-intranet-tile-light.svg'
            }
            alt=""
            {...props}
        />
    );
}
