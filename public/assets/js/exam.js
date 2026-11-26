// Exam page: countdown (the server keeps the real deadline), auto-submit at zero,
// confirm before the final submit, and a warning before leaving with unsaved answers.
(() => {
    const form = document.getElementById('exam');
    if (!form) return;
    let dirty = false;
    let leaving = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('submit', (e) => {
        const submitter = e.submitter;
        if (submitter && submitter.classList.contains('js-exam-submit') && !form.dataset.timeUp
            && !window.confirm('Submit your answers now? You cannot change them afterwards.')) {
            e.preventDefault();
            return;
        }
        leaving = true;
    });
    window.addEventListener('beforeunload', (e) => {
        if (dirty && !leaving) { e.preventDefault(); e.returnValue = ''; }
    });

    const timer = document.querySelector('.js-exam-timer');
    if (!timer) return;
    const label = timer.querySelector('span');
    const end = Date.now() + Number(timer.dataset.seconds) * 1000;
    const pad = (n) => String(n).padStart(2, '0');
    const tick = () => {
        const left = Math.max(0, Math.round((end - Date.now()) / 1000));
        label.textContent = `${pad(Math.floor(left / 3600))}:${pad(Math.floor(left / 60) % 60)}:${pad(left % 60)}`;
        timer.classList.toggle('is-low', left <= 300);
        if (left === 0) {
            form.dataset.timeUp = '1';
            form.querySelector('.js-exam-action').value = 'submit';
            leaving = true;
            form.submit();   // plain submit: the hidden action field says "submit"
            return;
        }
        window.setTimeout(tick, 1000);
    };
    tick();
})();
