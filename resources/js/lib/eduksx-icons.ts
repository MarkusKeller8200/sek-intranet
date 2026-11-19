/**
 * Freigegebene EduKSX-Icons (#216; Repo markussxch, docs/brand/icons/eduksx, freigegeben 09./13.11.2026), einfarbig in
 * Textfarbe, als Lucide-Icons für Menü und Module. Erzeugt aus svg/eduksx-<name>.svg – Motive und Strich nicht von
 * Hand ändern (neue Freigabe nötig).
 */
import { createLucideIcon } from 'lucide-react';
import type { LucideIcon, IconNode } from 'lucide-react';

const EduksxLogoNode: IconNode = [
    [
        'rect',
        {
            x: '3.5',
            y: '3.5',
            width: '7.5',
            height: '7.5',
            rx: '2',
            fill: 'currentColor',
            key: 'eduksx-1',
        },
    ],
    [
        'rect',
        {
            x: '13',
            y: '3.5',
            width: '7.5',
            height: '7.5',
            rx: '2',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'eduksx-2',
        },
    ],
    [
        'rect',
        {
            x: '3.5',
            y: '13',
            width: '7.5',
            height: '7.5',
            rx: '2',
            fill: 'currentColor',
            key: 'eduksx-3',
        },
    ],
    [
        'rect',
        {
            x: '13',
            y: '13',
            width: '7.5',
            height: '7.5',
            rx: '2',
            fill: 'currentColor',
            key: 'eduksx-4',
        },
    ],
] as IconNode;
export const EduksxLogo: LucideIcon = createLucideIcon(
    'EduksxLogo',
    EduksxLogoNode,
);
const EduksxIntranetNode: IconNode = [
    [
        'path',
        {
            d: 'M3.5 10.5 12 3.8l8.5 6.7V19a1.5 1.5 0 0 1-1.5 1.5H5A1.5 1.5 0 0 1 3.5 19Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'intranet-1',
        },
    ],
    [
        'rect',
        {
            x: '8',
            y: '11',
            width: '3.4',
            height: '3.4',
            rx: '0.8',
            fill: 'currentColor',
            key: 'intranet-2',
        },
    ],
    [
        'rect',
        {
            x: '12.6',
            y: '11',
            width: '3.4',
            height: '3.4',
            rx: '0.8',
            fill: 'currentColor',
            key: 'intranet-3',
        },
    ],
    [
        'rect',
        {
            x: '8',
            y: '15.6',
            width: '3.4',
            height: '3.4',
            rx: '0.8',
            fill: 'currentColor',
            key: 'intranet-4',
        },
    ],
    [
        'rect',
        {
            x: '12.6',
            y: '15.6',
            width: '3.4',
            height: '3.4',
            rx: '0.8',
            fill: 'currentColor',
            key: 'intranet-5',
        },
    ],
] as IconNode;
export const EduksxIntranet: LucideIcon = createLucideIcon(
    'EduksxIntranet',
    EduksxIntranetNode,
);
const EduksxTimeNode: IconNode = [
    [
        'circle',
        {
            cx: '12',
            cy: '12',
            r: '8.8',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'time-1',
        },
    ],
    [
        'path',
        {
            d: 'M12 7v5l3.4 2.1',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'time-2',
        },
    ],
    [
        'circle',
        { cx: '12', cy: '12', r: '1.1', fill: 'currentColor', key: 'time-3' },
    ],
] as IconNode;
export const EduksxTime: LucideIcon = createLucideIcon(
    'EduksxTime',
    EduksxTimeNode,
);
const EduksxGlossaryNode: IconNode = [
    [
        'path',
        {
            d: 'M12 6.6C9.8 5.1 6.9 4.7 3 5.2v13.3c3.9-.5 6.8-.1 9 1.4 2.2-1.5 5.1-1.9 9-1.4V5.2c-3.9-.5-6.8-.1-9 1.4Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'glossary-1',
        },
    ],
    [
        'path',
        {
            d: 'M12 6.6v13.3',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'glossary-2',
        },
    ],
    [
        'path',
        {
            d: 'M5.6 15.2 7.4 9.6l1.8 5.6M6.2 13.4h2.4',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'glossary-3',
        },
    ],
    [
        'path',
        {
            d: 'M14.6 9.8h3.2l-3.2 5.2h3.2',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'glossary-4',
        },
    ],
] as IconNode;
export const EduksxGlossary: LucideIcon = createLucideIcon(
    'EduksxGlossary',
    EduksxGlossaryNode,
);
const EduksxCodexNode: IconNode = [
    [
        'path',
        {
            d: 'M12 5v14.5M8 19.5h8M4 8h16',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'codex-1',
        },
    ],
    [
        'circle',
        { cx: '12', cy: '4.4', r: '1.4', fill: 'currentColor', key: 'codex-2' },
    ],
    [
        'path',
        {
            d: 'M6.5 8 4 13.6M6.5 8 9 13.6M17.5 8 15 13.6M17.5 8 20 13.6',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'codex-3',
        },
    ],
    [
        'path',
        {
            d: 'M3.2 13.6h6.6a3.3 3.3 0 0 1-6.6 0ZM14.2 13.6h6.6a3.3 3.3 0 0 1-6.6 0Z',
            fill: 'currentColor',
            key: 'codex-4',
        },
    ],
] as IconNode;
export const EduksxCodex: LucideIcon = createLucideIcon(
    'EduksxCodex',
    EduksxCodexNode,
);
const EduksxLicenceNode: IconNode = [
    [
        'circle',
        {
            cx: '7.4',
            cy: '12',
            r: '4.6',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'licence-1',
        },
    ],
    [
        'circle',
        {
            cx: '7.4',
            cy: '12',
            r: '1.5',
            fill: 'currentColor',
            key: 'licence-2',
        },
    ],
    [
        'path',
        {
            d: 'M12 12h9.2M18.6 12v3.6M15.6 12v2.6',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'licence-3',
        },
    ],
] as IconNode;
export const EduksxLicence: LucideIcon = createLucideIcon(
    'EduksxLicence',
    EduksxLicenceNode,
);
const EduksxSignNode: IconNode = [
    [
        'path',
        {
            d: 'M7.8 16.2 8.9 11.6 16.6 3.9a1.8 1.8 0 0 1 2.5 0l1 1a1.8 1.8 0 0 1 0 2.5l-7.7 7.7Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'sign-1',
        },
    ],
    [
        'path',
        {
            d: 'M14.9 5.6l3.5 3.5',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'sign-2',
        },
    ],
    [
        'path',
        {
            d: 'M3.2 20.2c1.3-1.6 2.6-1.6 3.6 0s2.3 1.6 3.6 0',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'sign-3',
        },
    ],
    [
        'path',
        {
            d: 'M13.6 20.2h7.2',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'sign-4',
        },
    ],
] as IconNode;
export const EduksxSign: LucideIcon = createLucideIcon(
    'EduksxSign',
    EduksxSignNode,
);
const EduksxTimetableNode: IconNode = [
    [
        'rect',
        {
            x: '3.5',
            y: '5',
            width: '17',
            height: '15.5',
            rx: '2.5',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'timetable-1',
        },
    ],
    [
        'path',
        {
            d: 'M8 3.2v3.4M16 3.2v3.4M9.2 9.5v11M14.8 9.5v11M3.5 15h17',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'timetable-2',
        },
    ],
    [
        'path',
        {
            d: 'M4.4 9.5h15.2',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'timetable-3',
        },
    ],
    [
        'rect',
        {
            x: '10.4',
            y: '10.7',
            width: '3.2',
            height: '3.1',
            rx: '0.6',
            fill: 'currentColor',
            key: 'timetable-4',
        },
    ],
] as IconNode;
export const EduksxTimetable: LucideIcon = createLucideIcon(
    'EduksxTimetable',
    EduksxTimetableNode,
);
const EduksxStaffingNode: IconNode = [
    [
        'circle',
        {
            cx: '8.5',
            cy: '7.8',
            r: '3',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'staffing-1',
        },
    ],
    [
        'path',
        {
            d: 'M3 19.5c.6-3.4 2.8-5.4 5.5-5.4 1.4 0 2.6.5 3.6 1.5',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'staffing-2',
        },
    ],
    [
        'rect',
        {
            x: '13',
            y: '12.5',
            width: '8',
            height: '8',
            rx: '1.6',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'staffing-3',
        },
    ],
    [
        'path',
        {
            d: 'M13 15.5h8',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'staffing-4',
        },
    ],
    [
        'circle',
        { cx: '17', cy: '18', r: '1', fill: 'currentColor', key: 'staffing-5' },
    ],
] as IconNode;
export const EduksxStaffing: LucideIcon = createLucideIcon(
    'EduksxStaffing',
    EduksxStaffingNode,
);
const EduksxIdentNode: IconNode = [
    [
        'rect',
        {
            x: '2.75',
            y: '5',
            width: '18.5',
            height: '14',
            rx: '2.5',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ident-1',
        },
    ],
    [
        'circle',
        {
            cx: '8.5',
            cy: '10.4',
            r: '2',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ident-2',
        },
    ],
    [
        'path',
        {
            d: 'M5.4 16.2c.6-1.7 1.8-2.6 3.1-2.6s2.5.9 3.1 2.6',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ident-3',
        },
    ],
    [
        'path',
        {
            d: 'M14.3 10.2h4M14.3 13.8h2.8',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ident-4',
        },
    ],
] as IconNode;
export const EduksxIdent: LucideIcon = createLucideIcon(
    'EduksxIdent',
    EduksxIdentNode,
);
const EduksxConnectNode: IconNode = [
    [
        'path',
        {
            d: 'M8.3 7.4h7.4M7.2 9.1l3.4 6.3M16.8 9.1l-3.4 6.3',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'connect-1',
        },
    ],
    [
        'circle',
        {
            cx: '5.8',
            cy: '7.2',
            r: '2.6',
            fill: 'currentColor',
            key: 'connect-2',
        },
    ],
    [
        'circle',
        {
            cx: '18.2',
            cy: '7.2',
            r: '2.6',
            fill: 'currentColor',
            key: 'connect-3',
        },
    ],
    [
        'circle',
        {
            cx: '12',
            cy: '17.8',
            r: '2.6',
            fill: 'currentColor',
            key: 'connect-4',
        },
    ],
] as IconNode;
export const EduksxConnect: LucideIcon = createLucideIcon(
    'EduksxConnect',
    EduksxConnectNode,
);
const EduksxShopNode: IconNode = [
    [
        'path',
        {
            d: 'M4.6 8.4h14.8l-1.1 11.1a1.6 1.6 0 0 1-1.6 1.5H7.3a1.6 1.6 0 0 1-1.6-1.5Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'shop-1',
        },
    ],
    [
        'path',
        {
            d: 'M8.8 8.4V7a3.2 3.2 0 0 1 6.4 0v1.4',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'shop-2',
        },
    ],
    [
        'path',
        {
            d: 'M9.2 13.6l1.9 1.9 3.7-3.8',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'shop-3',
        },
    ],
] as IconNode;
export const EduksxShop: LucideIcon = createLucideIcon(
    'EduksxShop',
    EduksxShopNode,
);
const EduksxPortalNode: IconNode = [
    [
        'path',
        {
            d: 'M6 20V10.2a6 6 0 0 1 12 0V20M3.5 20h17',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'portal-1',
        },
    ],
    [
        'path',
        {
            d: 'M8.6 15.2h6.2M12.4 12.8l2.4 2.4-2.4 2.4',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'portal-2',
        },
    ],
] as IconNode;
export const EduksxPortal: LucideIcon = createLucideIcon(
    'EduksxPortal',
    EduksxPortalNode,
);
const EduksxServicesNode: IconNode = [
    [
        'path',
        {
            d: 'M9.36 5.62 L10.59 5.25 L10.79 2.88 L13.21 2.88 L13.41 5.25 L14.64 5.63 L15.78 6.23 L17.59 4.70 L19.30 6.41 L17.77 8.22 L18.38 9.36 L18.75 10.59 L21.12 10.79 L21.12 13.21 L18.75 13.41 L18.37 14.64 L17.77 15.78 L19.30 17.59 L17.59 19.30 L15.78 17.77 L14.64 18.38 L13.41 18.75 L13.21 21.12 L10.79 21.12 L10.59 18.75 L9.36 18.37 L8.22 17.77 L6.41 19.30 L4.70 17.59 L6.23 15.78 L5.62 14.64 L5.25 13.41 L2.88 13.21 L2.88 10.79 L5.25 10.59 L5.63 9.36 L6.23 8.22 L4.70 6.41 L6.41 4.70 L8.22 6.23Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'services-1',
        },
    ],
    [
        'circle',
        {
            cx: '12',
            cy: '12',
            r: '2.7',
            fill: 'currentColor',
            key: 'services-2',
        },
    ],
] as IconNode;
export const EduksxServices: LucideIcon = createLucideIcon(
    'EduksxServices',
    EduksxServicesNode,
);
const EduksxCoreNode: IconNode = [
    [
        'path',
        {
            d: 'M12 2.9 19.9 7.45v9.1L12 21.1l-7.9-4.55v-9.1Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'core-1',
        },
    ],
    [
        'path',
        {
            d: 'M12 7.6 15.8 9.8v4.4L12 16.4l-3.8-2.2V9.8Z',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'core-2',
        },
    ],
    [
        'circle',
        { cx: '12', cy: '12', r: '1.9', fill: 'currentColor', key: 'core-3' },
    ],
] as IconNode;
export const EduksxCore: LucideIcon = createLucideIcon(
    'EduksxCore',
    EduksxCoreNode,
);
const EduksxOpsNode: IconNode = [
    [
        'rect',
        {
            x: '2.75',
            y: '4',
            width: '18.5',
            height: '12.5',
            rx: '2',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ops-1',
        },
    ],
    [
        'path',
        {
            d: 'M9 20.5h6M12 16.5v4',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ops-2',
        },
    ],
    [
        'path',
        {
            d: 'M5.8 11h3l1.6-3.2 2.4 6 1.8-3.8h3.6',
            fill: 'none',
            stroke: 'currentColor',
            strokeWidth: '1.75',
            strokeLinecap: 'round',
            strokeLinejoin: 'round',
            key: 'ops-3',
        },
    ],
    [
        'circle',
        { cx: '17.9', cy: '7', r: '0.9', fill: 'currentColor', key: 'ops-4' },
    ],
] as IconNode;
export const EduksxOps: LucideIcon = createLucideIcon(
    'EduksxOps',
    EduksxOpsNode,
);

/** Name im Menü (config/intranet.php bzw. module.json «icon») → Icon. */
export const EDUKSX_ICONS: Record<string, LucideIcon> = {
    EduksxLogo,
    EduksxIntranet,
    EduksxTime,
    EduksxGlossary,
    EduksxCodex,
    EduksxLicence,
    EduksxSign,
    EduksxTimetable,
    EduksxStaffing,
    EduksxIdent,
    EduksxConnect,
    EduksxShop,
    EduksxPortal,
    EduksxServices,
    EduksxCore,
    EduksxOps,
};
