const CACHE_NAME = 'farmwell-shell-v1';
const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/farmwell-192.png',
    '/farmwell-512.png',
    '/favicon.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
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
                        .filter((key) => key.startsWith('farmwell-shell-') && key !== CACHE_NAME)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
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

    const isStaticAsset =
        url.pathname.startsWith('/build/assets/') ||
        url.pathname.startsWith('/build/fonts/') ||
        PRECACHE_URLS.includes(url.pathname);

    if (isStaticAsset) {
        event.respondWith(
            caches.match(request).then(
                (cached) =>
                    cached ||
                    fetch(request).then((response) => {
                        if (response && response.ok) {
                            const copy = response.clone();
                            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(
                () => caches.match('/offline.html') || Response.error(),
            ),
        );
    }
});
