/*
 * "Choose from contacts" next to phone and email fields marked data-contacts="tel|email".
 * Only some browsers let websites open the contact book (Chrome on Android); elsewhere
 * nothing is added and the field is typed as usual.
 */
(function () {
    if (!('contacts' in navigator) || !('ContactsManager' in window) || typeof navigator.contacts.select !== 'function') {
        return;
    }
    document.querySelectorAll('input[data-contacts]').forEach(function (input) {
        var kind = input.getAttribute('data-contacts') === 'email' ? 'email' : 'tel';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn--small btn--ghost contact-pick';
        button.textContent = 'Choose from contacts';
        button.addEventListener('click', function () {
            var props = [kind, 'name'];
            navigator.contacts.select(props, { multiple: false }).then(function (picked) {
                if (!picked || !picked.length) { return; }
                var c = picked[0];
                var value = (c[kind] || [])[0];
                if (value) {
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
                var nameField = input.getAttribute('data-contacts-name');
                var target = nameField ? input.form && input.form.elements.namedItem(nameField) : null;
                if (target && !target.value && c.name && c.name[0]) {
                    target.value = c.name[0];
                }
            }).catch(function () { /* the person closed the picker */ });
        });
        input.insertAdjacentElement('afterend', button);
    });
})();
