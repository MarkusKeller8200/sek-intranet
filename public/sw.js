/*
 * Service Worker des SekBülach Intranets (PWA).
 * Datenschutz: Seiten und API-Antworten werden NIE zwischengespeichert – nur die
 * unveränderlichen Build-Dateien (/build/…), Symbole und die Offline-Seite.
 * Die Version kommt aus der Registrierung (sw.js?v=<build>): neue Version = neuer Cache.
 */
const VERSION = new URL(self.location.href).searchParams.get('v') || 'dev';
const CACHE = `intranet-static-${VERSION}`;
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((k) => k.startsWith('intranet-') && k !== CACHE)
                        .map((k) => caches.delete(k)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

const isStatic = (url) =>
    url.origin === self.location.origin &&
    (url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.startsWith('/fonts/') ||
        url.pathname === '/favicon.svg');

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    const url = new URL(request.url);

    // Seitenaufrufe: immer vom Server, ohne Verbindung die Offline-Seite
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );

        return;
    }

    // Build-Dateien haben einen Hash im Namen: aus dem Cache, sonst laden und ablegen
    if (isStatic(url)) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok && response.type === 'basic') {
                            const copy = response.clone();
                            caches
                                .open(CACHE)
                                .then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );
    }
    // Alles andere (Inertia-Antworten, API, Dateien) geht unverändert ans Netz
});
