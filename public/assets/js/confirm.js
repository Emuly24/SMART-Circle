/* Ask before submitting dangerous forms: <form class="js-confirm" data-confirm="Are you sure?"> */
document.querySelectorAll('form.js-confirm').forEach((form) => {
    form.addEventListener('submit', (e) => {
        if (!window.confirm(form.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
});
