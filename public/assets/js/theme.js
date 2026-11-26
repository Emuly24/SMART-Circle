// Runs in <head> before the page draws: applies the saved theme (no flash of the wrong colours).
// Saved per device. "auto" (nothing saved) follows the device's own light/dark setting.
(function () {
    var root = document.documentElement;
    root.classList.add('js');
    if (root.hasAttribute('data-theme-fixed')) return;   // letters and the board stay light
    try {
        var t = window.localStorage.getItem('sc-theme');
        if (t === 'light' || t === 'comfort' || t === 'dark') root.setAttribute('data-theme', t);
    } catch (e) { /* private mode: stay on auto */ }
})();
