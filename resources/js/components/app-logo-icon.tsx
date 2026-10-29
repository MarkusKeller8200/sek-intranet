import type { ImgHTMLAttributes } from 'react';

/** Signet der Sekundarschule Bülach (fünf Ringe auf Logo-Blau). */
export default function AppLogoIcon(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img src="/images/sekbuelach-icon.svg" alt="" {...props} />;
}
