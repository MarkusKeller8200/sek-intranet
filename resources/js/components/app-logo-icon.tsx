import type { SVGAttributes } from 'react';

/** Platzhalter-Signet (Buch/Haus). Durch das offizielle Logo der SekBülach ersetzen. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 2 2 7.5V10h20V7.5L12 2Zm-7 10v7H3v3h18v-3h-2v-7h-3v7h-2.5v-7h-3v7H8v-7H5Z" />
        </svg>
    );
}
