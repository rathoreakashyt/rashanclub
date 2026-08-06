/**
 * POS PWA Service Worker
 * Caches assets for offline support
 */
const CACHE_NAME = 'pos-cache-v3';

const ASSETS_TO_CACHE = [
    '/',
    '/pwa/manifest.json',
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(ASSETS_TO_CACHE))
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

    // Skip cross-origin requests (except for same-origin assets)
    if (url.origin !== location.origin) {
        return;
    }

    // Bypass service worker for API/dynamic data - always fetch from network
    // Fixes POS product loading and other AJAX requests that require fresh data
    const path = url.pathname;
    const isApiRequest = path.startsWith('/pos/') ||
        path.startsWith('/api/') ||
        path.startsWith('/sale/') ||
        path.startsWith('/register/') ||
        path.startsWith('/customer/') ||
        path.startsWith('/payment-gateway/') ||
        request.headers.get('Accept')?.includes('application/json');
    if (isApiRequest) {
        return; // Let browser handle directly - no intercept
    }

    event.respondWith(
        caches.match(request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((response) => {
                    // Cache CSS, JS, fonts, and images
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
                    return caches.match('/');
                }
                return new Response('Offline', {
                    status: 503,
                    statusText: 'Service Unavailable'
                });
            })
    );
});
