// Site-wide: the phone menu and the display theme switch.
(() => {
    const root = document.documentElement;

    // ----- Phone menu -----
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('site-nav');
    if (toggle && nav) {
        const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', String(open));
            nav.classList.toggle('is-open', open);
        };
        toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') setOpen(false); });
    }

    // ----- Theme switch -----
    const meta = document.querySelector('meta[name="theme-color"]');
    const colours = { light: '#1D4E89', comfort: '#3B4A5C', dark: '#0C121A' };
    const current = () => root.getAttribute('data-theme') || 'auto';
    const apply = (choice) => {
        if (choice === 'auto') {
            root.removeAttribute('data-theme');
        } else {
            root.setAttribute('data-theme', choice);
        }
        try {
            if (choice === 'auto') window.localStorage.removeItem('sc-theme');
            else window.localStorage.setItem('sc-theme', choice);
        } catch (e) { /* not saved, still applied for this page */ }
        sync();
    };
    const sync = () => {
        const choice = current();
        document.querySelectorAll('[data-theme-choice]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.themeChoice === choice)));
        const dark = choice === 'dark' || (choice === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (meta) meta.setAttribute('content', dark ? colours.dark : (choice === 'comfort' ? colours.comfort : colours.light));
    };
    document.querySelectorAll('[data-theme-choice]').forEach((b) => b.addEventListener('click', () => apply(b.dataset.themeChoice)));
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', sync);
    sync();

    // ----- Gentle entrance: cards ease in as they scroll into view -----
    const calm = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!calm && 'IntersectionObserver' in window && !root.hasAttribute('data-theme-fixed')) {
        const items = document.querySelectorAll('.site-main .tile, .site-main .section-card, .site-main .student-row, .site-main .inbox-row, .site-main .card');
        if (items.length > 0) {
            root.classList.add('js-reveal');
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        io.unobserve(entry.target);
                    }
                });
            }, { rootMargin: '0px 0px -5% 0px' });
            items.forEach((item) => { item.classList.add('reveal-item'); io.observe(item); });
            // Never leave anything hidden (e.g. printing, or an observer that does not fire).
            window.setTimeout(() => items.forEach((item) => item.classList.add('is-visible')), 2500);
        }
    }

    // ----- Logging out removes lessons saved for offline on this device -----
    document.querySelectorAll('form[action="/logout"]').forEach((form) => {
        form.addEventListener('submit', () => {
            try { localStorage.removeItem('sc-offline-index'); } catch (e) { /* private mode */ }
            if ('caches' in window) { caches.delete('sc-offline-v1'); }
        });
    });
})();
