/*
 * SMART Circle offline helper. Only lessons and notes a student chose to "Save for offline" are kept
 * (in the sc-offline cache, on their own device). Saved lesson files are read from the device even
 * when online, which saves data. Everything else always comes from the network; when the network is
 * gone, saved pages still open and other pages show the offline page.
 */
const CACHE = 'sc-offline-v1';
const SHELL = ['/offline.html', '/assets/js/offline-page.js', '/assets/css/app.css'];
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;

    // Saved lesson files and note pictures: from the device first (no data used).
    if (/^\/lessons\/\d+\/file$/.test(url.pathname) || /^\/notes\/\d+\/images\/\d+$/.test(url.pathname)) {
        event.respondWith(caches.open(CACHE).then((c) => c.match(req).then((hit) => hit || fetch(req))));
        return;
    }

    // Everything else: the network, falling back to a saved copy, then the offline page.
    event.respondWith(fetch(req).catch(() => caches.open(CACHE).then((c) => c.match(req).then((hit) => {
        if (hit) return hit;
        return req.mode === 'navigate' ? c.match(OFFLINE) : Response.error();
    }))));
});
