/*
 * Shrinks a chosen profile photo on the device before upload (saves data and stays within
 * hosting limits). If the browser cannot, the original is sent and the server shrinks it.
 */
(function () {
    var form = document.querySelector('form.js-photo-form');
    if (!form || !window.HTMLCanvasElement || typeof DataTransfer === 'undefined') { return; }
    var input = form.querySelector('input[type=file]');
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file || !/^image\//.test(file.type)) { return; }
        var img = new Image();
        img.onload = function () {
            var side = Math.min(img.naturalWidth, img.naturalHeight);
            var size = 512;
            var canvas = document.createElement('canvas');
            canvas.width = size; canvas.height = size;
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, size, size);
            ctx.drawImage(img, (img.naturalWidth - side) / 2, (img.naturalHeight - side) / 2, side, side, 0, 0, size, size);
            canvas.toBlob(function (blob) {
                if (!blob) { return; }
                var dt = new DataTransfer();
                dt.items.add(new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
                input.files = dt.files;
            }, 'image/jpeg', 0.85);
            URL.revokeObjectURL(img.src);
        };
        img.src = URL.createObjectURL(file);
    });
})();
