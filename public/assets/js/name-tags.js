/*
 * One-click name tags: a button types {first_name}, {title_surname} ... into the message box beside it,
 * at the cursor. Each reader later sees their own name there.
 */
(function () {
    document.querySelectorAll('.js-name-tags').forEach(function (bar) {
        var form = bar.closest('form');
        var box = form ? form.querySelector('textarea') : null;
        if (!box) { return; }
        bar.hidden = false;
        bar.querySelectorAll('[data-name-tag]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tag = '{' + btn.getAttribute('data-name-tag') + '}';
                var start = box.selectionStart, end = box.selectionEnd, value = box.value;
                var before = value.slice(0, start), after = value.slice(end);
                // A space before and after, unless there is already one (or punctuation follows).
                if (before !== '' && !/[\s(“"']$/.test(before)) { tag = ' ' + tag; }
                if (after === '' || !/^[\s,.!?;:)”"']/.test(after)) { tag = tag + (after === '' ? '' : ' '); }
                box.value = before + tag + after;
                var caret = before.length + tag.length;
                box.focus();
                box.setSelectionRange(caret, caret);
                box.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
    });
})();
