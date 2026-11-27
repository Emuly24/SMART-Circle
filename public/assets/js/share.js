/* SMART Circle: the phone's own share menu (when it has one) and "Copy link". */
(function () {
    'use strict';
    document.querySelectorAll('.js-share').forEach(function (box) {
        var url = box.getAttribute('data-url');
        var text = box.getAttribute('data-text');
        var native = box.querySelector('.js-share-native');
        if (native && navigator.share) {
            native.hidden = false;
            native.addEventListener('click', function () {
                navigator.share({ title: 'SMART Circle', text: text, url: url }).catch(function () {});
            });
        }
        var copy = box.querySelector('.js-share-copy');
        var done = box.querySelector('.js-share-done');
        if (copy) {
            copy.addEventListener('click', function () {
                var ok = function () { if (done) { done.hidden = false; } };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(ok, function () { window.prompt('Copy this link:', url); });
                } else {
                    window.prompt('Copy this link:', url);
                }
            });
        }
    });
}());
