/* Application form helpers: subject rules by track, and shrinking report photos to save data. */
(function () {
    const form = document.getElementById('apply-form');
    if (!form) return;

    const formSelect = document.getElementById('form-select');
    const taken = () => Array.from(form.querySelectorAll('input[name="subjects_taken[]"]:checked')).map((i) => i.value);
    const note = document.getElementById('track-note');

    // Mirrors Subjects::track() on the server (the server always re-checks).
    function updateHelpSubjects() {
        const f = Number(formSelect.value || 0);
        const t = taken();
        const science = t.includes('physics') && t.includes('chemistry');
        const allowed = f >= 3 && !science ? ['mathematics', 'biology', 'english'] : ['mathematics', 'physics', 'chemistry', 'biology', 'english'];
        form.querySelectorAll('[data-help]').forEach((label) => {
            const key = label.dataset.help;
            const input = label.querySelector('input');
            const ok = allowed.includes(key) && t.includes(key);
            input.disabled = !ok;
            if (!ok) input.checked = false;
            label.classList.toggle('is-disabled', !ok);
        });
        if (note) {
            note.textContent = f >= 3
                ? (science
                    ? 'Science track: you can get help in all five subjects you take.'
                    : 'Humanities track: you can get help in Mathematics, Biology and English grammar.')
                : 'Forms 1–2: you can get help in any of the five subjects you take.';
        }
    }
    formSelect.addEventListener('change', updateHelpSubjects);
    form.querySelectorAll('input[name="subjects_taken[]"]').forEach((i) => i.addEventListener('change', updateHelpSubjects));
    updateHelpSubjects();

    // Shrink big photos (phones take 3-8 MB pictures) to about 1600 px JPEG before upload.
    const report = document.getElementById('report');
    const reportNote = document.getElementById('report-note');
    if (report && window.DataTransfer && window.createImageBitmap) {
        report.addEventListener('change', async () => {
            const file = report.files && report.files[0];
            if (!file || !/^image\/(jpeg|png)$/.test(file.type) || file.size < 400 * 1024) return;
            try {
                reportNote.textContent = 'Making the photo smaller…';
                const bitmap = await createImageBitmap(file);
                const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(bitmap.width * scale);
                canvas.height = Math.round(bitmap.height * scale);
                canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.75));
                if (!blob || blob.size >= file.size) {
                    reportNote.textContent = 'Photo ready.';
                    return;
                }
                const dt = new DataTransfer();
                dt.items.add(new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
                report.files = dt.files;
                reportNote.textContent = `Photo ready: ${(blob.size / 1024).toFixed(0)} KB (was ${(file.size / 1048576).toFixed(1)} MB).`;
            } catch (e) {
                reportNote.textContent = 'Photo ready.';
            }
        });
    }

    // Prevent double submission on slow connections.
    form.addEventListener('submit', () => {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Sending…';
        }
    });
})();
