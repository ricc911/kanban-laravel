const CACHE_NAME = 'kanban-static-v1';

const STATIC_ASSETS = [
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
    );

    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key !== CACHE_NAME)
                    .map((key) => caches.delete(key))
            )
        )
    );

    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Non mettere in cache API e autenticazione.
    if (
        url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/sanctum/') ||
        url.pathname === '/login' ||
        url.pathname === '/logout' ||
        url.pathname === '/register'
    ) {
        return;
    }

    // Asset compilati da Vite hanno hash nel nome:
    // sono ottimi candidati per la cache.
    if (
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname === '/manifest.webmanifest'
    ) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (!response || response.status !== 200) {
                        return response;
                    }

                    const copy = response.clone();

                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, copy);
                    });

                    return response;
                });
            })
        );
    }
});