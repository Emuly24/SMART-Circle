/**
 * Lesson recorder: a pausable clock + the microphone (MediaRecorder).
 *
 * The clock and the audio start together, and pause together, so every stroke's
 * timestamp lines up with the voice on replay.
 */

export class LessonClock {
    constructor() {
        this.elapsedBefore = 0; // ms accumulated before the current run
        this.startedAt = null;  // performance.now() when the current run started
    }

    get running() {
        return this.startedAt !== null;
    }

    now() {
        return this.elapsedBefore + (this.startedAt === null ? 0 : performance.now() - this.startedAt);
    }

    start() {
        if (this.startedAt === null) this.startedAt = performance.now();
    }

    pause() {
        if (this.startedAt !== null) {
            this.elapsedBefore += performance.now() - this.startedAt;
            this.startedAt = null;
        }
    }

    reset() {
        this.elapsedBefore = 0;
        this.startedAt = null;
    }
}

/** Best audio format the browser can record: iPad Safari = MP4/AAC, Chrome/Android = WebM/Opus. */
export function pickAudioMime() {
    if (typeof MediaRecorder === 'undefined') return null;
    const candidates = ['audio/mp4;codecs=mp4a.40.2', 'audio/mp4', 'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus'];
    return candidates.find((m) => MediaRecorder.isTypeSupported(m)) || '';
}

export class VoiceRecorder {
    constructor() {
        this.stream = null;
        this.recorder = null;
        this.chunks = [];
        this.mime = '';
    }

    get supported() {
        return typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia && typeof MediaRecorder !== 'undefined';
    }

    /** Ask for the microphone. Must be called from a button tap (browser rule). */
    async open() {
        this.stream = await navigator.mediaDevices.getUserMedia({
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
        });
        this.mime = pickAudioMime() ?? '';
        // About 32 kbps: clear speech, roughly 14 MB per hour.
        const options = { audioBitsPerSecond: 32000 };
        if (this.mime) options.mimeType = this.mime;
        this.recorder = new MediaRecorder(this.stream, options);
        this.mime = this.recorder.mimeType || this.mime || 'audio/webm';
        this.recorder.ondataavailable = (e) => {
            if (e.data && e.data.size > 0) this.chunks.push(e.data);
        };
    }

    /** Resolves once audio is actually being captured (keeps the clock in sync). */
    start() {
        return new Promise((resolve) => {
            this.chunks = [];
            this.recorder.onstart = () => resolve();
            // Ask for data every 5 s so a crash loses at most a few seconds.
            this.recorder.start(5000);
        });
    }

    pause() {
        if (this.recorder?.state === 'recording') this.recorder.pause();
    }

    resume() {
        if (this.recorder?.state === 'paused') this.recorder.resume();
    }

    /** Stops and returns the whole recording as one Blob. */
    stop() {
        return new Promise((resolve) => {
            if (!this.recorder || this.recorder.state === 'inactive') {
                resolve(new Blob(this.chunks, { type: this.mime }));
                return;
            }
            this.recorder.onstop = () => resolve(new Blob(this.chunks, { type: this.mime }));
            this.recorder.stop();
        });
    }

    close() {
        this.stream?.getTracks().forEach((t) => t.stop());
        this.stream = null;
    }
}
