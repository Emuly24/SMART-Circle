/**
 * Board lab page: record a lesson (strokes + voice), keep it on this device,
 * replay it, export it as a .scl file and open files recorded elsewhere.
 */

import { COLORS, Renderer } from './board-core.js';
import { BoardInput } from './board-input.js';
import { LessonClock, VoiceRecorder } from './recorder.js';
import { decodeLesson, encodeLesson, listLocal, loadLocal, saveLocal, deleteLocal } from './lesson-file.js';
import { formatTime, LessonPlayer } from './player.js';

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

    // Start from a clean board so the lesson is self-contained.
    input.state.reset();
    input.history = [];
    input.redoStack = [];
    renderer.render(input.state);

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
        li.append(open, del);
        return li;
    }));
    $('#library-empty').hidden = items.length > 0;
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
