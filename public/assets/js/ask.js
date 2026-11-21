// Topic request form: show the "why now?" box only when the school has not covered the topic.
(() => {
    const form = document.getElementById('ask');
    const box = form && form.querySelector('.js-exception-reason');
    if (!box) return;
    const update = () => {
        const radio = form.querySelector('.js-not-covered');
        const notCovered = radio !== null && radio.checked;
        box.hidden = !notCovered;
    };
    form.querySelectorAll('input[name="covered"]').forEach((r) => r.addEventListener('change', update));
    update();
})();
