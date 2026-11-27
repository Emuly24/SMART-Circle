/* "Quote" on a message fills the reply form with that message, without reloading the page. */
(function () {
    var form = document.getElementById('reply');
    if (!form) { return; }
    var idField = form.querySelector('.js-quote-id');
    var box = form.querySelector('.reply-quote');
    var text = form.querySelector('.js-quote-text');
    var clear = form.querySelector('.js-quote-clear');
    var textarea = form.querySelector('textarea');
    document.querySelectorAll('[data-quote-id]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            idField.value = link.getAttribute('data-quote-id');
            text.textContent = '';
            var who = document.createElement('span');
            who.className = 'msg__quote-who';
            who.textContent = link.getAttribute('data-quote-name') + ' wrote:';
            text.appendChild(who);
            text.appendChild(document.createTextNode(' ' + link.getAttribute('data-quote-text')));
            box.classList.remove('is-empty');
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            textarea.focus({ preventScroll: true });
        });
    });
    clear.addEventListener('click', function (event) {
        event.preventDefault();
        idField.value = '';
        text.textContent = '';
        box.classList.add('is-empty');
    });
})();
