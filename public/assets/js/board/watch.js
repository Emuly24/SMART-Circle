/**
 * Student/tutor watch page: download the lesson (with progress), then replay it.
 */

import { decodeLesson } from './lesson-file.js';
import { formatTime, LessonPlayer } from './player.js';

const $ = (sel) => document.querySelector(sel);
const root = $('#watch');

async function download(url, expectedSize, onProgress) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`Could not load the lesson (error ${res.status}).`);
    const total = Number(res.headers.get('Content-Length')) || expectedSize;
    if (!res.body || !total) return res.blob();

    const reader = res.body.getReader();
    const chunks = [];
    let received = 0;
    for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
        received += value.length;
        onProgress(received, total);
    }
    return new Blob(chunks);
}

async function main() {
    const status = $('#load-status');
    const bar = $('#load-bar');
    let blob;
    try {
        blob = await download(root.dataset.src, Number(root.dataset.size), (got, total) => {
            const pct = Math.min(100, Math.round((got / total) * 100));
            bar.style.width = `${pct}%`;
            status.textContent = `Loading lesson… ${pct}% of ${(total / 1048576).toFixed(1)} MB`;
        });
    } catch (err) {
        status.textContent = err.message;
        return;
    }

    let decoded;
    try {
        decoded = await decodeLesson(blob);
    } catch (err) {
        status.textContent = err.message;
        return;
    }

    $('#overlay').hidden = true;
    const player = new LessonPlayer($('#replay'), decoded.lesson, decoded.audio);
    const play = $('#play');
    const seek = $('#seek');
    player.onTick = (t, playing) => {
        $('#play-time').textContent = `${formatTime(t)} / ${formatTime(player.duration)}`;
        seek.value = player.duration ? String(Math.round((t / player.duration) * 1000)) : '0';
        play.textContent = playing ? '❚❚' : '▶';
        play.setAttribute('aria-label', playing ? 'Pause' : 'Play');
    };
    player.onTick(0, false);
    play.disabled = false;
    seek.disabled = false;

    play.addEventListener('click', async () => {
        if (player.playing) {
            player.pause();
            return;
        }
        try {
            await player.play();
        } catch (err) {
            $('#overlay').hidden = false;
            status.textContent = `This phone could not play the voice (${err.message || err.name}). Try another browser such as Chrome.`;
        }
    });
    seek.addEventListener('input', () => player.seek((Number(seek.value) / 1000) * player.duration));
    document.querySelectorAll('[data-rate]').forEach((btn) => btn.addEventListener('click', () => {
        player.setRate(Number(btn.dataset.rate));
        document.querySelectorAll('[data-rate]').forEach((b) => b.classList.toggle('is-active', b === btn));
    }));
}

main();
