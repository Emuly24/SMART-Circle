/**
 * Lesson file format (.scl) and on-device storage.
 *
 * File layout (binary, little-endian):
 *   bytes 0-3   "SCL1" magic
 *   bytes 4-7   header length N (uint32)
 *   next N      UTF-8 JSON header: { version, page, durationMs, audioMime, events, title, createdAt }
 *   rest        audio bytes (MP4/AAC or WebM/Opus), may be empty
 *
 * Strokes are tiny compared with audio, so a lesson is about the size of its voice track.
 */

const MAGIC = 'SCL1';

/**
 * @param {{title?: string, durationMs: number, events: object[], audioMime: string}} lesson
 * @param {Blob|null} audio
 * @returns {Blob}
 */
export function encodeLesson(lesson, audio) {
    const header = new TextEncoder().encode(JSON.stringify({
        version: 1,
        page: { width: 1600, height: 1000 },
        createdAt: new Date().toISOString(),
        ...lesson,
    }));
    const prefix = new Uint8Array(8);
    prefix.set(new TextEncoder().encode(MAGIC), 0);
    new DataView(prefix.buffer).setUint32(4, header.length, true);

    return new Blob([prefix, header, audio || new Blob()], { type: 'application/octet-stream' });
}

/** @returns {Promise<{lesson: object, audio: Blob|null}>} */
export async function decodeLesson(blob) {
    const head = new Uint8Array(await blob.slice(0, 8).arrayBuffer());
    if (new TextDecoder().decode(head.slice(0, 4)) !== MAGIC) {
        throw new Error('This is not a SMART Circle lesson file.');
    }
    const headerLength = new DataView(head.buffer).getUint32(4, true);
    const headerBytes = await blob.slice(8, 8 + headerLength).arrayBuffer();
    const lesson = JSON.parse(new TextDecoder().decode(headerBytes));
    const audioBlob = blob.slice(8 + headerLength, blob.size, lesson.audioMime || '');

    return { lesson, audio: audioBlob.size > 0 ? audioBlob : null };
}

/* ---------- IndexedDB: lessons survive closing the tab or losing signal ---------- */

const DB_NAME = 'smartcircle-board';
const STORE = 'lessons';

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'id' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function tx(mode, fn) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
        const t = db.transaction(STORE, mode);
        const result = fn(t.objectStore(STORE));
        t.oncomplete = () => resolve(result?.result ?? result);
        t.onerror = () => reject(t.error);
    });
}

/** Save (or overwrite) a lesson draft on this device. */
export function saveLocal(id, title, blob) {
    return tx('readwrite', (s) => s.put({ id, title, blob, savedAt: Date.now(), size: blob.size }));
}

/** @returns {Promise<Array<{id: string, title: string, savedAt: number, size: number}>>} */
export async function listLocal() {
    const rows = await tx('readonly', (s) => s.getAll());
    return (rows || []).map(({ id, title, savedAt, size }) => ({ id, title, savedAt, size })).sort((a, b) => b.savedAt - a.savedAt);
}

export async function loadLocal(id) {
    const row = await tx('readonly', (s) => s.get(id));
    return row ? row.blob : null;
}

export function deleteLocal(id) {
    return tx('readwrite', (s) => s.delete(id));
}
