// EduKSX-Icons aus @eduksx/ui (Repo EduKSX-UI) übernehmen – dieses Repo hält keine eigenen Kopien (22.11.2026).
// Erzeugt die Dateien, die als feste Pfade gebraucht werden (Favicon, App-Icons, Signet); sie stehen in .gitignore.
// Aufruf: node scripts/eduksx-icons.mjs  (läuft vor «build» und «dev»)
import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const ui = resolve(root, 'node_modules/@eduksx/ui/icons');
const dateien = [
    ['svg/eduksx-intranet-tile-light.svg', 'public/favicon.svg'],
    [
        'svg/eduksx-intranet-tile-light.svg',
        'public/images/brands/eduksx-intranet-tile-light.svg',
    ],
    [
        'svg/eduksx-intranet-tile-dark.svg',
        'public/images/brands/eduksx-intranet-tile-dark.svg',
    ],
    ['png/192/eduksx-intranet-tile-light.png', 'public/icons/icon-192.png'],
    ['png/512/eduksx-intranet-tile-light.png', 'public/icons/icon-512.png'],
    ['png/192/eduksx-intranet-tile-light.png', 'public/apple-touch-icon.png'],
    ['ico/eduksx-intranet.ico', 'public/favicon.ico'],
    [
        'png/192/eduksx-intranet-maskable-light.png',
        'public/icons/icon-maskable-192.png',
    ],
    [
        'png/512/eduksx-intranet-maskable-light.png',
        'public/icons/icon-maskable-512.png',
    ],
];

for (const [quelle, ziel] of dateien) {
    const pfad = resolve(root, ziel);
    mkdirSync(dirname(pfad), { recursive: true });
    copyFileSync(resolve(ui, quelle), pfad);
}
console.log(`EduKSX-Icons aus @eduksx/ui → ${dateien.length} Dateien`);
