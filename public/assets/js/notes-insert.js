/*
 * Notes editor "Insert" toolbar: types notes markup (tables, symbols, equations, fractions, boxes,
 * reference tables) into the text box next to it, at the cursor.
 */
(function () {
    document.querySelectorAll('.js-insert-bar').forEach(function (bar) {
        var form = bar.closest('form');
        var box = form ? form.querySelector('textarea') : null;
        if (!box) { return; }
        bar.hidden = false;
        var symbols = bar.parentNode.querySelector('.js-pop-symbols');
        var table = bar.parentNode.querySelector('.js-pop-table');

        function put(text, opts) {
            opts = opts || {};
            var start = box.selectionStart, end = box.selectionEnd, value = box.value;
            var before = value.slice(0, start), chosen = value.slice(start, end), after = value.slice(end);
            if (opts.line && before !== '' && !/\n$/.test(before)) { text = '\n' + text; }
            if (opts.block) {
                if (before !== '' && !/\n\n$/.test(before)) { text = (/\n$/.test(before) ? '\n' : '\n\n') + text; }
                if (after !== '' && !/^\n/.test(after)) { text = text + '\n'; }
            }
            var caret;
            if (text.indexOf('|') !== -1 && opts.wrap) {
                var parts = text.split('|');
                var middle = chosen || (parts[0] === '**' ? 'bold words' : 'a');
                text = parts[0] + middle + parts.slice(1).join('|');
                caret = before.length + parts[0].length + middle.length;
                box.value = before + text + after;
                box.setSelectionRange(before.length + parts[0].length, caret);
            } else {
                box.value = before + text + after;
                caret = before.length + text.length;
                box.setSelectionRange(caret, caret);
            }
            box.focus();
            box.dispatchEvent(new Event('input', { bubbles: true }));
        }

        bar.querySelectorAll('[data-wrap]').forEach(function (b) {
            b.addEventListener('click', function () { put(b.getAttribute('data-wrap'), { wrap: true }); });
        });
        bar.querySelectorAll('[data-insert]').forEach(function (b) {
            b.addEventListener('click', function () { put(b.getAttribute('data-insert'), { line: b.hasAttribute('data-line') }); });
        });
        bar.querySelectorAll('[data-pop]').forEach(function (b) {
            b.addEventListener('click', function () {
                var pop = b.getAttribute('data-pop') === 'symbols' ? symbols : table;
                var other = pop === symbols ? table : symbols;
                if (other) { other.hidden = true; }
                if (pop) { pop.hidden = !pop.hidden; }
            });
        });
        bar.querySelector('.js-insert-box').addEventListener('change', function (e) {
            if (e.target.value) { put(e.target.value, { line: true }); }
            e.target.value = '';
        });
        bar.querySelector('.js-insert-ref').addEventListener('change', function (e) {
            if (e.target.value) { put('[table: ' + e.target.value + ']', { block: true }); }
            e.target.value = '';
        });
        if (symbols) {
            symbols.querySelectorAll('[data-sym]').forEach(function (s) {
                s.addEventListener('click', function () { put(s.getAttribute('data-sym')); });
            });
        }
        if (table) {
            table.querySelector('.js-table-insert').addEventListener('click', function () {
                var cols = Math.max(1, Math.min(8, parseInt(table.querySelector('.js-table-cols').value, 10) || 3));
                var rows = Math.max(1, Math.min(30, parseInt(table.querySelector('.js-table-rows').value, 10) || 3));
                var head = [], sep = [], lines = [];
                for (var c = 1; c <= cols; c++) { head.push('Heading ' + c); sep.push('---'); }
                lines.push('| ' + head.join(' | ') + ' |', '| ' + sep.join(' | ') + ' |');
                for (var r = 0; r < rows; r++) {
                    var cells = [];
                    for (var k = 0; k < cols; k++) { cells.push('   '); }
                    lines.push('|' + cells.join('|') + '|');
                }
                put(lines.join('\n'), { block: true });
                table.hidden = true;
            });
        }
    });
})();
