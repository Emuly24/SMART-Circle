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
})();
