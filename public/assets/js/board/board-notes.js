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
        const flush = () => { if (paragraph.length) { out.push({ style: 'p', text: plain(paragraph.join(' ')) }); paragraph = []; } };
        for (const raw of String(b.body || '').replace(/\r\n/g, '\n').split('\n')) {
            const line = raw.trimEnd();
            let m;
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
    for (const line of lines) {
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

/** A measuring function using a hidden canvas (the same font as the board). */
export function canvasMeasure() {
    const ctx = document.createElement('canvas').getContext('2d');
    return (text, size, weight) => {
        ctx.font = `${weight === 'bold' ? '700 ' : ''}${size}px Inter, system-ui, sans-serif`;
        return ctx.measureText(text).width;
    };
}
