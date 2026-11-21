/**
 * Resumable lesson upload: 1 MB pieces, retries on a weak connection, and
 * picks up where it stopped if the page is closed or the signal drops.
 */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function api(url, options = {}) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        ...options,
        headers: { 'X-CSRF-Token': csrfToken(), ...(options.headers || {}) },
    });
    let data = {};
    try {
        data = await res.json();
    } catch {
        // Non-JSON reply (e.g. hosting error page).
    }
    return { status: res.status, data };
}

async function sha256Hex(blob) {
    const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
    return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/**
 * @param {Blob} blob the .scl lesson file
 * @param {{clientKey: string, title: string, onProgress?: (sent: number, total: number, note?: string) => void}} opts
 * @returns {Promise<number>} the new lesson's id on the site
 */
export async function uploadLesson(blob, { clientKey, title, onProgress = () => {} }) {
    if (!window.crypto?.subtle) {
        throw new Error('Uploading needs a secure (https://) connection.');
    }
    onProgress(0, blob.size, 'Checking the file…');
    const sha256 = await sha256Hex(blob);

    const start = await api('/studio/uploads', {
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client_key: clientKey, title, size: blob.size, sha256 }),
    });
    if (start.status !== 200 || !start.data.ok) {
        throw new Error(start.data.error || `The site refused the upload (error ${start.status}).`);
    }
    if (start.data.lesson_id) return start.data.lesson_id; // already uploaded earlier

    const { upload_id: uploadId, chunk_size: chunkSize } = start.data;
    let offset = start.data.received_bytes || 0;
    let failures = 0;

    while (offset < blob.size) {
        onProgress(offset, blob.size, failures ? 'Weak connection, retrying…' : '');
        const piece = blob.slice(offset, Math.min(offset + chunkSize, blob.size));
        let res;
        try {
            res = await api(`/studio/uploads/${uploadId}/chunk?offset=${offset}`, {
                headers: { 'Content-Type': 'application/octet-stream' },
                body: piece,
            });
        } catch {
            res = { status: 0, data: {} }; // network error
        }

        if (res.status === 200 && res.data.ok) {
            offset = res.data.received_bytes;
            failures = 0;
        } else if (res.status === 409 && typeof res.data.received_bytes === 'number') {
            offset = res.data.received_bytes; // the site has a different position; continue from there
        } else if (res.status === 0 || res.status >= 500) {
            failures++;
            if (failures > 8) throw new Error('The connection keeps dropping. Your lesson is safe on this device; try Upload again later.');
            await sleep(Math.min(30000, 1000 * 2 ** failures));
        } else {
            throw new Error(res.data.error || `Upload failed (error ${res.status}).`);
        }
    }

    onProgress(blob.size, blob.size, 'Finishing…');
    const done = await api(`/studio/uploads/${uploadId}/finish`);
    if (done.status !== 200 || !done.data.ok) {
        throw new Error(done.data.error || `Could not finish the upload (error ${done.status}).`);
    }
    return done.data.lesson_id;
}
