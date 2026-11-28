/**
 * Turns Pencil / finger / mouse input on the canvas into board events.
 *
 * - Apple Pencil (pointerType "pen") uses real pressure.
 * - Fingers and mice get a natural width from drawing speed (fast = thinner).
 * - Palm rejection: once a Pencil has been used, fingers stop drawing
 *   (unless "fingers draw" is switched on), so a resting hand leaves no marks.
 */

import { BoardState, COLORS, hitsStroke, PAGE_H, PAGE_W, TOOLS } from './board-core.js';

let nextId = 1;
const newId = () => `s${Date.now().toString(36)}${(nextId++).toString(36)}`;

export class BoardInput {
    /**
     * @param {HTMLCanvasElement} canvas
     * @param {import('./board-core.js').Renderer} renderer
     * @param {() => number} clock   current lesson time in ms
     * @param {(event: object) => void} emit   receives every finished event
     */
    constructor(canvas, renderer, clock, emit) {
        this.canvas = canvas;
        this.renderer = renderer;
        this.clock = clock;
        this.emit = emit;
        this.state = new BoardState();

        this.tool = 'pen';
        this.color = COLORS.ink;
        this.penWidth = TOOLS.pen.width;
        this.fingersDraw = true;     // until a Pencil is seen
        this.pencilSeen = false;

        this.active = new Map();     // pointerId -> stroke being drawn
        this.history = [];           // ids of strokes, for undo
        this.redoStack = [];

        this.onChange = () => {};
        /** Called with a page point when the Type tool is tapped; the page shows a text box. */
        this.onTextRequest = () => {};
        this.textCentred = true;
        this.textSize = 0;           // 0 = follow the pen size
        this.stampKey = null;        // the illustration placed by the Shapes tool
        this.stampBox = 220;         // its width in page units
        this.bind();
    }

    bind() {
        const c = this.canvas;
        c.addEventListener('pointerdown', (e) => this.down(e));
        c.addEventListener('pointermove', (e) => this.move(e));
        c.addEventListener('pointerup', (e) => this.up(e));
        c.addEventListener('pointercancel', (e) => this.up(e));
        // Stop iOS from scrolling, zooming or showing the text-selection loupe.
        c.addEventListener('touchstart', (e) => e.preventDefault(), { passive: false });
        c.addEventListener('contextmenu', (e) => e.preventDefault());
    }

    accepts(e) {
        if (e.pointerType === 'pen') {
            if (!this.pencilSeen) {
                this.pencilSeen = true;
                this.fingersDraw = false; // palm rejection kicks in
                this.onChange();
            }
            return true;
        }
        if (e.pointerType === 'touch') return this.fingersDraw;
        return e.button === 0 || e.buttons === 1;
    }

    point(e, prev) {
        const [x, y] = this.renderer.toPage(e.clientX, e.clientY);
        const t = Math.round(this.clock());
        let pressure;
        if (e.pointerType === 'pen' && e.pressure > 0) {
            pressure = e.pressure;
        } else {
            // Simulated pressure from speed (page units per ms).
            const speed = prev ? Math.hypot(x - prev[0], y - prev[1]) / Math.max(1, t - prev[3]) : 0;
            const target = Math.max(0.25, Math.min(0.85, 0.85 - speed * 0.35));
            pressure = prev ? prev[2] * 0.6 + target * 0.4 : 0.6;
        }
        return [round1(clamp(x, 0, PAGE_W)), round1(clamp(y, 0, PAGE_H)), round2(pressure), t];
    }

    down(e) {
        if (!this.accepts(e)) return;
        e.preventDefault();
        try {
            // Keep receiving moves even if the Pencil slides off the canvas edge.
            this.canvas.setPointerCapture(e.pointerId);
        } catch {
            // The pointer may already be gone (very quick taps); drawing still works without capture.
        }
        const p = this.point(e, null);

        if (this.tool === 'text') {
            this.onTextRequest(p, e.clientX, e.clientY);
            return;
        }

        if (this.tool === 'stamp') {
            this.addShape(p);
            return;
        }

        if (DRAG_TOOLS.includes(this.tool)) {
            // Line, arrow, box or circle: drag from one corner to the other.
            const shape = { type: 'shape', id: newId(), t: p[3], key: this.tool, color: this.color, width: Math.max(3, Math.round(this.penWidth * 0.7)), paths: [], labels: [], from: [p[0], p[1]] };
            this.active.set(e.pointerId, shape);
            this.state.apply(shape);
            this.redraw();
            return;
        }

        if (this.tool === 'eraser') {
            this.active.set(e.pointerId, { type: 'eraser' });
            this.eraseAt(p);
            return;
        }

        const isLaser = this.tool === 'laser';
        const stroke = isLaser
            ? { type: 'laser', t: p[3], points: [p] }
            : {
                type: 'stroke',
                id: newId(),
                t: p[3],
                tool: this.tool,
                color: this.tool === 'highlighter' ? highlighterColor(this.color) : this.color,
                width: this.tool === 'highlighter' ? TOOLS.highlighter.width : this.penWidth,
                points: [p],
            };
        this.active.set(e.pointerId, stroke);
        this.state.apply(stroke);
        this.redraw();
    }

    move(e) {
        const stroke = this.active.get(e.pointerId);
        if (!stroke) return;
        e.preventDefault();
        // Coalesced events give the Pencil's full 240 Hz detail where supported.
        const events = typeof e.getCoalescedEvents === 'function' ? e.getCoalescedEvents() : [e];
        if (stroke.type === 'shape') {
            const p = this.point(e, null);
            stroke.paths = dragPaths(stroke.key, stroke.from, [p[0], p[1]]);
            this.redraw();
            return;
        }
        for (const ev of events.length ? events : [e]) {
            if (stroke.type === 'eraser') {
                this.eraseAt(this.point(ev, null));
                continue;
            }
            const prev = stroke.points[stroke.points.length - 1];
            const p = this.point(ev, prev);
            // Skip points closer than half a unit: saves data without visible loss.
            if (Math.hypot(p[0] - prev[0], p[1] - prev[1]) >= 0.5) stroke.points.push(p);
        }
        this.redraw();
    }

    up(e) {
        const stroke = this.active.get(e.pointerId);
        if (!stroke) return;
        this.active.delete(e.pointerId);
        if (stroke.type === 'eraser') return;
        if (stroke.type === 'shape') {
            const page = this.state.strokes;
            const [x0, y0] = stroke.from;
            const extent = stroke.paths.flat().reduce((m, [x, y]) => Math.max(m, Math.abs(x - x0), Math.abs(y - y0)), 0);
            if (extent < 4) {           // a tap, not a drag: nothing to draw
                const i = page.indexOf(stroke);
                if (i >= 0) page.splice(i, 1);
                this.redraw();
                return;
            }
            delete stroke.from;
            this.history.push(stroke.id);
            this.redoStack = [];
            this.emit(stroke);
            this.redraw();
            return;
        }
        if (stroke.type === 'stroke') {
            this.history.push(stroke.id);
            this.redoStack = [];
        }
        this.emit(stroke);
        this.redraw();
    }

    /**
     * Adds typed text (or equations) at a page point; centred on the page when textCentred is on.
     * Several lines are placed one under another.
     */
    addText(text, p) {
        const lines = String(text).replace(/\r\n/g, '\n').split('\n').map((l) => l.replace(/\s+/g, ' ').trim().slice(0, 200));
        const size = this.textSize || (this.penWidth <= 4 ? 30 : this.penWidth >= 12 ? 60 : 42);
        let y = p[1];
        for (const clean of lines) {
            if (clean !== '') {
                const item = {
                    type: 'text',
                    id: newId(),
                    t: Math.round(this.clock()),
                    x: this.textCentred ? PAGE_W / 2 : round1(p[0]),
                    y: round1(clamp(y, size, PAGE_H - 10)),
                    text: clean,
                    size,
                    color: this.color,
                    align: this.textCentred ? 'center' : 'left',
                };
                this.history.push(item.id);
                this.redoStack = [];
                this.commit(item);
            }
            y += size * 1.45;
        }
    }

    /** Places the chosen illustration (Shapes tool) centred on a page point. */
    addShape(p) {
        if (!this.stampKey || !this.buildShape) return;
        const shape = this.buildShape(this.stampKey, p[0], p[1], this.stampBox, this.color, Math.max(3, Math.round(this.penWidth * 0.7)), newId(), Math.round(this.clock()));
        if (!shape) return;
        this.history.push(shape.id);
        this.redoStack = [];
        this.commit(shape);
    }

    /** Puts prepared pages (notes) on the board, from page `start`; returns the events made. */
    addPages(pages, start) {
        const made = [];
        pages.forEach((items, i) => {
            const page = { type: 'page', t: Math.round(this.clock()), index: start + i };
            this.commit(page);
            made.push(page);
            for (const item of items) {
                const ev = { ...item, id: newId(), t: Math.round(this.clock()) };
                this.commit(ev);
                made.push(ev);
            }
        });
        this.history = [];
        this.redoStack = [];
        return made;
    }

    eraseAt(p) {
        const ids = this.state.strokes
            .filter((s) => !this.state.removed.has(s.id) && hitsStroke(s, p[0], p[1], 12))
            .map((s) => s.id);
        if (ids.length === 0) return;
        this.commit({ type: 'erase', t: p[3], ids });
    }

    undo() {
        const id = this.history.pop();
        if (!id) return;
        this.redoStack.push(id);
        this.commit({ type: 'undo', t: Math.round(this.clock()), id });
    }

    redo() {
        const id = this.redoStack.pop();
        if (!id) return;
        this.history.push(id);
        this.commit({ type: 'redo', t: Math.round(this.clock()), id });
    }

    clearPage() {
        this.commit({ type: 'clear', t: Math.round(this.clock()) });
        this.history = [];
        this.redoStack = [];
    }

    goToPage(index) {
        this.commit({ type: 'page', t: Math.round(this.clock()), index });
        this.history = [];
        this.redoStack = [];
    }

    commit(event) {
        this.state.apply(event);
        this.emit(event);
        this.redraw();
    }

    redraw() {
        this.renderer.render(this.state, Infinity, this.clock());
        this.onChange();
    }
}

/** Tools drawn by dragging, saved as 'shape' events. */
export const DRAG_TOOLS = ['line', 'arrow', 'rect', 'ellipse'];

/**
 * The lines of a dragged shape from point a to point b. Lines and arrows snap to level, upright or 45° when
 * close (tidy circuit wires); a box or ellipse that is nearly square becomes a square or circle. Pure.
 */
export function dragPaths(kind, a, b) {
    let [x1, y1] = a;
    let [x2, y2] = b;
    const r1 = (v) => Math.round(v * 10) / 10;
    if (kind === 'line' || kind === 'arrow') {
        const len = Math.hypot(x2 - x1, y2 - y1);
        let ang = Math.atan2(y2 - y1, x2 - x1);
        const step = Math.PI / 4;
        const snapped = Math.round(ang / step) * step;
        if (Math.abs(ang - snapped) < 0.12) ang = snapped;
        x2 = x1 + len * Math.cos(ang);
        y2 = y1 + len * Math.sin(ang);
        const paths = [[[r1(x1), r1(y1)], [r1(x2), r1(y2)]]];
        if (kind === 'arrow' && len > 1) {
            const h = Math.min(28, Math.max(14, len * 0.25));
            const p = (da) => [r1(x2 - h * Math.cos(ang + da)), r1(y2 - h * Math.sin(ang + da))];
            paths.push([p(0.45), [r1(x2), r1(y2)], p(-0.45)]);
        }
        return paths;
    }
    let w = x2 - x1;
    let h = y2 - y1;
    if (Math.abs(Math.abs(w) - Math.abs(h)) < 0.08 * Math.max(Math.abs(w), Math.abs(h))) {
        const side = Math.max(Math.abs(w), Math.abs(h));
        w = Math.sign(w || 1) * side;
        h = Math.sign(h || 1) * side;
    }
    x2 = x1 + w;
    y2 = y1 + h;
    if (kind === 'rect') {
        return [[[r1(x1), r1(y1)], [r1(x2), r1(y1)], [r1(x2), r1(y2)], [r1(x1), r1(y2)], [r1(x1), r1(y1)]]];
    }
    const cx = (x1 + x2) / 2;
    const cy = (y1 + y2) / 2;
    const pts = [];
    for (let i = 0; i <= 48; i++) {
        const t = (i / 48) * Math.PI * 2;
        pts.push([r1(cx + (w / 2) * Math.cos(t)), r1(cy + (h / 2) * Math.sin(t))]);
    }
    return [pts];
}

function highlighterColor(color) {
    // Highlighters read best in bright colours; dark ink becomes yellow.
    return color === COLORS.ink || color === COLORS.blue ? '#FFD54F' : color;
}

const clamp = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
const round1 = (v) => Math.round(v * 10) / 10;
const round2 = (v) => Math.round(v * 100) / 100;
