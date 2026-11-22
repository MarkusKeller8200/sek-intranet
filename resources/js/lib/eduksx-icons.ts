/**
 * EduKSX-Icons für Menü und Module (#216) – einzige Quelle ist das Repo EduKSX-UI (Paket `@eduksx/ui`, Baustein
 * `EduksxIcon`, Variante `mono` in Textfarbe). Dieses Repo hält keine eigenen Pfade mehr (Entscheid 22.11.2026).
 * Die Namen («EduksxTime» usw.) bleiben, damit config/intranet.php und module.json unverändert gelten; sie entstehen
 * aus den Schlüsseln des Icon-Sets (`eduksx` → EduksxLogo, `time` → EduksxTime, …).
 */
import { EDUKSX_ICON_NAMES, EduksxIcon, type EduksxIconName } from '@eduksx/ui';
import type { LucideIcon, LucideProps } from 'lucide-react';
import { createElement, forwardRef } from 'react';

function komponente(name: EduksxIconName): LucideIcon {
    const Icon = forwardRef<SVGSVGElement, LucideProps>(
        ({ className, size, style }, ref) =>
            createElement(EduksxIcon, {
                name,
                variant: 'mono',
                className,
                size: size ?? 24,
                style,
                ref,
            }),
    );
    Icon.displayName = komponentenName(name);
    return Icon as unknown as LucideIcon;
}

function komponentenName(name: EduksxIconName): string {
    return name === 'eduksx'
        ? 'EduksxLogo'
        : `Eduksx${name.charAt(0).toUpperCase()}${name.slice(1)}`;
}

/** Name im Menü (config/intranet.php bzw. module.json «icon») → Icon. */
export const EDUKSX_ICONS: Record<string, LucideIcon> = Object.fromEntries(
    EDUKSX_ICON_NAMES.map((n) => [komponentenName(n), komponente(n)]),
);
