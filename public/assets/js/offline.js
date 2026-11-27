/*
 * "Save for offline" on lesson and note pages: keeps that page, its lesson file or pictures, and the
 * scripts and styles it needs in the device's sc-offline cache. Also registers the service worker.
 * Logging out removes everything saved (see site.js).
 */
(function () {
    if (!('serviceWorker' in navigator) || !('caches' in window)) { return; }
    navigator.serviceWorker.register('/sw.js').catch(function () { /* offline saving is optional */ });

    var button = document.querySelector('.js-offline-save');
    if (!button) { return; }
    button.hidden = false;
    var KEY = 'sc-offline-index';
    var page = location.pathname;
    var index = [];
    try { index = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { index = []; }
    var saved = index.some(function (it) { return it.url === page; });
    var label = button.querySelector('.js-offline-label') || button;
    function show(state) {
        label.textContent = state === 'saved' ? 'Saved for offline ✓' : state === 'saving' ? 'Saving…' : 'Save for offline';
        button.disabled = state === 'saving';
    }
    show(saved ? 'saved' : 'idle');

    button.addEventListener('click', function () {
        if (saved) { return; }
        show('saving');
        var urls = [page];
        (button.getAttribute('data-offline-extra') || '').split(' ').forEach(function (u) { if (u) { urls.push(u); } });
        document.querySelectorAll('link[rel="stylesheet"][href^="/"], script[src^="/"]').forEach(function (n) { urls.push(n.getAttribute('href') || n.getAttribute('src')); });
        document.querySelectorAll('.reader img[src^="/notes/"]').forEach(function (img) { urls.push(img.getAttribute('src')); });
        caches.open('sc-offline-v1').then(function (cache) {
            return Promise.all(urls.map(function (u) { return cache.add(new Request(u, { credentials: 'same-origin' })).catch(function () { return null; }); }));
        }).then(function () {
            index = index.filter(function (it) { return it.url !== page; });
            index.unshift({ url: page, title: button.getAttribute('data-offline-title') || document.title, saved: Date.now() });
            try { localStorage.setItem(KEY, JSON.stringify(index.slice(0, 50))); } catch (e) { /* storage full */ }
            saved = true;
            show('saved');
        }).catch(function () { show('idle'); window.alert('Could not save for offline. Is there enough space on this device?'); });
    });
})();
