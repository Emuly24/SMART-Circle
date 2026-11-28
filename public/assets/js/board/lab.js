/**
 * Board lab page: record a lesson (strokes + voice), keep it on this device,
 * replay it, export it as a .scl file and open files recorded elsewhere.
 */

import { BoardState, COLORS, PAGE_H, PAGE_W, Renderer, drawShape } from './board-core.js';
import { buildShape, GROUPS, STAMPS, SUBJECTS } from './board-stamps.js';
import { canvasMeasure, emptyTablePages, layout, noteLines, tablePages } from './board-notes.js';
import { BoardInput } from './board-input.js';
import { LessonClock, VoiceRecorder } from './recorder.js';
import { decodeLesson, encodeLesson, listLocal, loadLocal, saveLocal, deleteLocal } from './lesson-file.js';
import { formatTime, LessonPlayer } from './player.js';
import { uploadLesson } from './lesson-upload.js';

const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => Array.from(document.querySelectorAll(sel));
const status = (text) => { $('#status').textContent = text; };

/* ---------------- Recording ---------------- */

const clock = new LessonClock();
const voice = new VoiceRecorder();
let events = [];
let recording = false;      // a recording session exists (running or paused)
let lessonId = null;
let autosaveTimer = null;
let pageCount = 1;

const boardCanvas = $('#board');
const renderer = new Renderer(boardCanvas);
const input = new BoardInput(boardCanvas, renderer, () => clock.now(), (event) => {
    if (recording) events.push(event);
});

input.onChange = () => {
    $('#fingers-draw').checked = input.fingersDraw;
    $('#page-label').textContent = `Page ${input.state.pageIndex + 1}`;
};
renderer.render(input.state);

// Laser trails fade over time, so keep repainting while any are visible.
(function animate() {
    if (input.state.lasers.length) renderer.render(input.state, Infinity, clock.now());
    requestAnimationFrame(animate);
})();

window.addEventListener('resize', () => {
    renderer.resize();
    renderer.render(input.state, Infinity, clock.now());
});

// Tools, colours, sizes
$$('[data-tool]').forEach((btn) => btn.addEventListener('click', () => {
    input.tool = btn.dataset.tool;
    $$('[data-tool]').forEach((b) => b.classList.toggle('is-active', b === btn));
    $('#shapes-open').classList.remove('is-active');
    status(btn.title.split(':')[0]);
}));
$$('[data-color]').forEach((btn) => btn.addEventListener('click', () => {
    input.color = COLORS[btn.dataset.color];
    if (input.tool === 'eraser' || input.tool === 'laser') $('[data-tool="pen"]').click();
    $$('[data-color]').forEach((b) => b.classList.toggle('is-active', b === btn));
}));
$$('[data-size]').forEach((btn) => btn.addEventListener('click', () => {
    input.penWidth = Number(btn.dataset.size);
    $$('[data-size]').forEach((b) => b.classList.toggle('is-active', b === btn));
}));
$('#undo').addEventListener('click', () => input.undo());
$('#redo').addEventListener('click', () => input.redo());
$('#clear').addEventListener('click', () => input.clearPage());
$('#page-prev').addEventListener('click', () => {
    if (input.state.pageIndex > 0) input.goToPage(input.state.pageIndex - 1);
});
$('#page-next').addEventListener('click', () => {
    const next = input.state.pageIndex + 1;
    pageCount = Math.max(pageCount, next + 1);
    input.goToPage(next);
});
$('#fingers-draw').addEventListener('change', (e) => { input.fingersDraw = e.target.checked; });

// Type tool: tap the board, type a line (text or an equation), press Enter.
const entry = $('#text-entry');
const entryInput = $('#text-input');
let entryPoint = null;
input.onTextRequest = (p) => {
    entryPoint = p;
    entry.hidden = false;
    entryInput.value = '';
    entryInput.focus();
};
const closeEntry = () => { entry.hidden = true; entryPoint = null; };
entry.addEventListener('submit', (e) => {
    e.preventDefault();
    if (entryPoint) input.addText(entryInput.value, [entryPoint[0], entryPoint[1], 0.5, Math.round(clock.now())]);
    closeEntry();
});
$('#text-cancel').addEventListener('click', closeEntry);
entryInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeEntry();
    // Enter starts a new line; Ctrl/Cmd + Enter adds the text.
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); entry.requestSubmit(); }
});
$('#text-size').addEventListener('change', (e) => { input.textSize = Number(e.target.value); });

/* ---------------- Panels: Shapes and Notes ---------------- */

const openPanel = (id) => {
    $$('.board-panel').forEach((panel) => { panel.hidden = panel.id !== id; });
};
$$('[data-close]').forEach((b) => b.addEventListener('click', () => { $('#' + b.dataset.close).hidden = true; }));

// Shapes: ready-made illustrations (circuit symbols, apparatus, ...). Pick one, then tap the board.
input.buildShape = buildShape;
(function buildShapesList() {
    const list = $('#shapes-list');
    for (const group of GROUPS) {
        const section = document.createElement('section');
        section.className = 'shapes-group';
        section.dataset.subject = group.split(' · ')[0];
        const h = document.createElement('h3');
        h.textContent = group;
        const grid = document.createElement('div');
        grid.className = 'shapes-grid';
        for (const [key, def] of Object.entries(STAMPS).filter(([, d]) => d.group === group)) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'shape-btn';
            btn.dataset.stamp = key;
            btn.dataset.find = `${def.name} ${group}`.toLowerCase();
            const cv = document.createElement('canvas');
            cv.width = 128;
            cv.height = 128;
            const ctx = cv.getContext('2d');
            ctx.fillStyle = '#FFFFFF';
            ctx.fillRect(0, 0, 128, 128);
            drawShape(ctx, buildShape(key, 64, 64, 112, COLORS.ink, 3, key, 0), Infinity);
            const label = document.createElement('span');
            label.textContent = def.name;
            btn.append(cv, label);
            btn.addEventListener('click', () => {
                input.stampKey = key;
                input.tool = 'stamp';
                $$('[data-tool]').forEach((b) => b.classList.remove('is-active'));
                $('#shapes-open').classList.add('is-active');
                $$('.shape-btn').forEach((b) => b.classList.toggle('is-active', b === btn));
                $('#shapes-panel').hidden = true;
                status(`${def.name}: tap the board where it should go. Choose Pen to draw again.`);
            });
            grid.append(btn);
        }
        section.append(h, grid);
        list.append(section);
    }
    const subject = $('#shapes-subject');
    for (const name of SUBJECTS) subject.append(new Option(name, name));
    const search = $('#shapes-search');
    const filter = () => {
        const want = subject.value;
        const words = search.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        $$('.shapes-group').forEach((sec) => {
            let shown = 0;
            sec.querySelectorAll('.shape-btn').forEach((b) => {
                const ok = (!want || sec.dataset.subject === want) && words.every((w) => b.dataset.find.includes(w));
                b.hidden = !ok;
                if (ok) shown++;
            });
            sec.hidden = shown === 0;
        });
    };
    subject.addEventListener('change', filter);
    search.addEventListener('input', filter);
})();
$('#shapes-open').addEventListener('click', () => openPanel('shapes-panel'));
$('#stamp-size').addEventListener('change', (e) => { input.stampBox = Number(e.target.value); });

// Notes: lay the text of written notes out on board pages, to record over them.
let notesPrelude = [];          // notes put on the board before recording: they can start the lesson
let notesLoaded = false;
const getJson = async (url) => {
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
};
$('#notes-open').addEventListener('click', async () => {
    openPanel('notes-panel');
    if (notesLoaded) return;
    const select = $('#notes-select');
    try {
        const data = await getJson('/studio/notes/board-list');
        select.innerHTML = '';
        if (data.notes.length === 0) select.append(new Option('No notes yet: write some in Notes & slides', ''));
        for (const n of data.notes) select.append(new Option(`Form ${n.form} · ${n.subject} · ${n.title}`, String(n.id)));
        notesLoaded = true;
    } catch {
        select.innerHTML = '';
        select.append(new Option('Your notes could not be loaded', ''));
    }
});
$('#notes-put').addEventListener('click', async () => {
    const id = $('#notes-select').value;
    if (!id) return;
    try {
        const note = await getJson(`/studio/notes/${encodeURIComponent(id)}/board`);
        putPages(layout(noteLines(note.title, note.blocks), canvasMeasure()), note.title);
        $('#notes-panel').hidden = true;
    } catch {
        status('Those notes could not be put on the board. Please try again.');
    }
});

/** Puts laid-out pages on the board: on this page if it is empty, else after the last page. */
function putPages(pages, title) {
    const used = input.state.strokes.some((st) => !input.state.removed.has(st.id));
    const start = used ? input.state.pages.length : input.state.pageIndex;
    const made = input.addPages(pages, start);
    input.goToPage(start);
    pageCount = Math.max(pageCount, start + pages.length);
    if (!recording) notesPrelude.push(...made);
    const n = pages.length;
    const shownTitle = title.replace(/[\^_]\{([^}]*)\}/g, '$1').replace(/[\^_]/g, '');
    status(`“${shownTitle}” is on ${n} board page${n === 1 ? '' : 's'}${n > 1 ? ' (▶ for the next)' : ''}.${recording ? '' : ' Press Record when ready.'}`);
}

// Tables: standard reference tables (enthalpies, periodic table, formulas, ...) or an empty table to fill in.
let tablesLoaded = false;
const SUBJECT_NAMES = { mathematics: 'Mathematics', physics: 'Physics', chemistry: 'Chemistry', biology: 'Biology', english: 'English grammar' };
$('#tables-open').addEventListener('click', async () => {
    openPanel('tables-panel');
    if (tablesLoaded) return;
    const select = $('#tables-select');
    try {
        const data = await getJson('/studio/reference');
        select.innerHTML = '';
        for (const [subject, list] of Object.entries(data.tables)) {
            const group = document.createElement('optgroup');
            group.label = SUBJECT_NAMES[subject] || subject;
            for (const [key, title] of Object.entries(list)) group.append(new Option(title, key));
            select.append(group);
        }
        tablesLoaded = true;
    } catch {
        select.innerHTML = '';
        select.append(new Option('The tables could not be loaded', ''));
    }
});
$('#tables-put').addEventListener('click', async () => {
    const key = $('#tables-select').value;
    if (!key) return;
    try {
        const table = await getJson(`/studio/reference/${encodeURIComponent(key)}`);
        putPages(tablePages(table, canvasMeasure()), table.title);
        $('#tables-panel').hidden = true;
    } catch {
        status('That table could not be put on the board. Please try again.');
    }
});
$('#tables-empty').addEventListener('click', () => {
    const cols = Math.max(1, Math.min(8, Number($('#tables-cols').value) || 3));
    const rows = Math.max(1, Math.min(12, Number($('#tables-rows').value) || 4));
    putPages(emptyTablePages(cols, rows), `Empty ${cols} × ${rows} table`);
    $('#tables-panel').hidden = true;
});

// Full screen: the board fills the screen (the top bar hides; the tools stay).
const refit = () => {
    renderer.resize();
    renderer.render(input.state, Infinity, clock.now());
};
$('#fullscreen').addEventListener('click', async () => {
    const on = !document.body.classList.contains('board-focus');
    document.body.classList.toggle('board-focus', on);
    const root = document.documentElement;
    try {
        if (on && root.requestFullscreen) await root.requestFullscreen();
        else if (on && root.webkitRequestFullscreen) root.webkitRequestFullscreen();
        else if (!on && document.fullscreenElement) await document.exitFullscreen();
    } catch {
        // Some tablets do not allow full screen for pages; hiding the top bar still gives more room.
    }
    refit();
});
document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement) document.body.classList.remove('board-focus');
    refit();
});
$('#text-centred').addEventListener('change', (e) => { input.textCentred = e.target.checked; });

// Save the current page as a picture (to add to notes or share).
$('#snapshot').addEventListener('click', () => {
    const canvas = document.createElement('canvas');
    canvas.width = PAGE_W;
    canvas.height = PAGE_H;
    const pic = new Renderer(canvas);
    pic.canvas.width = PAGE_W;
    pic.canvas.height = PAGE_H;
    pic.scale = 1;
    pic.offsetX = 0;
    pic.offsetY = 0;
    const state = new BoardState();
    state.pages = input.state.pages;
    state.pageIndex = input.state.pageIndex;
    state.removed = input.state.removed;
    pic.render(state);
    canvas.toBlob((blob) => {
        if (!blob) return;
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `board-page-${input.state.pageIndex + 1}.png`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        status('Page saved as a picture. Add it to notes with “Add a picture”.');
    }, 'image/png');
});

$('#rec').addEventListener('click', async () => {
    if (!recording) {
        await startRecording();
    } else if (clock.running) {
        clock.pause();
        voice.pause();
        setRecUi('paused');
    } else {
        voice.resume();
        clock.start();
        setRecUi('recording');
    }
});
$('#stop').addEventListener('click', stopRecording);

async function startRecording() {
    events = [];
    clock.reset();
    lessonId = `lesson-${Date.now()}`;
    let withVoice = false;

    if (voice.supported) {
        try {
            status('Allow the microphone to record your voice…');
            await voice.open();
            await voice.start();
            withVoice = true;
        } catch (err) {
            console.warn(err);
            if (!confirm('The microphone is not available. Record the board only, without voice?')) {
                status('Recording cancelled');
                return;
            }
        }
    } else if (!confirm('This browser cannot record audio. Record the board only?')) {
        return;
    }

    // Start from a clean board so the lesson is self-contained, keeping notes put on the board for it.
    const keepNotes = notesPrelude.length > 0 && confirm('Start the recording with the notes you put on the board?');
    input.state.reset();
    input.history = [];
    input.redoStack = [];
    pageCount = 1;
    if (keepNotes) {
        // Renumber so the notes start on page 1 of the lesson.
        const first = Math.min(...notesPrelude.filter((ev) => ev.type === 'page').map((ev) => ev.index));
        for (const ev of notesPrelude) {
            const copy = ev.type === 'page' ? { ...ev, t: 0, index: ev.index - first } : { ...ev, t: 0 };
            input.state.apply(copy);
            events.push(copy);
            if (copy.type === 'page') pageCount = Math.max(pageCount, copy.index + 1);
        }
        const back = { type: 'page', t: 0, index: 0 };
        input.state.apply(back);
        events.push(back);
    }
    notesPrelude = [];
    renderer.render(input.state);
    input.onChange();

    clock.start();
    recording = true;
    setRecUi('recording');
    status(withVoice ? `Recording voice (${voice.mime.split(';')[0]}) and board` : 'Recording board only');
    autosaveTimer = setInterval(autosave, 15000);
    tick();
}

async function stopRecording() {
    if (!recording) return;
    clock.pause();
    clearInterval(autosaveTimer);
    const audio = voice.recorder ? await voice.stop() : null;
    voice.close();
    recording = false;
    setRecUi('idle');

    const title = `Lesson ${new Date().toLocaleString()}`;
    const blob = encodeLesson({ title, durationMs: Math.round(clock.now()), events, audioMime: audio ? audio.type : '' }, audio);
    await saveLocal(lessonId, title, blob);
    status(`Saved on this device (${formatBytes(blob.size)})`);
    await refreshLibrary();
    switchMode('replay');
    await openLesson(blob, `${lessonId}.scl`);
}

async function autosave() {
    if (!recording) return;
    // Board strokes are always safe; audio so far is included as captured.
    const audio = new Blob(voice.chunks, { type: voice.mime });
    const blob = encodeLesson({ title: 'Unsaved recording (auto-saved)', durationMs: Math.round(clock.now()), events, audioMime: voice.mime }, audio.size ? audio : null);
    await saveLocal(`${lessonId}-autosave`, 'Unsaved recording (auto-saved)', blob);
}

function setRecUi(state) {
    const rec = $('#rec');
    rec.classList.toggle('is-recording', state === 'recording');
    rec.classList.toggle('is-paused', state === 'paused');
    $('#rec-label').textContent = state === 'recording' ? 'Pause' : state === 'paused' ? 'Resume' : 'Record';
    rec.setAttribute('aria-label', $('#rec-label').textContent);
    $('#stop').disabled = state === 'idle';
}

function tick() {
    if (!recording) return;
    const ms = clock.now();
    $('#rec-time').textContent = formatTime(ms);
    const audioBytes = voice.chunks.reduce((sum, c) => sum + c.size, 0);
    const strokeBytes = JSON.stringify(events).length;
    const perHour = ms > 10000 ? ((audioBytes + strokeBytes) / ms) * 3600000 : 0;
    $('#rec-size').textContent = `${formatBytes(audioBytes + strokeBytes)}${perHour ? ` · ≈${formatBytes(perHour)}/hour` : ''}`;
    setTimeout(tick, 500);
}

window.addEventListener('beforeunload', (e) => {
    if (recording) {
        e.preventDefault();
        e.returnValue = '';
    }
});

/* ---------------- Replay ---------------- */

let player = null;
let exportUrl = null;

$$('[data-mode]').forEach((btn) => btn.addEventListener('click', () => switchMode(btn.dataset.mode)));

function switchMode(mode) {
    if (mode === 'replay' && recording) {
        status('Stop the recording first');
        return;
    }
    $$('[data-mode]').forEach((b) => {
        const on = b.dataset.mode === mode;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', String(on));
    });
    $('#mode-record').hidden = mode !== 'record';
    $('#mode-replay').hidden = mode !== 'replay';
    if (mode === 'record') {
        player?.pause();
        renderer.resize();
        renderer.render(input.state);
    } else {
        refreshLibrary();
        player?.renderer.resize();
        player?.draw(player.time(), true);
    }
}

async function refreshLibrary() {
    const items = await listLocal();
    const list = $('#library');
    list.replaceChildren(...items.map((item) => {
        const li = document.createElement('li');
        const open = document.createElement('button');
        open.type = 'button';
        open.className = 'library__item';
        open.textContent = `${item.title} · ${formatBytes(item.size)}`;
        open.addEventListener('click', async () => openLesson(await loadLocal(item.id), `${item.id}.scl`));
        const del = document.createElement('button');
        del.type = 'button';
        del.className = 'library__delete';
        del.setAttribute('aria-label', `Delete ${item.title}`);
        del.textContent = '✕';
        del.addEventListener('click', async () => {
            if (confirm(`Delete "${item.title}" from this device?`)) {
                await deleteLocal(item.id);
                refreshLibrary();
            }
        });
        const up = document.createElement('button');
        up.type = 'button';
        up.className = 'library__upload-btn';
        const uploadedId = uploadedLessonId(item.id);
        up.textContent = uploadedId ? '✓ On site' : '⬆ Upload';
        up.setAttribute('aria-label', `Upload ${item.title} to the site`);
        up.disabled = item.id.endsWith('-autosave');
        up.addEventListener('click', () => uploadItem(item, up));
        li.append(open, up, del);
        return li;
    }));
    $('#library-empty').hidden = items.length > 0;
}

function uploadedLessonId(localId) {
    try {
        return localStorage.getItem(`sc-uploaded-${localId}`);
    } catch {
        return null;
    }
}

let uploading = false;
async function uploadItem(item, button) {
    if (uploading) return;
    const note = $('#upload-status');
    const existing = uploadedLessonId(item.id);
    if (existing) {
        window.location.href = `/studio/lessons/${existing}`;
        return;
    }
    uploading = true;
    button.disabled = true;
    note.hidden = false;
    try {
        const blob = await loadLocal(item.id);
        const lessonId = await uploadLesson(blob, {
            clientKey: item.id,
            title: item.title,
            onProgress: (sent, total, msg) => {
                const pct = total ? Math.round((sent / total) * 100) : 0;
                note.textContent = `Uploading "${item.title}": ${pct}% ${msg || ''}`;
                button.textContent = `${pct}%`;
            },
        });
        try {
            localStorage.setItem(`sc-uploaded-${item.id}`, String(lessonId));
        } catch {
            // Private mode: the site still has the lesson.
        }
        note.textContent = 'Uploaded. Add the real-life sections and publish it on the lesson page.';
        button.textContent = '✓ On site';
        status('Lesson uploaded to the site');
        const link = document.createElement('a');
        link.href = `/studio/lessons/${lessonId}`;
        link.className = 'library__link';
        link.textContent = 'Open lesson details →';
        note.append(' ', link);
    } catch (err) {
        console.error(err);
        note.textContent = err.message || 'Upload failed. Your lesson is still saved on this device.';
        button.textContent = '⬆ Retry';
    } finally {
        uploading = false;
        button.disabled = false;
    }
}

$('#import').addEventListener('change', async (e) => {
    const file = e.target.files?.[0];
    if (file) await openLesson(file, file.name);
    e.target.value = '';
});

async function openLesson(blob, filename) {
    try {
        const { lesson, audio } = await decodeLesson(blob);
        player?.destroy();
        player = new LessonPlayer($('#replay'), lesson, audio);
        player.onTick = (t, playing) => {
            $('#play-time').textContent = `${formatTime(t)} / ${formatTime(player.duration)}`;
            $('#seek').value = player.duration ? String(Math.round((t / player.duration) * 1000)) : '0';
            $('#play').textContent = playing ? '❚❚' : '▶';
            $('#play').setAttribute('aria-label', playing ? 'Pause' : 'Play');
        };
        player.onTick(0, false);
        $('#play').disabled = false;
        $('#seek').disabled = false;

        if (exportUrl) URL.revokeObjectURL(exportUrl);
        exportUrl = URL.createObjectURL(blob);
        const link = $('#export');
        link.href = exportUrl;
        link.download = filename.endsWith('.scl') ? filename : `${filename}.scl`;
        link.hidden = false;

        const strokes = lesson.events.filter((ev) => ev.type === 'stroke').length;
        $('#player-info').textContent = `${lesson.title || 'Lesson'} · ${formatTime(lesson.durationMs)} · ${strokes} strokes · ${audio ? `voice ${audio.type.split(';')[0] || 'audio'}` : 'no voice'} · ${formatBytes(blob.size)}`;
        status('Lesson loaded');
    } catch (err) {
        console.error(err);
        status(err.message || 'Could not open that lesson');
    }
}

$('#play').addEventListener('click', async () => {
    if (!player) return;
    if (player.playing) {
        player.pause();
    } else {
        try {
            await player.play();
        } catch (err) {
            console.error(err);
            status(`This device cannot play this recording's audio (${err.message || err.name})`);
        }
    }
});
$('#seek').addEventListener('input', (e) => {
    if (player) player.seek((Number(e.target.value) / 1000) * player.duration);
});
$$('[data-rate]').forEach((btn) => btn.addEventListener('click', () => {
    player?.setRate(Number(btn.dataset.rate));
    $$('[data-rate]').forEach((b) => b.classList.toggle('is-active', b === btn));
}));

function formatBytes(bytes) {
    if (bytes < 1024) return `${Math.round(bytes)} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

refreshLibrary();
