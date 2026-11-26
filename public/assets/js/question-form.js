// Question form: show only the fields for the chosen type (all stay visible without JavaScript).
(() => {
    const select = document.querySelector('.js-question-type');
    if (!select) return;
    const sets = document.querySelectorAll('.type-fields');
    const update = () => sets.forEach((s) => { s.hidden = s.dataset.type !== select.value; });
    select.addEventListener('change', update);
    update();
})();
