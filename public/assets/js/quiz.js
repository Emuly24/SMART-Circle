/* Entry quiz countdown. The server also enforces the time limit. */
(function () {
    const timer = document.getElementById('quiz-timer');
    const form = document.getElementById('quiz-form');
    if (!timer || !form) return;

    const minutes = Number(timer.dataset.minutes);
    // Use the server's clock difference so a wrong phone clock does not matter.
    const offset = Number(timer.dataset.now) * 1000 - Date.now();
    const deadline = Number(timer.dataset.started) * 1000 + minutes * 60000;
    let submitted = false;

    function tick() {
        const left = Math.max(0, deadline - (Date.now() + offset));
        const m = Math.floor(left / 60000);
        const s = Math.floor((left % 60000) / 1000);
        timer.textContent = left > 0 ? `Time left: ${m}:${String(s).padStart(2, '0')}` : 'Time is up: sending your answers…';
        timer.classList.toggle('is-low', left < 120000);
        if (left <= 0 && !submitted) {
            submitted = true;
            form.submit();
            return;
        }
        setTimeout(tick, 1000);
    }
    tick();

    form.addEventListener('submit', () => {
        submitted = true;
    });
})();
