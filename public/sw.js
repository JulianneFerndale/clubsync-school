const CACHE_NAME = 'clubsync-v3';
const OFFLINE_URL = '/offline';

// Assets to pre-cache on install so the offline fallback is always available.
const PRECACHE_ASSETS = ['/', OFFLINE_URL];

self.addEventListener('install', (event) => {
    event.waitUntil(
        // Don't let a single failed precache (e.g. '/' returning a redirect/404)
        // abort the whole install.
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_ASSETS).catch(() => {}))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

// Network-first strategy: try network, fall back to cache, then to the offline page.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    // Skip cross-origin requests (fonts, APIs, etc.)
    if (url.origin !== self.location.origin) return;

    // Skip Laravel API / form routes — always network only.
    if (url.pathname.startsWith('/api/')) return;

    // Never cache the connectivity probe — a stale cached 204 would make the
    // app think it is online while offline. Let it hit the network directly.
    if (url.pathname === '/ping') return;

    // Never cache private messages. The network-first handler below stores every
    // page it fetches, which would leave conversations readable in Cache Storage
    // on a shared computer after logout (RA 10173).
    if (url.pathname === '/chat' || url.pathname.startsWith('/chat/')) return;

    // Built assets are content-hashed (immutable): serve cache-first so a slow or
    // dropped network request can never leave a page unstyled, and a new build
    // (new hash = new URL) is always fetched fresh. This is the fix for pages
    // occasionally rendering as unstyled HTML.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(event.request).then((cached) =>
                cached || fetch(event.request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                    }
                    return response;
                })
            )
        );
        return;
    }

    const isNavigation =
        event.request.mode === 'navigate' ||
        (event.request.headers.get('accept') || '').includes('text/html');

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                if (response.ok) {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone));
                }
                return response;
            })
            .catch(async () => {
                // Offline: serve the cached copy if we have one…
                const cached = await caches.match(event.request);
                if (cached) return cached;

                // …otherwise, for page navigations, show the offline fallback.
                if (isNavigation) {
                    const offline = await caches.match(OFFLINE_URL);
                    if (offline) return offline;
                }

                return Response.error();
            })
    );
});
