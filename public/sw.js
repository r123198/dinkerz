// CourtOS service worker — installable PWA shell with offline fallback.
// Bump CACHE_VERSION to invalidate old caches on the next visit.
const CACHE_VERSION = 'courtos-v1';
const ASSET_CACHE = `${CACHE_VERSION}-assets`;
const OFFLINE_URL = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/images/favicon/favicon_io/android-chrome-192x192.png',
    '/images/favicon/favicon_io/android-chrome-512x512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(ASSET_CACHE).then((cache) => cache.addAll(PRECACHE)),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => !key.startsWith(CACHE_VERSION))
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

// Cache-first for content-hashed build assets and static images.
function isCacheableAsset(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/images/')
    );
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Only handle our own origin; let everything else go to the network.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Page navigations: always try the network first so authenticated,
    // CSRF-bearing HTML is never served stale. Fall back to the offline
    // page only when the network is unavailable.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    if (isCacheableAsset(url)) {
        event.respondWith(
            caches.open(ASSET_CACHE).then(async (cache) => {
                const cached = await cache.match(request);
                if (cached) {
                    return cached;
                }

                const response = await fetch(request);
                if (response.ok) {
                    cache.put(request, response.clone());
                }

                return response;
            }),
        );
    }
});
