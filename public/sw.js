const CACHE_NAME = 'veloce-shell-v1';
const STATIC_ASSET_PREFIXES = ['/build/', '/icons/', '/brand/'];
const NETWORK_ONLY_PREFIXES = ['/absen', '/izin', '/persetujuan', '/dashboard'];

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheKeys) =>
                Promise.all(
                    cacheKeys
                        .filter((key) => key !== CACHE_NAME)
                        .map((key) => caches.delete(key))
                )
            )
            .then(() => self.clients.claim())
    );
});

function isNetworkOnlyPath(pathname) {
    return NETWORK_ONLY_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`)
    );
}

function isStaticAssetPath(pathname) {
    return STATIC_ASSET_PREFIXES.some((prefix) => pathname.startsWith(prefix));
}

function isHtmlOrNavigationRequest(request) {
    if (request.mode === 'navigate' || request.destination === 'document') {
        return true;
    }

    const acceptHeader = request.headers.get('accept') || '';

    return acceptHeader.includes('text/html');
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Network-only untuk seluruh metode non-GET agar pengiriman data selalu langsung ke server.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Abaikan permintaan lintas origin.
    if (url.origin !== self.location.origin) {
        return;
    }

    // Network-only untuk semua dokumen HTML/navigasi serta rute /absen, /izin, /persetujuan, dan /dashboard
    // agar absensi tidak pernah berjalan offline dan selalu memakai waktu server resmi.
    if (isHtmlOrNavigationRequest(request) || isNetworkOnlyPath(url.pathname)) {
        return;
    }

    // Cache-first HANYA untuk aset statis /build/, /icons/, dan /brand/.
    if (!isStaticAssetPath(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((networkResponse) => {
                if (
                    networkResponse &&
                    networkResponse.status === 200 &&
                    networkResponse.type === 'basic'
                ) {
                    const responseToCache = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }

                return networkResponse;
            });
        })
    );
});
