/**
 * POS PWA Service Worker
 * Caches POS pages and assets for full offline support
 * Desktop POS = PWA POS (100% identical)
 */
const CACHE_NAME = 'pos-cache-v6';

const PRECACHE_URLS = [
    '/',
    '/pwa/manifest.json',
];

// Install event - pre-cache essential pages
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting())
            .catch((err) => console.warn('PWA install cache failed:', err))
    );
});

// Activate event - clean old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames
                    .filter((name) => name !== CACHE_NAME)
                    .map((name) => caches.delete(name))
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }

    const path = url.pathname;

    // ═══ API/DATA requests: always network (never cache) ═══
    const isApiRequest = path.startsWith('/api/') ||
        path.startsWith('/sale/') ||
        path.startsWith('/register/') ||
        path.startsWith('/customer/') ||
        path.startsWith('/payment-gateway/') ||
        request.headers.get('Accept')?.includes('application/json');
    if (isApiRequest) {
        return;
    }

    // ═══ POS page HTML requests: Network-first with cache fallback ═══
    // This ensures POS pages work offline after first load
    const isPosPage = path === '/pos' || path === '/pos/' ||
        path.startsWith('/pos/') || path.startsWith('/pos?');
    if (isPosPage && request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Cache the POS page HTML for offline
                    if (response.ok) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return response;
                })
                .catch(() => {
                    // Offline: return cached POS page
                    return caches.match(request).then((cached) => {
                        return cached || caches.match('/pos');
                    });
                })
        );
        return;
    }

    // ═══ Static assets: Cache-first with network fallback ═══
    event.respondWith(
        caches.match(request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((response) => {
                    const contentType = response.headers.get('content-type') || '';
                    const shouldCache = /\.(css|js|woff2?|ttf|eot|png|jpg|jpeg|gif|ico|svg|webp)$/i.test(url.pathname) ||
                        contentType.includes('text/css') ||
                        contentType.includes('application/javascript') ||
                        contentType.includes('font/');

                    if (response.ok && shouldCache) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => {
                            cache.put(request, responseClone);
                        });
                    }
                    return response;
                });
            })
            .catch(() => {
                // Return offline page for navigation requests
                if (request.mode === 'navigate') {
                    return caches.match('/pos').then((cached) => {
                        return cached || caches.match('/');
                    });
                }
                return new Response('Offline', {
                    status: 503,
                    statusText: 'Service Unavailable'
                });
            })
    );
});
