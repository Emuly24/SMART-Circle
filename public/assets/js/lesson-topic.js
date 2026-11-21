// Lesson edit: only offer topics from the chosen form and subject.
(() => {
    const select = document.querySelector('.js-topic-select');
    if (!select) return;
    const form = select.form.querySelector('select[name="form"]');
    const subject = select.form.querySelector('select[name="subject"]');
    const update = () => {
        for (const opt of select.options) {
            if (!opt.dataset.form) continue;
            const match = opt.dataset.form === form.value && opt.dataset.subject === subject.value;
            opt.hidden = !match;
            opt.disabled = !match;
            if (!match && opt.selected) select.value = '0';
        }
    };
    form.addEventListener('change', update);
    subject.addEventListener('change', update);
    update();
})();
