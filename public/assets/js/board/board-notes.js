/**
 * Puts written notes on the board: their text is laid out on board pages (headings, paragraphs, lists,
 * "Real life" boxes, centred equations), so the teacher can record a lesson over notes already written.
 * Uses the same simple markup as notes; pictures are listed by caption (add them with the pen if needed).
 */
import { COLORS, PAGE_H, PAGE_W } from './board-core.js';

const LEFT = 80;
const RIGHT = PAGE_W - 80;
const TOP = 90;
const BOTTOM = PAGE_H - 50;

const STYLES = {
    title: { size: 50, color: COLORS.blue, weight: 'bold', gapBefore: 0 },
    h1: { size: 42, color: COLORS.blue, weight: 'bold', gapBefore: 24 },
    h2: { size: 36, color: COLORS.green, weight: 'bold', gapBefore: 18 },
    p: { size: 30, color: COLORS.ink, gapBefore: 10 },
    li: { size: 30, color: COLORS.ink, gapBefore: 4 },
    box: { size: 30, color: COLORS.purple, gapBefore: 12 },
    eq: { size: 34, color: COLORS.blue, gapBefore: 8, center: true },
    pic: { size: 26, color: COLORS.green, gapBefore: 12 },
};

/** Removes bold/italic markers (the board shows plain text); keeps x^2, H_2O, -> etc. for the board. */
const plain = (s) => s.replace(/\*\*(.+?)\*\*/g, '$1').replace(/(^|[^\w*])\*(?!\s)(.+?)\*(?!\w)/g, '$1$2').trim();

/** Splits "| a | b |" into cells. */
export const cells = (row) => row.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());
/** True for a "|---|:---:|" separator row. */
export const isSeparator = (row) => /^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(row);

/** Turns note blocks into lines with a style. Pure. */
export function noteLines(title, blocks) {
    const out = [{ style: 'title', text: plain(title) }];
    for (const b of blocks) {
        if (b.type === 'image') {
            out.push({ style: 'pic', text: '[Picture' + (b.caption ? ': ' + plain(b.caption) : '') + ']' });
            continue;
        }
        if (b.type !== 'text') continue;
        let paragraph = [];
        let rows = [];
        const flushTable = () => {
            if (rows.length) { out.push({ style: 'table', head: rows[0], rows: rows.slice(1) }); rows = []; }
        };
        const flush = () => {
            flushTable();
            if (paragraph.length) { out.push({ style: 'p', text: plain(paragraph.join(' ')) }); paragraph = []; }
        };
        for (const raw of String(b.body || '').replace(/\r\n/g, '\n').split('\n')) {
            const line = raw.trimEnd();
            let m;
            if (/^\s*\|/.test(line)) {
                if (paragraph.length) { out.push({ style: 'p', text: plain(paragraph.join(' ')) }); paragraph = []; }
                if (!isSeparator(line)) rows.push(cells(line).map(plain));
                continue;
            }
            flushTable();
            if (line.trim() === '') { flush(); continue; }
            if ((m = line.match(/^##\s+(.+)$/))) { flush(); out.push({ style: 'h2', text: plain(m[1]) }); continue; }
            if ((m = line.match(/^#\s+(.+)$/))) { flush(); out.push({ style: 'h1', text: plain(m[1]) }); continue; }
            if ((m = line.match(/^=\s+(.+)$/))) { flush(); out.push({ style: 'eq', text: m[1].trim() }); continue; }
            if ((m = line.match(/^>\s?(.*)$/))) { flush(); if (m[1].trim()) out.push({ style: 'box', text: '★ ' + plain(m[1]) }); continue; }
            if ((m = line.match(/^\s*[-*]\s+(.+)$/))) { flush(); out.push({ style: 'li', text: '•  ' + plain(m[1]) }); continue; }
            if ((m = line.match(/^\s*(\d+)[.)]\s+(.+)$/))) { flush(); out.push({ style: 'li', text: m[1] + '.  ' + plain(m[2]) }); continue; }
            paragraph.push(line.trim());
        }
        flush();
    }
    return out;
}

/** Splits a line into pieces that fit the page width. `measure(text, size, weight)` returns a width. */
export function wrap(text, size, weight, maxWidth, measure) {
    const words = text.split(/\s+/);
    const lines = [];
    let current = '';
    for (const w of words) {
        const next = current ? current + ' ' + w : w;
        if (current && measure(next, size, weight) > maxWidth) {
            lines.push(current);
            current = w;
        } else {
            current = next;
        }
    }
    if (current) lines.push(current);
    return lines;
}

/**
 * Lays the lines out on pages. Returns a list of pages, each a list of text items (no id or time yet).
 * Pure apart from `measure`.
 */
export function layout(lines, measure) {
    const pages = [[]];
    let y = TOP;
    const newPage = () => { pages.push([]); y = TOP; };
    for (const line of lines) {
        if (line.style === 'table') {
            y = layoutTable(line.head, line.rows, y, pages, newPage, measure);
            continue;
        }
        const st = STYLES[line.style];
        const indent = line.style === 'li' ? 30 : 0;
        const pieces = wrap(line.text, st.size, st.weight, RIGHT - LEFT - indent, measure);
        y += st.gapBefore;
        pieces.forEach((piece, i) => {
            if (y + st.size > BOTTOM) {
                pages.push([]);
                y = TOP;
            }
            y += st.size;
            pages[pages.length - 1].push({
                type: 'text',
                x: st.center ? PAGE_W / 2 : LEFT + (i > 0 ? indent + (line.style === 'li' ? 30 : 0) : indent),
                y: Math.round(y),
                text: piece,
                size: st.size,
                color: st.color,
                align: st.center ? 'center' : 'left',
                weight: st.weight,
                instant: true,
            });
            y += Math.round(st.size * 0.45);
        });
    }
    return pages.filter((p, i) => p.length > 0 || i === 0);
}

/** The text a cell shows, without the ^{ } _ markers (for measuring). */
const shown = (t) => t.replace(/[\^_]\{([^}]*)\}/g, '$1').replace(/[\^_]/g, '').replace(/\\[A-Za-z]+/g, 'W');

/**
 * Lays a table out from y: columns sized to their text (shared out when too wide, with wrapping),
 * a grid of lines (one 'shape' per page) and the cells as text. A table taller than the page continues
 * on the next page with its heading row repeated. Returns the new y. Pure apart from `measure`.
 */
export function layoutTable(head, rows, y, pages, newPage, measure, opts = {}) {
    const n = Math.max(head.length, ...rows.map((r) => r.length), 1);
    const norm = (r) => Array.from({ length: n }, (_, i) => r[i] ?? '');
    const all = [norm(head), ...rows.map(norm)];
    const avail = RIGHT - LEFT;
    const size = n >= 6 ? 26 : 28;
    const pad = Math.round(size * 0.45);
    const width = (t, bold) => measure(shown(t), size, bold ? 'bold' : undefined);
    const natural = Array.from({ length: n }, (_, c) => Math.max(size, ...all.map((r, i) => width(r[c], i === 0))) + pad * 2);
    let widths = natural.slice();
    const total = natural.reduce((a, b) => a + b, 0);
    if (total > avail) {
        // Share the width: short columns keep their size, long ones split what is left.
        const fair = avail / n;
        const small = natural.map((w) => w <= fair);
        const fixed = natural.reduce((a, w, i) => a + (small[i] ? w : 0), 0);
        const bigTotal = natural.reduce((a, w, i) => a + (small[i] ? 0 : w), 0);
        widths = natural.map((w, i) => (small[i] ? w : Math.max(size * 2, (avail - fixed) * (w / bigTotal))));
        const sum = widths.reduce((a, b) => a + b, 0);
        if (sum > avail) widths = widths.map((w) => w * (avail / sum));
    }
    const lineH = Math.round(size * 1.3);
    const xs = [LEFT];
    widths.forEach((w) => xs.push(xs[xs.length - 1] + w));
    const cellLines = (r, i) => r.map((t, c) => (t.trim() === '' ? [''] : wrap(t, size, i === 0 ? 'bold' : undefined, widths[c] - pad * 2, (s2, z, wt) => measure(shown(s2), z, wt))));
    const rowHeight = (ls) => Math.max(opts.minRow || 0, pad * 2 + size + (Math.max(...ls.map((l) => l.length)) - 1) * lineH + Math.round(size * 0.2));

    let grid = null;
    const startGrid = () => {
        grid = { type: 'shape', key: 'table', color: COLORS.ink, width: 2, paths: [], labels: [], top: y, lines: [y] };
        pages[pages.length - 1].push(grid);
    };
    const closeGrid = () => {
        if (!grid) return;
        const bottom = grid.lines[grid.lines.length - 1];
        grid.paths = [...grid.lines.map((ly) => [[xs[0], ly], [xs[n], ly]]), ...xs.map((x) => [[x, grid.top], [x, bottom]])].map((p) => p.map(([a, b]) => [Math.round(a * 10) / 10, Math.round(b * 10) / 10]));
        delete grid.top;
        delete grid.lines;
        grid = null;
    };
    const putRow = (r, i) => {
        const ls = cellLines(r, i);
        const h = rowHeight(ls);
        if (y + h > BOTTOM && grid && grid.lines.length > 1) {
            closeGrid();
            newPage();
            y = TOP;
            startGrid();
            if (i !== 0) putRow(all[0], 0);   // repeat the heading row
        }
        if (!grid) startGrid();
        ls.forEach((pieces, c) => pieces.forEach((piece, k) => {
            if (piece === '') return;
            pages[pages.length - 1].push({
                type: 'text', x: Math.round(xs[c] + pad), y: Math.round(y + pad + size + k * lineH), text: piece, size,
                color: i === 0 ? COLORS.blue : COLORS.ink, align: 'left', weight: i === 0 ? 'bold' : undefined, instant: true,
            });
        }));
        y += h;
        grid.lines.push(y);
    };
    y += 14;
    if (y + size * 3 > BOTTOM) { newPage(); y = TOP; }
    all.forEach((r, i) => putRow(r, i));
    closeGrid();
    return y + 16;
}

/** Board pages for a reference table (title, table, note). Pure apart from `measure`. */
export function tablePages(table, measure) {
    const lines = [{ style: 'title', text: table.title }, { style: 'table', head: table.cols, rows: table.rows }];
    if (table.note) lines.push({ style: 'pic', text: table.note });
    return layout(lines, measure);
}

/** An empty table to fill in on the board: equal columns across the page, rows tall enough to write in. */
export function emptyTablePages(cols, rows) {
    const share = (RIGHT - LEFT) / cols;
    const head = Array.from({ length: cols }, () => ' ');
    const body = Array.from({ length: rows }, () => Array.from({ length: cols }, () => ''));
    const pages = [[]];
    layoutTable(head, body, TOP - 14, pages, () => { pages.push([]); }, (t, z) => (t === ' ' ? share - z : 0), { minRow: Math.min(110, Math.max(60, Math.floor((BOTTOM - TOP - 40) / (rows + 1)))) });
    return pages;
}

/** A measuring function using a hidden canvas (the same font as the board). */
export function canvasMeasure() {
    const ctx = document.createElement('canvas').getContext('2d');
    return (text, size, weight) => {
        ctx.font = `${weight === 'bold' ? '700 ' : ''}${size}px Inter, system-ui, sans-serif`;
        return ctx.measureText(text).width;
    };
}
