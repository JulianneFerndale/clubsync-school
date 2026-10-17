// v4: v3 stored signed-in pages (see storable()). A new name makes activate() below
// delete that old cache from every device on its next visit.
const CACHE_NAME = 'clubsync-v4';
const OFFLINE_URL = '/offline';

// Assets to pre-cache on install so the offline fallback is always available.
const PRECACHE_ASSETS = ['/', OFFLINE_URL];

// Only what the server allows to be kept. Every signed-in page is sent with
// Cache-Control: no-store (FirebaseTokenMiddleware); storing one let anyone open it
// offline after its owner had logged out — on a shared phone or lab computer, with
// no password (RA 10173). A redirected response isn't stored under the address that
// was asked for either.
function storable(response) {
    return response.ok
        && !response.redirected
        && !/no-store|private/i.test(response.headers.get('Cache-Control') || '');
}

// On logout, keep only the offline page and the site's own files, in case anything
// personal was stored before this worker (or by a page sent without no-store).
async function forgetPages() {
    const cache = await caches.open(CACHE_NAME);
    for (const request of await cache.keys()) {
        const path = new URL(request.url).pathname;
        if (!PRECACHE_ASSETS.includes(path) && !path.startsWith('/build/')) {
            await cache.delete(request);
        }
    }
}

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
    const url = new URL(event.request.url);

    // The logout request itself goes to the network untouched; meanwhile, forget.
    if (event.request.method === 'POST' && url.origin === self.location.origin && url.pathname === '/logout') {
        event.waitUntil(forgetPages());
        return;
    }

    if (event.request.method !== 'GET') return;

    // Skip cross-origin requests (fonts, APIs, etc.)
    if (url.origin !== self.location.origin) return;

    // Skip Laravel API / form routes — always network only.
    if (url.pathname.startsWith('/api/')) return;

    // Never cache the connectivity probe — a stale cached 204 would make the
    // app think it is online while offline. Let it hit the network directly.
    if (url.pathname === '/ping') return;

    // Never cache private messages. storable() already refuses signed-in pages;
    // chat is skipped outright as well, being the most private of them (RA 10173).
    if (url.pathname === '/chat' || url.pathname.startsWith('/chat/')) return;

    // Nor official receipts. A receipt PDF carries the member's name and EDP number,
    // and caching an attachment buys nothing offline — it would only leave personal
    // data in Cache Storage on a shared computer after logout (RA 10173), for the
    // same reason chat is skipped above.
    if (/\/receipt(?:\/|$)/.test(url.pathname)) return;

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
                if (storable(response)) {
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
