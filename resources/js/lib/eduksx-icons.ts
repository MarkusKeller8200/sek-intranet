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

/* Im Intranet genutzte Icons (Bundle «intranet» in EduKSX-UI) als feste Namen für Menü, module.json und Tests. */
export const EduksxLogo: LucideIcon = komponente('eduksx');
export const EduksxIntranet: LucideIcon = komponente('intranet');
export const EduksxTime: LucideIcon = komponente('time');
export const EduksxGlossary: LucideIcon = komponente('glossary');
export const EduksxCodex: LucideIcon = komponente('codex');
export const EduksxLicence: LucideIcon = komponente('licence');
export const EduksxSign: LucideIcon = komponente('sign');
export const EduksxTimetable: LucideIcon = komponente('timetable');
export const EduksxStaffing: LucideIcon = komponente('staffing');
export const EduksxIdent: LucideIcon = komponente('ident');
export const EduksxConnect: LucideIcon = komponente('connect');
export const EduksxShop: LucideIcon = komponente('shop');
export const EduksxPortal: LucideIcon = komponente('portal');
export const EduksxServices: LucideIcon = komponente('services');
export const EduksxCore: LucideIcon = komponente('core');
export const EduksxOps: LucideIcon = komponente('ops');

/** Name im Menü (config/intranet.php bzw. module.json «icon») → Icon. */
export const EDUKSX_ICONS: Record<string, LucideIcon> = Object.fromEntries(
    EDUKSX_ICON_NAMES.map((n) => [komponentenName(n), komponente(n)]),
);
