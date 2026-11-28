/**
 * SMART Circle board — core model and renderer (no dependencies).
 *
 * Everything is stored in "page units": a fixed 1600 x 1000 page, so a lesson
 * recorded on an iPad replays identically on a small Android phone.
 *
 * A lesson is a time-stamped list of events:
 *   { t, type: 'stroke', id, tool, color, width, points: [[x, y, pressure, t], ...] }
 *   { t, type: 'erase', ids: [...] }        stroke eraser
 *   { t, type: 'laser', points: [...] }     fading pointer trail (not permanent)
 *   { t, type: 'undo', id } / { t, type: 'redo', id }
 *   { t, type: 'clear' }                    wipe the current page
 *   { t, type: 'page', index }              switch to page N (creates it if new)
 *   { t, type: 'text', id, x, y, text, size, color, align, weight?, instant? }   typed text or an equation
 *                                           (align 'center' = centred on x; 'left' = starts at x;
 *                                            instant = shown whole at once in replay, e.g. notes put on the board)
 *   { t, type: 'shape', id, color, width, paths: [[[x, y], ...], ...], labels: [{ x, y, text, size }] }
 *                                           a ready-made illustration (circuit symbol, apparatus...), drawn at once
 * t = milliseconds since recording started (paused time excluded).
 */

export const PAGE_W = 1600;
export const PAGE_H = 1000;

export const TOOLS = {
    pen: { width: 7, alpha: 1, composite: 'source-over' },
    highlighter: { width: 28, alpha: 0.32, composite: 'multiply' },
    laser: { width: 7, alpha: 1, composite: 'source-over' },
};

export const COLORS = {
    ink: '#14213D',
    blue: '#1D4E89',
    green: '#157A52',
    red: '#C62828',
    amber: '#F2A900',
    purple: '#7B3FA0',
};

const LASER_FADE_MS = 1400;

/**
 * Board state rebuilt from events: pages of strokes, with undo/erase applied.
 */
export class BoardState {
    constructor() {
        this.reset();
    }

    reset() {
        this.pages = [[]];      // each page: array of stroke events
        this.pageIndex = 0;
        this.removed = new Set(); // ids hidden by erase/undo
        this.lasers = [];        // recent laser events (for fading)
    }

    get strokes() {
        return this.pages[this.pageIndex];
    }

    /** Apply one event. Strokes may be partial (only points up to time t). */
    apply(event) {
        switch (event.type) {
            case 'stroke':
            case 'text':
            case 'shape':
                this.strokes.push(event);
                break;
            case 'erase':
                event.ids.forEach((id) => this.removed.add(id));
                break;
            case 'undo':
                this.removed.add(event.id);
                break;
            case 'redo':
                this.removed.delete(event.id);
                break;
            case 'clear':
                this.strokes.forEach((s) => this.removed.add(s.id));
                break;
            case 'page':
                while (this.pages.length <= event.index) this.pages.push([]);
                this.pageIndex = event.index;
                break;
            case 'laser':
                this.lasers.push(event);
                break;
        }
    }
}

/**
 * Draws a BoardState onto a canvas, scaled to fit while keeping the page shape.
 */
export class Renderer {
    constructor(canvas, { background = '#FFFFFF', grid = false } = {}) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.background = background;
        this.grid = grid;
        this.resize();
    }

    /** Match the canvas to its CSS size and the screen's pixel density. */
    resize() {
        const rect = this.canvas.getBoundingClientRect();
        const dpr = Math.min(window.devicePixelRatio || 1, 3);
        this.canvas.width = Math.max(1, Math.round(rect.width * dpr));
        this.canvas.height = Math.max(1, Math.round(rect.height * dpr));
        // Fit the 1600x1000 page inside the canvas, centred.
        const scale = Math.min(this.canvas.width / PAGE_W, this.canvas.height / PAGE_H);
        this.scale = scale;
        this.offsetX = (this.canvas.width - PAGE_W * scale) / 2;
        this.offsetY = (this.canvas.height - PAGE_H * scale) / 2;
    }

    /** Screen (client) coordinates -> page units. */
    toPage(clientX, clientY) {
        const rect = this.canvas.getBoundingClientRect();
        const dpr = this.canvas.width / rect.width;
        return [
            ((clientX - rect.left) * dpr - this.offsetX) / this.scale,
            ((clientY - rect.top) * dpr - this.offsetY) / this.scale,
        ];
    }

    /**
     * Render the state. `untilT` limits stroke points (for progressive replay);
     * `now` drives laser fading.
     */
    render(state, untilT = Infinity, now = untilT) {
        const { ctx } = this;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.fillStyle = '#E9EDF3';
        ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
        ctx.setTransform(this.scale, 0, 0, this.scale, this.offsetX, this.offsetY);
        ctx.fillStyle = this.background;
        ctx.fillRect(0, 0, PAGE_W, PAGE_H);
        if (this.grid) this.drawGrid();

        for (const stroke of state.strokes) {
            if (state.removed.has(stroke.id)) continue;
            if (stroke.type === 'text') drawText(ctx, stroke, untilT);
            else if (stroke.type === 'shape') drawShape(ctx, stroke, untilT);
            else drawStroke(ctx, stroke, untilT);
        }

        // Laser trails fade out after LASER_FADE_MS.
        state.lasers = state.lasers.filter((l) => now - lastT(l) < LASER_FADE_MS);
        for (const laser of state.lasers) {
            const age = now - lastT(laser);
            drawLaser(ctx, laser, Math.max(0, 1 - age / LASER_FADE_MS), untilT);
        }
    }

    drawGrid() {
        const { ctx } = this;
        ctx.save();
        ctx.strokeStyle = '#E3E8EF';
        ctx.lineWidth = 1;
        for (let x = 50; x < PAGE_W; x += 50) line(ctx, x, 0, x, PAGE_H);
        for (let y = 50; y < PAGE_H; y += 50) line(ctx, 0, y, PAGE_W, y);
        ctx.restore();
    }
}

function line(ctx, x1, y1, x2, y2) {
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.stroke();
}

function lastT(event) {
    const pts = event.points;
    return pts.length ? pts[pts.length - 1][3] : event.t;
}

/** Visible points of a stroke at time `untilT`. */
function visiblePoints(stroke, untilT) {
    if (untilT === Infinity) return stroke.points;
    let n = 0;
    while (n < stroke.points.length && stroke.points[n][3] <= untilT) n++;
    return stroke.points.slice(0, n);
}

/**
 * Smooth, pressure-sensitive stroke: quadratic curves through midpoints,
 * drawn segment by segment so the width can follow pressure.
 */
export function drawStroke(ctx, stroke, untilT = Infinity) {
    const pts = visiblePoints(stroke, untilT);
    if (pts.length === 0) return;
    const tool = TOOLS[stroke.tool] || TOOLS.pen;

    ctx.save();
    ctx.globalAlpha = tool.alpha;
    ctx.globalCompositeOperation = tool.composite;
    ctx.strokeStyle = stroke.color;
    ctx.fillStyle = stroke.color;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    const widthAt = (p) => stroke.width * (stroke.tool === 'highlighter' ? 1 : 0.35 + 0.9 * p);

    if (pts.length === 1) {
        ctx.beginPath();
        ctx.arc(pts[0][0], pts[0][1], widthAt(pts[0][2]) / 2, 0, Math.PI * 2);
        ctx.fill();
    } else if (stroke.tool === 'highlighter') {
        // Highlighter: one path, constant width, so overlaps don't darken within a stroke.
        ctx.lineWidth = stroke.width;
        ctx.lineCap = 'butt';
        ctx.beginPath();
        tracePath(ctx, pts);
        ctx.stroke();
    } else {
        for (let i = 1; i < pts.length; i++) {
            const p0 = pts[i - 1];
            const p1 = pts[i];
            const m0 = i === 1 ? p0 : mid(pts[i - 2], p0);
            const m1 = mid(p0, p1);
            ctx.lineWidth = (widthAt(p0[2]) + widthAt(p1[2])) / 2;
            ctx.beginPath();
            ctx.moveTo(m0[0], m0[1]);
            ctx.quadraticCurveTo(p0[0], p0[1], m1[0], m1[1]);
            if (i === pts.length - 1) ctx.lineTo(p1[0], p1[1]);
            ctx.stroke();
        }
    }
    ctx.restore();
}

function tracePath(ctx, pts) {
    ctx.moveTo(pts[0][0], pts[0][1]);
    for (let i = 1; i < pts.length - 1; i++) {
        const m = mid(pts[i], pts[i + 1]);
        ctx.quadraticCurveTo(pts[i][0], pts[i][1], m[0], m[1]);
    }
    const last = pts[pts.length - 1];
    ctx.lineTo(last[0], last[1]);
}

function drawLaser(ctx, laser, opacity, untilT) {
    const pts = visiblePoints(laser, untilT);
    if (pts.length === 0) return;
    ctx.save();
    ctx.globalAlpha = opacity;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.shadowColor = 'rgba(198, 40, 40, 0.8)';
    ctx.shadowBlur = 14;
    ctx.strokeStyle = '#E53935';
    ctx.lineWidth = TOOLS.laser.width;
    ctx.beginPath();
    tracePath(ctx, pts.length === 1 ? [pts[0], pts[0]] : pts);
    ctx.stroke();
    // Bright dot at the tip.
    const tip = pts[pts.length - 1];
    ctx.fillStyle = '#FF5252';
    ctx.beginPath();
    ctx.arc(tip[0], tip[1], 9, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}

function mid(a, b) {
    return [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2];
}

/*
 * Typed text and equations. A light, safe notation (the same as in notes):
 *   x^2  x^{n+1}  H_2O  CO_{2}   superscript / subscript
 *   ->  =>  <=  >=  !=  +-  sqrt  *   become  →  ⇒  ≤  ≥  ≠  ±  √  ×
 *   \alpha \Delta \deg \pi \therefore ...   symbols;  frac{a}{b}  becomes (a)/(b)
 * During replay the text appears letter by letter from the moment it was typed.
 */
const TYPE_MS_PER_CHAR = 35;
const SYMBOLS = [['<=>', '⇌'], ['->', '→'], ['=>', '⇒'], ['<=', '≤'], ['>=', '≥'], ['!=', '≠'], ['+-', '±'], ['sqrt', '√'], ['*', '×'], ['deg', '°']];

/** \alpha → α, \deg → °, ... The same list as src/Support/Symbols.php (WORDS). */
export const SYMBOL_WORDS = { alpha: 'α', beta: 'β', gamma: 'γ', delta: 'δ', epsilon: 'ε', theta: 'θ', lambda: 'λ', mu: 'μ', pi: 'π', rho: 'ρ', sigma: 'σ', tau: 'τ', phi: 'φ', omega: 'ω', eta: 'η', Delta: 'Δ', Sigma: 'Σ', Omega: 'Ω', Theta: 'Θ', Phi: 'Φ', Pi: 'Π', Lambda: 'Λ', times: '×', div: '÷', pm: '±', neq: '≠', approx: '≈', equiv: '≡', leq: '≤', geq: '≥', infty: '∞', propto: '∝', sqrt: '√', cbrt: '∛', deg: '°', angle: '∠', perp: '⊥', parallel: '∥', therefore: '∴', because: '∵', triangle: '△', sum: 'Σ', cdot: '·', in: '∈', notin: '∉', subset: '⊂', union: '∪', intersect: '∩', empty: '∅', xi: 'ξ', half: '½', third: '⅓', quarter: '¼', prime: '′', to: '→', gets: '←', rightarrow: '→', leftarrow: '←', eqm: '⇌', implies: '⇒', iff: '⇔', up: '↑', down: '↓', plus: '⁺', minus: '⁻', ohm: 'Ω', micro: 'µ', celsius: '°C', tick: '✓', star: '★', para: '¶', section: '§', ldquo: '“', rdquo: '”', dash: '—' };

export function prettify(text) {
    let s = text.indexOf('\\') === -1 ? text : text.replace(/\\([A-Za-z]+)/g, (m, w) => (Object.prototype.hasOwnProperty.call(SYMBOL_WORDS, w) ? SYMBOL_WORDS[w] : m));
    s = s.replace(/frac\{([^{}]{1,40})\}\{([^{}]{1,40})\}/g, '($1)/($2)');
    for (const [from, to] of SYMBOLS) s = s.split(from).join(to);
    return s;
}

/** Splits text into runs: { text, shift: 0 | 1 (superscript) | -1 (subscript) }. */
export function textRuns(text) {
    // Same rules as notes: x^2, x^{n+1}, CO_{2}; an unbraced subscript is digits only (H_2O).
    const runs = [];
    const re = /\^\{([^}]{1,20})\}|\^(-?[0-9A-Za-z+]{1,6})|_\{([^}]{1,20})\}|_([0-9]{1,3})/g;
    let last = 0;
    let m;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) runs.push({ text: text.slice(last, m.index), shift: 0 });
        const sup = m[1] !== undefined || m[2] !== undefined;
        runs.push({ text: m[1] ?? m[2] ?? m[3] ?? m[4], shift: sup ? 1 : -1 });
        last = re.lastIndex;
    }
    if (last < text.length) runs.push({ text: text.slice(last), shift: 0 });
    return runs;
}

function font(size, weight) {
    return `${weight === 'bold' ? '700 ' : ''}${size}px Inter, system-ui, sans-serif`;
}

function textWidth(ctx, runs, size, weight) {
    let w = 0;
    for (const r of runs) {
        ctx.font = font(r.shift ? Math.round(size * 0.62) : size, weight);
        w += ctx.measureText(r.text).width;
    }
    return w;
}

export function drawText(ctx, item, untilT = Infinity) {
    let text = prettify(item.text);
    if (untilT !== Infinity && item.instant) {
        if (untilT < item.t) return;
    } else if (untilT !== Infinity) {
        const shown = Math.floor((untilT - item.t) / TYPE_MS_PER_CHAR) + 1;
        if (shown <= 0) return;
        text = text.slice(0, shown);
    }
    const runs = textRuns(text);
    ctx.save();
    ctx.fillStyle = item.color;
    ctx.textBaseline = 'alphabetic';
    // Centre on the whole text, so it does not shift while it types out during replay.
    let x = item.align === 'center' ? item.x - textWidth(ctx, textRuns(prettify(item.text)), item.size, item.weight) / 2 : item.x;
    for (const r of runs) {
        const size = r.shift ? Math.round(item.size * 0.62) : item.size;
        ctx.font = font(size, item.weight);
        const dy = r.shift === 1 ? -item.size * 0.42 : r.shift === -1 ? item.size * 0.22 : 0;
        ctx.fillText(r.text, x, item.y + dy);
        x += ctx.measureText(r.text).width;
    }
    ctx.restore();
}

/** A ready-made illustration: all its lines at once, plus any labels (A, V, N, S...). */
export function drawShape(ctx, shape, untilT = Infinity) {
    if (untilT !== Infinity && untilT < shape.t) return;
    ctx.save();
    ctx.strokeStyle = shape.color;
    ctx.fillStyle = shape.color;
    ctx.lineWidth = shape.width;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    for (const path of shape.paths) {
        if (path.length === 0) continue;
        ctx.beginPath();
        ctx.moveTo(path[0][0], path[0][1]);
        for (let i = 1; i < path.length; i++) ctx.lineTo(path[i][0], path[i][1]);
        if (path.length === 1) ctx.lineTo(path[0][0] + 0.1, path[0][1]);
        ctx.stroke();
    }
    for (const label of shape.labels || []) {
        ctx.font = font(label.size, 'bold');
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(label.text, label.x, label.y);
    }
    ctx.restore();
}

/** Rough box of a text item (for the eraser), in page units. */
function textBox(item) {
    const w = prettify(item.text).length * item.size * 0.55;
    const left = item.align === 'center' ? item.x - w / 2 : item.x;
    return [left, item.y - item.size, left + w, item.y + item.size * 0.3];
}

/** Distance from point to a stroke (for the stroke eraser). */
export function hitsStroke(stroke, x, y, radius) {
    if (stroke.type === 'text') {
        const [l, t, r, b] = textBox(stroke);
        return x >= l - radius && x <= r + radius && y >= t - radius && y <= b + radius;
    }
    if (stroke.type === 'shape') {
        const rr = radius + stroke.width / 2;
        return stroke.paths.some((path) => path.some((a, i) => {
            const b = path[i + 1] || a;
            return distToSegment(x, y, a[0], a[1], b[0], b[1]) <= rr;
        }));
    }
    const r = radius + stroke.width / 2;
    const pts = stroke.points;
    for (let i = 0; i < pts.length; i++) {
        const a = pts[i];
        const b = pts[i + 1] || a;
        if (distToSegment(x, y, a[0], a[1], b[0], b[1]) <= r) return true;
    }
    return false;
}

function distToSegment(px, py, ax, ay, bx, by) {
    const dx = bx - ax;
    const dy = by - ay;
    const len2 = dx * dx + dy * dy;
    let t = len2 === 0 ? 0 : ((px - ax) * dx + (py - ay) * dy) / len2;
    t = Math.max(0, Math.min(1, t));
    return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
}
