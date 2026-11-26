// Sign-up: the travel question only matters for day scholars and evening-class students.
(() => {
    const travel = document.querySelector('.js-travel');
    if (!travel) return;
    const radios = document.querySelectorAll('.js-study-mode');
    const update = () => {
        const mode = Array.from(radios).find((r) => r.checked)?.value || '';
        travel.hidden = !(mode === 'day' || mode === 'evening' || mode === '');
    };
    radios.forEach((r) => r.addEventListener('change', update));
    update();
})();
