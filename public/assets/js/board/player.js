/**
 * Replays a recorded lesson: the voice plays and the board redraws in step with it.
 * The audio element is the master clock, so pausing, seeking and speed changes
 * keep writing and voice together.
 */

import { BoardState, Renderer } from './board-core.js';

export class LessonPlayer {
    /**
     * @param {HTMLCanvasElement} canvas
     * @param {{durationMs: number, events: object[]}} lesson
     * @param {Blob|null} audio
     */
    constructor(canvas, lesson, audio) {
        this.renderer = new Renderer(canvas);
        this.lesson = lesson;
        this.events = [...lesson.events].sort((a, b) => a.t - b.t);
        this.duration = lesson.durationMs || (this.events.length ? this.events[this.events.length - 1].t : 0);
        this.state = new BoardState();
        this.applied = 0;           // events applied so far
        this.lastTime = -1;
        this.onTick = () => {};

        // Silent clock fallback when there is no audio (e.g. microphone refused).
        this.silentStart = null;
        this.silentOffset = 0;
        this.rate = 1;

        this.audio = null;
        if (audio) {
            this.audioUrl = URL.createObjectURL(audio);
            this.audio = new Audio();
            this.audio.preload = 'auto';
            this.audio.src = this.audioUrl;
            this.audio.addEventListener('ended', () => this.onTick(this.time(), false));
        }

        this.playing = false;
        this.loop = this.loop.bind(this);
        window.addEventListener('resize', () => {
            this.renderer.resize();
            this.draw(this.time(), true);
        });
        this.draw(0, true);
    }

    time() {
        if (this.audio) return Math.min(this.duration, this.audio.currentTime * 1000);
        if (this.silentStart === null) return this.silentOffset;
        return Math.min(this.duration, this.silentOffset + (performance.now() - this.silentStart) * this.rate);
    }

    async play() {
        if (this.time() >= this.duration) this.seek(0);
        if (this.audio) {
            this.audio.playbackRate = this.rate;
            await this.audio.play();
        } else {
            this.silentStart = performance.now();
        }
        this.playing = true;
        requestAnimationFrame(this.loop);
    }

    pause() {
        if (this.audio) {
            this.audio.pause();
        } else if (this.silentStart !== null) {
            this.silentOffset = this.time();
            this.silentStart = null;
        }
        this.playing = false;
        this.onTick(this.time(), false);
    }

    seek(ms) {
        const t = Math.max(0, Math.min(this.duration, ms));
        if (this.audio) {
            this.audio.currentTime = t / 1000;
        } else {
            this.silentOffset = t;
            if (this.silentStart !== null) this.silentStart = performance.now();
        }
        this.draw(t, true);
        this.onTick(t, this.playing);
    }

    setRate(rate) {
        if (!this.audio && this.silentStart !== null) {
            this.silentOffset = this.time();
            this.silentStart = performance.now();
        }
        this.rate = rate;
        if (this.audio) this.audio.playbackRate = rate;
    }

    loop() {
        if (!this.playing) return;
        const t = this.time();
        this.draw(t, false);
        this.onTick(t, true);
        if (t >= this.duration && (!this.audio || this.audio.ended || this.audio.paused)) {
            this.playing = false;
            this.onTick(t, false);
            return;
        }
        requestAnimationFrame(this.loop);
    }

    /** Bring the board to time t. Going backwards rebuilds from the start. */
    draw(t, force) {
        if (t < this.lastTime) {
            this.state.reset();
            this.applied = 0;
        }
        while (this.applied < this.events.length && this.events[this.applied].t <= t) {
            this.state.apply(this.events[this.applied]);
            this.applied++;
        }
        if (force || t !== this.lastTime) this.renderer.render(this.state, t, t);
        this.lastTime = t;
    }

    destroy() {
        this.pause();
        if (this.audioUrl) URL.revokeObjectURL(this.audioUrl);
    }
}

export function formatTime(ms) {
    const s = Math.max(0, Math.floor(ms / 1000));
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
