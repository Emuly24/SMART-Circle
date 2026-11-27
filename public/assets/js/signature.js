/* SMART Circle: draw a signature on a canvas; the picture is sent as a PNG with the form. */
(function () {
    'use strict';
    var form = document.querySelector('.js-signature-form');
    if (!form) { return; }
    var pad = form.querySelector('.js-signature-pad');
    var canvas = form.querySelector('.js-signature-canvas');
    var out = form.querySelector('.js-signature-data');
    if (!pad || !canvas || !canvas.getContext || !window.PointerEvent) { return; }
    pad.hidden = false;
    var ctx = canvas.getContext('2d');
    var drawing = false, drawn = false, last = null;
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#14213D';

    function point(e) {
        var r = canvas.getBoundingClientRect();
        return { x: (e.clientX - r.left) * canvas.width / r.width, y: (e.clientY - r.top) * canvas.height / r.height };
    }
    canvas.addEventListener('pointerdown', function (e) {
        drawing = true; last = point(e); canvas.setPointerCapture(e.pointerId); e.preventDefault();
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!drawing) { return; }
        var p = point(e);
        ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        last = p; drawn = true; e.preventDefault();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) {
        canvas.addEventListener(t, function () { drawing = false; });
    });
    form.querySelector('.js-signature-clear').addEventListener('click', function () {
        ctx.clearRect(0, 0, canvas.width, canvas.height); drawn = false; out.value = '';
    });
    form.addEventListener('submit', function () {
        out.value = drawn ? canvas.toDataURL('image/png') : '';
    });
}());
