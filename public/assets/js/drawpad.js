/*
 * Drawing pad for notes: sketch a diagram, graph or circuit with a finger, mouse or Apple Pencil,
 * then add it to the notes as a picture (sent through the normal "Add a picture" upload).
 */
(function () {
    var open = document.querySelector('.js-drawpad-open');
    if (!open || !window.HTMLCanvasElement || typeof FormData === 'undefined') { return; }
    open.hidden = false;
    var W = 1200, H = 750;
    var COLORS = { Black: '#14213D', Blue: '#1D4E89', Green: '#157A52', Red: '#C62828' };

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        if (text) { n.textContent = text; }
        return n;
    }

    open.addEventListener('click', function () {
        var strokes = [], current = null, color = COLORS.Black, width = 5, erasing = false;
        var panel = el('div', 'drawpad');
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Drawing pad');
        var bar = el('div', 'drawpad__bar');
        Object.keys(COLORS).forEach(function (name) {
            var b = el('button', 'drawpad__swatch', name);
            b.type = 'button';
            b.setAttribute('data-color', COLORS[name]);
            b.addEventListener('click', function () { color = COLORS[name]; erasing = false; mark(b); });
            bar.appendChild(b);
        });
        var thin = el('button', 'drawpad__tool', 'Thin'), thick = el('button', 'drawpad__tool', 'Thick'), rub = el('button', 'drawpad__tool', 'Rubber');
        [thin, thick, rub].forEach(function (b) { b.type = 'button'; bar.appendChild(b); });
        thin.addEventListener('click', function () { width = 3; });
        thick.addEventListener('click', function () { width = 9; });
        rub.addEventListener('click', function () { erasing = true; mark(rub); });
        // Shapes: the board's ready-made illustrations and structural formulas, placed with a tap.
        var stamp = null, stamps = null;
        var shapeSel = el('select', 'drawpad__shapes');
        shapeSel.setAttribute('aria-label', 'Add a shape');
        shapeSel.appendChild(el('option', null, 'Shape…'));
        var sizeSel = el('select', 'drawpad__size');
        sizeSel.setAttribute('aria-label', 'Shape size');
        [['160', 'Small'], ['260', 'Medium'], ['400', 'Large']].forEach(function (o) {
            var opt = el('option', null, o[1]);
            opt.value = o[0];
            if (o[0] === '260') { opt.selected = true; }
            sizeSel.appendChild(opt);
        });
        bar.appendChild(shapeSel);
        bar.appendChild(sizeSel);
        import('/assets/js/board/board-stamps.js').then(function (mod) {
            stamps = mod;
            mod.GROUPS.forEach(function (g) {
                var og = document.createElement('optgroup');
                og.label = g;
                Object.keys(mod.STAMPS).forEach(function (k) {
                    if (mod.STAMPS[k].group === g) {
                        var opt = el('option', null, mod.STAMPS[k].name);
                        opt.value = k;
                        og.appendChild(opt);
                    }
                });
                shapeSel.appendChild(og);
            });
        }).catch(function () { shapeSel.hidden = true; sizeSel.hidden = true; });
        shapeSel.addEventListener('change', function () {
            stamp = shapeSel.value && stamps ? shapeSel.value : null;
            if (stamp) { erasing = false; msg.textContent = 'Tap the drawing where the shape should go.'; }
        });

        var undo = el('button', 'drawpad__tool', 'Undo'), clear = el('button', 'drawpad__tool', 'Clear');
        [undo, clear].forEach(function (b) { b.type = 'button'; bar.appendChild(b); });
        undo.addEventListener('click', function () { strokes.pop(); draw(); });
        clear.addEventListener('click', function () { if (strokes.length === 0 || window.confirm('Clear the drawing?')) { strokes = []; draw(); } });

        var canvas = el('canvas', 'drawpad__canvas');
        canvas.width = W;
        canvas.height = H;
        var ctx = canvas.getContext('2d');

        var foot = el('div', 'drawpad__foot');
        var caption = el('input', 'drawpad__caption');
        caption.type = 'text';
        caption.maxLength = 500;
        caption.placeholder = 'Caption (optional)';
        var where = el('select', 'drawpad__align');
        [['center', 'Centre'], ['left', 'Left'], ['right', 'Right']].forEach(function (o) {
            var opt = el('option', null, o[1]);
            opt.value = o[0];
            where.appendChild(opt);
        });
        var save = el('button', 'btn btn--small', 'Add to the notes'), cancel = el('button', 'btn btn--small btn--ghost', 'Cancel');
        save.type = 'button';
        cancel.type = 'button';
        var msg = el('p', 'drawpad__msg');
        msg.setAttribute('aria-live', 'polite');
        [caption, where, save, cancel, msg].forEach(function (n) { foot.appendChild(n); });
        panel.appendChild(bar);
        panel.appendChild(canvas);
        panel.appendChild(foot);
        document.body.appendChild(panel);
        mark(bar.firstChild);
        draw();

        function mark(btn) {
            bar.querySelectorAll('.is-active').forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
        }
        function pos(e) {
            var r = canvas.getBoundingClientRect();
            return [(e.clientX - r.left) * W / r.width, (e.clientY - r.top) * H / r.height, e.pointerType === 'pen' && e.pressure > 0 ? e.pressure : 0.6];
        }
        function draw() {
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, W, H);
            strokes.forEach(function (s) {
                if (s.shape) {
                    ctx.save();
                    ctx.strokeStyle = s.color;
                    ctx.fillStyle = s.color;
                    ctx.lineWidth = s.width;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    s.shape.paths.forEach(function (path) {
                        ctx.beginPath();
                        path.forEach(function (pt, i) { if (i === 0) { ctx.moveTo(pt[0], pt[1]); } else { ctx.lineTo(pt[0], pt[1]); } });
                        ctx.stroke();
                    });
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    s.shape.labels.forEach(function (l) {
                        ctx.font = '700 ' + l.size + 'px Inter, system-ui, sans-serif';
                        ctx.fillText(l.text, l.x, l.y);
                    });
                    ctx.restore();
                    return;
                }
                ctx.strokeStyle = s.erase ? '#FFFFFF' : s.color;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                for (var i = 1; i < s.points.length; i++) {
                    var a = s.points[i - 1], b = s.points[i];
                    ctx.lineWidth = s.erase ? 28 : s.width * (0.5 + b[2]);
                    ctx.beginPath();
                    ctx.moveTo(a[0], a[1]);
                    ctx.lineTo(b[0], b[1]);
                    ctx.stroke();
                }
                if (s.points.length === 1 && !s.erase) {
                    ctx.fillStyle = s.color;
                    ctx.beginPath();
                    ctx.arc(s.points[0][0], s.points[0][1], s.width / 2, 0, Math.PI * 2);
                    ctx.fill();
                }
            });
        }
        canvas.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            try { canvas.setPointerCapture(e.pointerId); } catch (err) { /* quick taps */ }
            if (stamp && stamps) {
                var p = pos(e);
                var built = stamps.buildShape(stamp, p[0], p[1], Number(sizeSel.value), color, Math.max(2, Math.round(width * 0.6)), 'd' + strokes.length, 0);
                if (built) { strokes.push({ shape: built, color: color, width: Math.max(2, Math.round(width * 0.6)), points: [] }); }
                stamp = null;
                shapeSel.value = shapeSel.options[0].value;
                msg.textContent = '';
                draw();
                return;
            }
            current = { color: color, width: width, erase: erasing, points: [pos(e)] };
            strokes.push(current);
            draw();
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!current) { return; }
            e.preventDefault();
            current.points.push(pos(e));
            draw();
        });
        ['pointerup', 'pointercancel'].forEach(function (t) { canvas.addEventListener(t, function () { current = null; }); });
        canvas.addEventListener('touchstart', function (e) { e.preventDefault(); }, { passive: false });

        cancel.addEventListener('click', function () {
            if (strokes.length === 0 || window.confirm('Close without adding the drawing?')) { panel.remove(); }
        });
        save.addEventListener('click', function () {
            if (strokes.length === 0) { msg.textContent = 'Draw something first.'; return; }
            var token = document.querySelector('input[name="_token"]');
            save.disabled = true;
            msg.textContent = 'Adding…';
            canvas.toBlob(function (blob) {
                var data = new FormData();
                data.append('_token', token ? token.value : '');
                data.append('image', blob, 'drawing.png');
                data.append('caption', caption.value);
                data.append('align', where.value);
                fetch(open.getAttribute('data-action'), { method: 'POST', body: data, credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r.ok) { throw new Error(String(r.status)); }
                        window.location.href = window.location.pathname + '#add';
                        window.location.reload();
                    })
                    .catch(function () { msg.textContent = 'The drawing could not be added. Please try again.'; save.disabled = false; });
            }, 'image/png');
        });
    });
})();
