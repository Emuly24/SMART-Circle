/**
 * Ready-made illustrations for the board, for every subject we teach (circuit symbols, apparatus, cells, organs,
 * graphs, solids, grammar organisers, ...). Each is drawn in a 100 x 100 box as lines ("paths") plus optional
 * labels, then placed on the page at the size the teacher chooses. It is saved as one 'shape' event (with its
 * points), so it replays, undoes and erases like a stroke, even if this list changes later.
 *
 * Groups are named "Subject · Topic"; the panel filters by the subject before the dot.
 */

const arc = (cx, cy, r, from, to, n = 24) => {
    const pts = [];
    for (let i = 0; i <= n; i++) {
        const a = (from + (to - from) * (i / n)) * Math.PI / 180;
        pts.push([cx + r * Math.cos(a), cy + r * Math.sin(a)]);
    }
    return pts;
};
const circle = (cx, cy, r, n = 32) => arc(cx, cy, r, 0, 360, n);
const ellipse = (cx, cy, rx, ry, n = 32) => circle(0, 0, 1, n).map(([x, y]) => [cx + x * rx, cy + y * ry]);
const rect = (x1, y1, x2, y2) => [[x1, y1], [x2, y1], [x2, y2], [x1, y2], [x1, y1]];
const poly = (cx, cy, r, sides, rot = -90) => arc(cx, cy, r, rot, rot + 360, sides);
const head = (x, y, dir, s = 10) => {          // arrow head at (x, y) pointing in direction dir (degrees)
    const a = dir * Math.PI / 180;
    const p = (da) => [x - s * Math.cos(a + da), y - s * Math.sin(a + da)];
    return [p(0.5), [x, y], p(-0.5)];
};
const arrow = (x1, y1, x2, y2, s = 10) => [[[x1, y1], [x2, y2]], head(x2, y2, Math.atan2(y2 - y1, x2 - x1) * 180 / Math.PI, s)];
const dashed = (x1, y1, x2, y2, n = 6) => {     // a dashed line as n short paths
    const out = [];
    for (let i = 0; i < n; i++) {
        const a = i / n;
        const b = (i + 0.55) / n;
        out.push([[x1 + (x2 - x1) * a, y1 + (y2 - y1) * a], [x1 + (x2 - x1) * b, y1 + (y2 - y1) * b]]);
    }
    return out;
};
const wave = (x1, x2, y, amp, cycles, n = 64, phase = 0) => {
    const pts = [];
    for (let i = 0; i <= n; i++) {
        const t = i / n;
        pts.push([x1 + (x2 - x1) * t, y - amp * Math.sin(phase + t * cycles * Math.PI * 2)]);
    }
    return pts;
};
const backDash = (pts) => pts.reduce((acc, p, i, a) => (i % 2 === 0 && a[i + 1] ? acc.concat([[p, a[i + 1]]]) : acc), []);
const flat = (pts, cy, k = 0.3) => pts.map(([x, y]) => [x, cy + (y - cy) * k]);
const L = (x, y, text, size = 12) => ({ x, y, text, size });
const meter = (letter) => ({ paths: [[[0, 50], [32, 50]], circle(50, 50, 18), [[68, 50], [100, 50]]], labels: [L(50, 51, letter, 20)] });
const tag = (word) => ({ paths: [rect(4, 34, 96, 66)], labels: [L(50, 50, word, word.length > 9 ? 12 : 15)] });
const axes2 = [[[6, 50], [94, 50]], head(94, 50, 0), [[50, 94], [50, 6]], head(50, 6, -90)];
const axes1 = [[[10, 88], [94, 88]], head(94, 88, 0), [[12, 90], [12, 6]], head(12, 6, -90)];

const P = 'Physics';
const C = 'Chemistry';
const B = 'Biology';
const M = 'Mathematics';
const E = 'English grammar';

export const STAMPS = {
    /* ---------------- Physics · Electricity ---------------- */
    cell: { group: `${P} · Electricity`, name: 'Cell', paths: [[[0, 50], [44, 50]], [[44, 30], [44, 70]], [[56, 40], [56, 60]], [[56, 50], [100, 50]]], labels: [L(36, 26, '+', 12), L(64, 30, '−', 12)] },
    battery: { group: `${P} · Electricity`, name: 'Battery', paths: [[[0, 50], [28, 50]], [[28, 30], [28, 70]], [[38, 40], [38, 60]], [[38, 50], [62, 50]], [[62, 30], [62, 70]], [[72, 40], [72, 60]], [[72, 50], [100, 50]]] },
    dcSupply: { group: `${P} · Electricity`, name: 'D.C. supply', paths: [[[0, 50], [25, 50]], circle(30, 50, 5, 16), circle(70, 50, 5, 16), [[75, 50], [100, 50]]], labels: [L(30, 32, '+', 16), L(70, 32, '−', 16)] },
    acSupply: { group: `${P} · Electricity`, name: 'A.C. supply', paths: [[[0, 50], [32, 50]], circle(50, 50, 18), wave(38, 62, 50, 7, 1, 24), [[68, 50], [100, 50]]] },
    wire: { group: `${P} · Electricity`, name: 'Wire', paths: [[[0, 50], [100, 50]]] },
    junction: { group: `${P} · Electricity`, name: 'Junction (joined)', paths: [[[0, 50], [100, 50]], [[50, 50], [50, 100]], circle(50, 50, 4, 12), circle(50, 50, 2, 8)] },
    crossing: { group: `${P} · Electricity`, name: 'Wires crossing (not joined)', paths: [[[0, 50], [100, 50]], [[50, 0], [50, 40]], arc(50, 50, 10, -90, 90, 12), [[50, 60], [50, 100]]] },
    switchOpen: { group: `${P} · Electricity`, name: 'Switch (open)', paths: [[[0, 50], [28, 50]], circle(31, 50, 3, 12), [[33, 48], [68, 30]], circle(71, 50, 3, 12), [[74, 50], [100, 50]]] },
    switchClosed: { group: `${P} · Electricity`, name: 'Switch (closed)', paths: [[[0, 50], [28, 50]], circle(31, 50, 3, 12), [[34, 50], [68, 50]], circle(71, 50, 3, 12), [[74, 50], [100, 50]]] },
    lamp: { group: `${P} · Electricity`, name: 'Bulb (lamp)', paths: [[[0, 50], [32, 50]], circle(50, 50, 18), [[37, 37], [63, 63]], [[63, 37], [37, 63]], [[68, 50], [100, 50]]] },
    resistor: { group: `${P} · Electricity`, name: 'Resistor', paths: [[[0, 50], [25, 50]], rect(25, 40, 75, 60), [[75, 50], [100, 50]]] },
    variable: { group: `${P} · Electricity`, name: 'Variable resistor (rheostat)', paths: [[[0, 50], [25, 50]], rect(25, 40, 75, 60), [[75, 50], [100, 50]], [[22, 76], [78, 24]], head(78, 24, -43)] },
    heater: { group: `${P} · Electricity`, name: 'Heater', paths: [[[0, 50], [25, 50]], rect(25, 40, 75, 60), [[37, 40], [37, 60]], [[50, 40], [50, 60]], [[63, 40], [63, 60]], [[75, 50], [100, 50]]] },
    fuse: { group: `${P} · Electricity`, name: 'Fuse', paths: [rect(30, 43, 70, 57), [[0, 50], [100, 50]]] },
    ldr: { group: `${P} · Electricity`, name: 'LDR (light-dependent resistor)', paths: [[[0, 55], [30, 55]], rect(30, 47, 70, 63), [[70, 55], [100, 55]], circle(50, 55, 26), ...arrow(14, 12, 30, 28, 7), ...arrow(28, 6, 44, 22, 7)] },
    thermistor: { group: `${P} · Electricity`, name: 'Thermistor', paths: [[[0, 50], [25, 50]], rect(25, 40, 75, 60), [[75, 50], [100, 50]], [[18, 72], [30, 72], [80, 26]]] },
    diode: { group: `${P} · Electricity`, name: 'Diode', paths: [[[0, 50], [38, 50]], [[38, 34], [38, 66], [62, 50], [38, 34]], [[62, 34], [62, 66]], [[62, 50], [100, 50]]] },
    led: { group: `${P} · Electricity`, name: 'LED', paths: [[[0, 50], [38, 50]], [[38, 34], [38, 66], [62, 50], [38, 34]], [[62, 34], [62, 66]], [[62, 50], [100, 50]], [[56, 28], [68, 16]], head(68, 16, -45), [[66, 32], [78, 20]], head(78, 20, -45)] },
    capacitor: { group: `${P} · Electricity`, name: 'Capacitor', paths: [[[0, 50], [44, 50]], [[44, 30], [44, 70]], [[56, 30], [56, 70]], [[56, 50], [100, 50]]] },
    ammeter: { group: `${P} · Electricity`, name: 'Ammeter', ...meter('A') },
    voltmeter: { group: `${P} · Electricity`, name: 'Voltmeter', ...meter('V') },
    galvanometer: { group: `${P} · Electricity`, name: 'Galvanometer', ...meter('G') },
    motor: { group: `${P} · Electricity`, name: 'Motor', ...meter('M') },
    bell: { group: `${P} · Electricity`, name: 'Bell', paths: [[[0, 70], [35, 70], [35, 60]], [[100, 70], [65, 70], [65, 60]], [[28, 60], [72, 60]], arc(50, 60, 22, 180, 360, 20)] },
    buzzer: { group: `${P} · Electricity`, name: 'Buzzer', paths: [[[0, 30], [38, 30], [38, 40]], [[100, 30], [62, 30], [62, 40]], [[26, 40], [74, 40], ...arc(50, 40, 24, 0, 180, 20).slice(1)]] },
    speaker: { group: `${P} · Electricity`, name: 'Loudspeaker', paths: [[[0, 44], [30, 44]], [[0, 56], [30, 56]], rect(30, 38, 42, 62), [[42, 38], [62, 22], [62, 78], [42, 62]]] },
    microphone: { group: `${P} · Electricity`, name: 'Microphone', paths: [circle(50, 50, 16), [[34, 32], [34, 68]], [[66, 50], [100, 50]]] },
    earth: { group: `${P} · Electricity`, name: 'Earth', paths: [[[50, 20], [50, 50]], [[30, 50], [70, 50]], [[37, 58], [63, 58]], [[44, 66], [56, 66]]] },
    transformer: { group: `${P} · Electricity`, name: 'Transformer', paths: [[[10, 15], [30, 15], ...[0, 1, 2, 3].flatMap((i) => arc(30, 26 + i * 16, 8, -90, 90, 8)), [10, 82]], [[90, 15], [70, 15], ...[0, 1, 2, 3].flatMap((i) => arc(70, 26 + i * 16, 8, 270, 90, 8)), [90, 82]], [[46, 12], [46, 88]], [[54, 12], [54, 88]]] },
    current: { group: `${P} · Electricity`, name: 'Current arrow (I)', paths: arrow(10, 50, 90, 50), labels: [L(50, 36, 'I', 16)] },

    /* ---------------- Physics · Magnetism ---------------- */
    magnet: { group: `${P} · Magnetism`, name: 'Bar magnet', paths: [rect(10, 38, 90, 62), [[50, 38], [50, 62]]], labels: [L(30, 50, 'N', 18), L(70, 50, 'S', 18)] },
    horseshoe: { group: `${P} · Magnetism`, name: 'Horseshoe magnet', paths: [[[20, 90], [20, 45], ...arc(50, 45, 30, 180, 360, 20).slice(1), [80, 90], [62, 90], [62, 45], ...arc(50, 45, 12, 360, 180, 12).slice(1), [38, 90], [20, 90]], [[20, 76], [38, 76]], [[62, 76], [80, 76]]], labels: [L(29, 84, 'N', 11), L(71, 84, 'S', 11)] },
    fieldLines: { group: `${P} · Magnetism`, name: 'Field round a bar magnet', paths: [rect(30, 44, 70, 56), ellipse(50, 36, 30, 14), ellipse(50, 64, 30, 14), ellipse(50, 30, 44, 24), ellipse(50, 70, 44, 24), head(50, 22, 180, 6), head(50, 78, 180, 6)], labels: [L(38, 50, 'N', 9), L(62, 50, 'S', 9)] },
    compass: { group: `${P} · Magnetism`, name: 'Plotting compass', paths: [circle(50, 50, 30), [[50, 26], [56, 50], [50, 74], [44, 50], [50, 26]], [[44, 50], [56, 50]]], labels: [L(50, 12, 'N', 12)] },
    solenoid: { group: `${P} · Magnetism`, name: 'Coil (solenoid)', paths: [[[0, 30], [15, 30], ...Array.from({ length: 97 }, (_, i) => { const t = i / 96 * 12 * Math.PI; return [15 + t * 70 / (12 * Math.PI) - 6 * Math.sin(t), 50 - 20 * Math.cos(t)]; }), [100, 30]]] },
    electromagnet: { group: `${P} · Magnetism`, name: 'Electromagnet (iron core)', paths: [rect(15, 42, 85, 58), [[25, 34], [31, 66], [37, 34], [43, 66], [49, 34], [55, 66], [61, 34], [67, 66], [73, 34]], [[25, 34], [25, 18], [10, 18]], [[73, 34], [73, 18], [90, 18]]] },

    /* ---------------- Physics · Forces and motion ---------------- */
    forceArrow: { group: `${P} · Forces and motion`, name: 'Force arrow', paths: arrow(10, 50, 90, 50) },
    freeBody: { group: `${P} · Forces and motion`, name: 'Forces on a block', paths: [rect(38, 38, 62, 62), ...arrow(50, 38, 50, 8), ...arrow(50, 62, 50, 92), ...arrow(62, 50, 92, 50), ...arrow(38, 50, 8, 50)], labels: [L(60, 12, 'R'), L(60, 88, 'W'), L(86, 42, 'F'), L(14, 42, 'f')] },
    mass: { group: `${P} · Forces and motion`, name: 'Mass (weight)', paths: [[[50, 5], [50, 30]], rect(30, 30, 70, 80)], labels: [L(50, 56, 'kg', 16)] },
    spring: { group: `${P} · Forces and motion`, name: 'Spring', paths: [[[5, 50], [15, 50], [20, 35], [30, 65], [40, 35], [50, 65], [60, 35], [70, 65], [80, 35], [85, 50], [95, 50]]] },
    springBalance: { group: `${P} · Forces and motion`, name: 'Spring balance', paths: [[[50, 2], [50, 10]], rect(38, 10, 62, 70), [[50, 16], [44, 22], [56, 28], [44, 34], [56, 40], [50, 46]], [[38, 24], [43, 24]], [[38, 36], [43, 36]], [[38, 48], [43, 48]], [[38, 60], [43, 60]], [[50, 70], [50, 82], ...arc(46, 86, 4, 0, 180, 8).slice(1)]] },
    pulley: { group: `${P} · Forces and motion`, name: 'Pulley', paths: [circle(50, 35, 20), circle(50, 35, 3, 8), [[30, 35], [30, 95]], [[70, 35], [70, 80]], [[50, 15], [50, 2]]] },
    lever: { group: `${P} · Forces and motion`, name: 'Lever (see-saw)', paths: [[[5, 45], [95, 45]], [[50, 47], [40, 65], [60, 65], [50, 47]], ...arrow(10, 15, 10, 42), ...arrow(90, 15, 90, 42)], labels: [L(10, 8, 'Effort', 9), L(90, 8, 'Load', 9), L(50, 74, 'Pivot', 9)] },
    incline: { group: `${P} · Forces and motion`, name: 'Inclined plane', paths: [[[10, 85], [90, 85], [90, 35], [10, 85]], [[46, 62.5], [62, 52.5], [53.5, 38.9], [37.5, 48.9], [46, 62.5]], arc(10, 85, 16, -32, 0, 8)], labels: [L(32, 80, 'θ', 10)] },
    pendulum: { group: `${P} · Forces and motion`, name: 'Pendulum', paths: [[[30, 8], [70, 8]], [[50, 8], [67.6, 68.5]], circle(69.5, 75, 7), ...dashed(50, 8, 50, 88, 7), arc(50, 8, 30, 74, 90, 6)], labels: [L(55, 46, 'θ', 9)] },
    trolley: { group: `${P} · Forces and motion`, name: 'Trolley', paths: [rect(15, 40, 85, 65), circle(30, 72, 7), circle(70, 72, 7), [[5, 79], [95, 79]]] },
    speedTime: { group: `${P} · Forces and motion`, name: 'Speed–time graph', paths: [...axes1, [[12, 88], [40, 40], [70, 40], [90, 88]]], labels: [L(94, 97, 't', 12), L(3, 8, 'v', 12)] },
    manometer: { group: `${P} · Forces and motion`, name: 'U-tube manometer', paths: [[[30, 10], [30, 72], ...arc(50, 72, 20, 180, 0, 16).slice(1), [70, 10]], [[38, 10], [38, 72], ...arc(50, 72, 12, 180, 0, 12).slice(1), [62, 10]], [[30, 50], [38, 50]], [[62, 34], [70, 34]]] },

    /* ---------------- Physics · Light and waves ---------------- */
    ray: { group: `${P} · Light and waves`, name: 'Light ray', paths: [[[5, 50], [95, 50]], head(56, 50, 0)] },
    mirror: { group: `${P} · Light and waves`, name: 'Plane mirror', paths: [[[50, 10], [50, 90]], [[50, 18], [60, 10]], [[50, 34], [60, 26]], [[50, 50], [60, 42]], [[50, 66], [60, 58]], [[50, 82], [60, 74]]] },
    reflection: { group: `${P} · Light and waves`, name: 'Reflection at a mirror', paths: [[[5, 80], [95, 80]], ...[12, 28, 44, 60, 76, 92].map((x) => [[x, 80], [x - 7, 88]]), ...dashed(50, 80, 50, 10, 7), [[12, 18], [50, 80], [88, 18]], head(31, 49, 58.5), head(76, 37.5, -58.5)], labels: [L(42, 58, 'i', 10), L(58, 58, 'r', 10)] },
    refraction: { group: `${P} · Light and waves`, name: 'Refraction in a glass block', paths: [rect(20, 35, 80, 65), ...dashed(40, 15, 40, 85, 7), [[15, 8], [40, 35], [55, 65], [80, 92]], head(28, 22, 47), head(68, 79, 47)] },
    convexLens: { group: `${P} · Light and waves`, name: 'Convex lens', paths: [[...arc(14, 50, 50, -53, 53)], [...arc(86, 50, 50, 127, 233)]] },
    concaveLens: { group: `${P} · Light and waves`, name: 'Concave lens', paths: [[[36, 10], [64, 10]], [[36, 90], [64, 90]], [...arc(-6, 50, 51, -52, 52)], [...arc(106, 50, 51, 128, 232)]] },
    concaveMirror: { group: `${P} · Light and waves`, name: 'Concave mirror', paths: [arc(0, 50, 60, -40, 40, 16), ...[-30, -15, 0, 15, 30].map((a) => { const r = a * Math.PI / 180; const x = 60 * Math.cos(r); const y = 50 + 60 * Math.sin(r); return [[x, y], [x + 8, y + 6]]; })] },
    convexMirror: { group: `${P} · Light and waves`, name: 'Convex mirror', paths: [arc(110, 50, 60, 140, 220, 16), ...[150, 165, 180, 195, 210].map((a) => { const r = a * Math.PI / 180; const x = 110 + 60 * Math.cos(r); const y = 50 + 60 * Math.sin(r); return [[x, y], [x + 8, y + 6]]; })] },
    prism: { group: `${P} · Light and waves`, name: 'Prism (dispersion)', paths: [[[50, 12], [82, 74], [18, 74], [50, 12]], [[2, 50], [36, 46]], [[36, 46], [64, 52], [98, 62]], [[64, 52], [98, 72]], [[64, 52], [98, 82]]], labels: [L(94, 56, 'red', 7), L(94, 88, 'violet', 7)] },
    pinhole: { group: `${P} · Light and waves`, name: 'Pinhole camera', paths: [[[40, 25], [40, 48]], [[40, 52], [40, 75]], [[40, 25], [90, 25], [90, 75], [40, 75]], ...arrow(15, 65, 15, 35, 7), [[15, 35], [90, 68]], [[15, 65], [90, 32]], ...arrow(88, 32, 88, 66, 6)] },
    transverse: { group: `${P} · Light and waves`, name: 'Transverse wave', paths: [wave(5, 95, 50, 25, 2), ...dashed(5, 50, 95, 50, 10)] },
    longitudinal: { group: `${P} · Light and waves`, name: 'Longitudinal wave', paths: [5, 9, 13, 16, 19, 22, 28, 36, 46, 54, 58, 61, 64, 67, 72, 80, 90, 95].map((x) => [[x, 30], [x, 70]]), labels: [L(17, 82, 'C', 9), L(41, 82, 'R', 9), L(63, 82, 'C', 9)] },

    /* ---------------- Chemistry · Apparatus ---------------- */
    beaker: { group: `${C} · Apparatus`, name: 'Beaker', paths: [[[20, 12], [25, 15], [25, 85], [75, 85], [75, 15], [80, 12]], [[25, 45], [33, 45]], [[25, 65], [33, 65]]] },
    testTube: { group: `${C} · Apparatus`, name: 'Test tube', paths: [[[40, 8], [40, 74], ...arc(50, 74, 10, 180, 0).slice(1), [60, 8]]] },
    boilingTube: { group: `${C} · Apparatus`, name: 'Test tube in a holder', paths: [[[44, 8], [44, 78], ...arc(50, 78, 6, 180, 0, 8).slice(1), [56, 8]], [[40, 30], [60, 30]], [[60, 30], [92, 22]], [[60, 34], [92, 26]]] },
    conical: { group: `${C} · Apparatus`, name: 'Conical flask', paths: [[[42, 8], [42, 36], [16, 86], [84, 86], [58, 36], [58, 8]]] },
    roundFlask: { group: `${C} · Apparatus`, name: 'Round-bottom flask', paths: [[[44, 8], [44, 41], ...arc(50, 64, 24, 256, -76, 32).slice(1), [56, 8]]] },
    cylinder: { group: `${C} · Apparatus`, name: 'Measuring cylinder', paths: [[[38, 8], [38, 86], [62, 86], [62, 8]], [[28, 90], [72, 90]], [[38, 30], [46, 30]], [[38, 48], [46, 48]], [[38, 66], [46, 66]]] },
    burette: { group: `${C} · Apparatus`, name: 'Burette', paths: [[[45, 4], [45, 74], [55, 74], [55, 4]], [[40, 78], [60, 78]], [[48, 74], [50, 92], [52, 74]], [[45, 16], [49, 16]], [[45, 30], [49, 30]], [[45, 44], [49, 44]], [[45, 58], [49, 58]]] },
    pipette: { group: `${C} · Apparatus`, name: 'Pipette', paths: [[[48, 4], [48, 33]], [[52, 4], [52, 33]], ellipse(50, 45, 9, 13), [[48, 57], [48, 88], [50, 95], [52, 88], [52, 57]], [[43, 18], [57, 18]]] },
    funnel: { group: `${C} · Apparatus`, name: 'Filter funnel', paths: [[[18, 14], [82, 14]], [[18, 14], [45, 52], [45, 90]], [[82, 14], [55, 52], [55, 90]]] },
    sepFunnel: { group: `${C} · Apparatus`, name: 'Separating funnel', paths: [[[44, 6], [44, 16], [25, 50], [45, 74], [45, 94]], [[56, 6], [56, 16], [75, 50], [55, 74], [55, 94]], [[36, 82], [64, 82]], [[30, 46], [70, 46]]] },
    burner: { group: `${C} · Apparatus`, name: 'Bunsen burner', paths: [[[44, 30], [44, 80]], [[56, 30], [56, 80]], rect(28, 80, 72, 90), [[50, 4], [58, 16], [56, 26], [44, 26], [42, 16], [50, 4]]] },
    thermometer: { group: `${C} · Apparatus`, name: 'Thermometer', paths: [[[46, 70], [46, 10], ...arc(50, 10, 4, 180, 360, 8).slice(1), [54, 70]], circle(50, 78, 9), [[54, 25], [58, 25]], [[54, 40], [58, 40]], [[54, 55], [58, 55]]] },
    tripod: { group: `${C} · Apparatus`, name: 'Tripod and gauze', paths: [[[16, 30], [84, 30]], [[30, 30], [22, 90]], [[50, 30], [50, 90]], [[70, 30], [78, 90]]] },
    stand: { group: `${C} · Apparatus`, name: 'Retort stand and clamp', paths: [rect(12, 88, 62, 95), [[24, 88], [24, 6]], [[24, 32], [62, 32]], [[62, 26], [62, 38]], [[62, 26], [72, 26]], [[62, 38], [72, 38]]] },
    dropper: { group: `${C} · Apparatus`, name: 'Dropper', paths: [ellipse(50, 18, 9, 12), [[45, 30], [45, 70], [50, 84], [55, 70], [55, 30]]] },
    evaporating: { group: `${C} · Apparatus`, name: 'Evaporating dish', paths: [[[8, 36], ...arc(50, 20, 40, 160, 20, 20), [92, 36]]] },
    crucible: { group: `${C} · Apparatus`, name: 'Crucible and lid', paths: [[[30, 30], [70, 30], [62, 76], [38, 76], [30, 30]], [[26, 24], [74, 24]], [[46, 24], [46, 18], [54, 18], [54, 24]]] },
    gasJar: { group: `${C} · Apparatus`, name: 'Gas jar and lid', paths: [[[30, 12], [30, 88], [70, 88], [70, 12]], [[24, 9], [76, 9]]] },
    syringe: { group: `${C} · Apparatus`, name: 'Gas syringe', paths: [rect(10, 40, 75, 60), [[2, 50], [10, 50]], [[60, 44], [60, 56]], [[60, 50], [95, 50]], [[95, 40], [95, 60]], [[20, 40], [20, 46]], [[30, 40], [30, 46]], [[40, 40], [40, 46]], [[50, 40], [50, 46]]] },
    spatula: { group: `${C} · Apparatus`, name: 'Spatula', paths: [[[8, 50], [68, 50]], ellipse(82, 50, 13, 6)] },
    mortar: { group: `${C} · Apparatus`, name: 'Mortar and pestle', paths: [[[12, 50], ...arc(50, 50, 38, 180, 0, 20).slice(0), [88, 50], [12, 50]], [[58, 60], [84, 14]], [[66, 64], [90, 18]], [[84, 14], [90, 18]]] },

    /* ---------------- Chemistry · Set-ups ---------------- */
    filtration: { group: `${C} · Set-ups`, name: 'Filtration', paths: [[[25, 8], [75, 8]], [[25, 8], [46, 34], [46, 52]], [[75, 8], [54, 34], [54, 52]], dashed(31, 12, 50, 34, 1)[0], [[42, 44], [42, 56], [18, 92], [82, 92], [58, 56], [58, 44]], [[28, 80], [72, 80]]], labels: [L(85, 22, 'filter', 8), L(88, 84, 'filtrate', 8)] },
    distillation: { group: `${C} · Set-ups`, name: 'Distillation', paths: [circle(22, 62, 14), [[18, 49], [18, 26]], [[26, 49], [26, 26]], [[22, 10], [22, 42]], [[26, 36], [90, 78]], [[44, 44], [84, 70], [80, 76], [40, 50], [44, 44]], [[78, 72], [78, 95], [98, 95], [98, 72]], [[22, 82], [25, 88], [22, 94], [19, 88], [22, 82]]], labels: [L(48, 36, 'water out', 7), L(70, 84, 'water in', 7)] },
    overWater: { group: `${C} · Set-ups`, name: 'Collecting gas over water', paths: [[[5, 40], [5, 92], [95, 92], [95, 40]], [[5, 55], [95, 55]], [[55, 75], [55, 20], [82, 20], [82, 75]], [[10, 8], [10, 30], [40, 30], [40, 84], [68, 84], [68, 66]]] },
    electrolysis: { group: `${C} · Set-ups`, name: 'Electrolysis', paths: [[[15, 30], [15, 92], [85, 92], [85, 30]], [[15, 44], [85, 44]], rect(30, 22, 36, 78), rect(64, 22, 70, 78), [[33, 22], [33, 8], [44, 8]], [[67, 22], [67, 8], [56, 8]], [[44, 0], [44, 16]], [[56, 4], [56, 12]]], labels: [L(22, 16, '+', 12), L(78, 16, '−', 12)] },
    chromatography: { group: `${C} · Set-ups`, name: 'Paper chromatography', paths: [rect(30, 6, 70, 90), [[22, 80], [78, 80]], [[22, 80], [22, 96], [78, 96], [78, 80]], ...dashed(30, 72, 70, 72, 6), circle(40, 72, 2.5, 8), circle(60, 72, 2.5, 8), circle(40, 40, 3, 8), circle(40, 28, 3, 8), circle(60, 50, 3, 8)] },

    /* ---------------- Chemistry · Particles and reactions ---------------- */
    atom: { group: `${C} · Particles and reactions`, name: 'Atom (electron shells)', paths: [circle(50, 50, 8), circle(50, 50, 22), circle(50, 50, 38), circle(72, 50, 2.5, 8), circle(28, 50, 2.5, 8), ...[0, 45, 90, 135, 180, 225, 270, 315].map((a) => circle(50 + 38 * Math.cos(a * Math.PI / 180 + 0.35), 50 + 38 * Math.sin(a * Math.PI / 180 + 0.35), 2.5, 8))], labels: [L(50, 51, '+', 9)] },
    solid: { group: `${C} · Particles and reactions`, name: 'Particles: solid', paths: [rect(14, 14, 86, 86), ...[0, 1, 2, 3].flatMap((i) => [0, 1, 2, 3].map((j) => circle(23 + i * 18, 23 + j * 18, 8, 16)))] },
    liquid: { group: `${C} · Particles and reactions`, name: 'Particles: liquid', paths: [[[14, 14], [14, 86], [86, 86], [86, 14]], ...[[24, 77], [41, 78], [58, 76], [76, 77], [30, 61], [48, 60], [67, 61], [22, 45], [40, 44], [78, 46]].map(([x, y]) => circle(x, y, 8, 16))] },
    gas: { group: `${C} · Particles and reactions`, name: 'Particles: gas', paths: [rect(14, 14, 86, 86), ...[[28, 26], [70, 30], [46, 54], [24, 74], [74, 72]].map(([x, y]) => circle(x, y, 7, 16)), ...arrow(34, 30, 44, 36, 5), ...arrow(64, 36, 56, 44, 5), ...arrow(52, 60, 60, 68, 5)] },
    covalent: { group: `${C} · Particles and reactions`, name: 'Covalent bond (shared pair)', paths: [circle(36, 50, 24), circle(64, 50, 24)], labels: [L(24, 50, 'H', 14), L(76, 50, 'H', 14), L(50, 44, '•', 14), L(50, 57, '×', 12)] },
    ionic: { group: `${C} · Particles and reactions`, name: 'Ions (ionic bond)', paths: [circle(28, 50, 20), circle(72, 50, 22), [[4, 24], [2, 24], [2, 76], [4, 76]], [[46, 24], [48, 24], [48, 76], [46, 76]], [[52, 22], [50, 22], [50, 78], [52, 78]], [[96, 22], [98, 22], [98, 78], [96, 78]]], labels: [L(28, 50, 'Na', 12), L(72, 50, 'Cl', 12), L(46, 18, '+', 11), L(96, 16, '−', 11)] },
    methane: { group: `${C} · Particles and reactions`, name: 'Structural formula (CH₄)', paths: [[[50, 40], [50, 18]], [[50, 60], [50, 82]], [[40, 50], [18, 50]], [[60, 50], [82, 50]]], labels: [L(50, 50, 'C', 16), L(50, 10, 'H', 14), L(50, 90, 'H', 14), L(10, 50, 'H', 14), L(90, 50, 'H', 14)] },
    ring: { group: `${C} · Particles and reactions`, name: 'Hexagon ring', paths: [poly(50, 50, 36, 6, 30), circle(50, 50, 22)] },
    energyProfile: { group: `${C} · Particles and reactions`, name: 'Energy profile (exothermic)', paths: [...axes1, [[14, 40], [30, 40], ...arc(46, 40, 16, 180, 360, 12).slice(1), [62, 40], [62, 40], [70, 66], [92, 66]], ...arrow(80, 40, 80, 64, 6)], labels: [L(94, 97, 'progress', 7), L(10, 4, 'energy', 7), L(20, 34, 'reactants', 7), L(84, 74, 'products', 7)] },
    phScale: { group: `${C} · Particles and reactions`, name: 'pH scale', paths: [rect(4, 40, 96, 60), ...Array.from({ length: 13 }, (_, i) => [[4 + (i + 1) * 92 / 14, 40], [4 + (i + 1) * 92 / 14, 60]])], labels: [L(7, 70, '1', 9), L(50, 70, '7', 9), L(93, 70, '14', 9), L(16, 30, 'acid', 9), L(50, 30, 'neutral', 9), L(85, 30, 'alkali', 9)] },

    /* ---------------- Biology · Cells and organisms ---------------- */
    plantCell: { group: `${B} · Cells and organisms`, name: 'Plant cell', paths: [rect(12, 18, 88, 82), rect(16, 22, 84, 78), circle(32, 40, 9), ellipse(62, 54, 14, 17), ellipse(28, 66, 5, 3, 12), ellipse(76, 30, 5, 3, 12)] },
    animalCell: { group: `${B} · Cells and organisms`, name: 'Animal cell', paths: [ellipse(50, 50, 40, 30), circle(44, 46, 10), circle(44, 46, 3, 8), ellipse(70, 60, 5, 3, 12), ellipse(30, 64, 4, 2.5, 12)] },
    bacterium: { group: `${B} · Cells and organisms`, name: 'Bacterium', paths: [[[30, 36], [70, 36], ...arc(70, 50, 14, -90, 90, 12).slice(1), [30, 64], ...arc(30, 50, 14, 90, 270, 12).slice(1)], [[34, 44], [40, 54], [46, 46], [52, 56], [58, 46], [64, 54]], [[84, 50], [88, 42], [94, 54], [98, 46]]] },
    amoeba: { group: `${B} · Cells and organisms`, name: 'Amoeba', paths: [[[30, 30], [44, 18], [56, 28], [72, 20], [84, 36], [76, 52], [88, 66], [70, 82], [52, 74], [34, 86], [18, 70], [24, 52], [14, 38], [30, 30]], circle(50, 50, 9), circle(66, 64, 5, 12)] },
    mitochondrion: { group: `${B} · Cells and organisms`, name: 'Mitochondrion', paths: [ellipse(50, 50, 42, 22), ellipse(50, 50, 37, 17), [[18, 50], [24, 36], [30, 62], [38, 36], [46, 62], [54, 36], [62, 62], [70, 36], [76, 62], [82, 50]]] },
    chloroplast: { group: `${B} · Cells and organisms`, name: 'Chloroplast', paths: [ellipse(50, 50, 42, 24), ...[30, 50, 70].flatMap((x) => [rect(x - 7, 36, x + 7, 40), rect(x - 7, 44, x + 7, 48), rect(x - 7, 52, x + 7, 56), rect(x - 7, 60, x + 7, 64)])] },
    virus: { group: `${B} · Cells and organisms`, name: 'Virus', paths: [poly(50, 30, 20, 6, 0), [[46, 47], [46, 72]], [[54, 47], [54, 72]], [[46, 72], [30, 90]], [[54, 72], [70, 90]], [[46, 72], [40, 92]], [[54, 72], [60, 92]], [[44, 72], [56, 72]]] },
    chromosome: { group: `${B} · Cells and organisms`, name: 'Chromosome', paths: [[[38, 10], [62, 90]], [[46, 10], [70, 90]], [[62, 10], [38, 90]], [[70, 10], [46, 90]], circle(54, 50, 4, 12)] },
    dna: { group: `${B} · Cells and organisms`, name: 'DNA double helix', paths: [wave(5, 95, 50, 28, 2), wave(5, 95, 50, 28, 2, 64, Math.PI), ...[14, 23, 36, 45, 64, 73, 86].map((x) => { const y = 28 * Math.sin((x - 5) / 90 * 4 * Math.PI); return [[x, 50 - y], [x, 50 + y]]; })] },
    microscope: { group: `${B} · Cells and organisms`, name: 'Light microscope', paths: [rect(22, 88, 78, 95), [[66, 88], [66, 58], [58, 30]], rect(30, 54, 70, 60), [[38, 6], [50, 6], [54, 44], [42, 44], [38, 6]], [[40, 44], [44, 52]], circle(70, 30, 5, 12), [[42, 74], [56, 74], [60, 82], [38, 82], [42, 74]]], labels: [L(20, 6, 'eyepiece', 7), L(20, 50, 'objective', 7), L(88, 57, 'stage', 7)] },
    petri: { group: `${B} · Cells and organisms`, name: 'Petri dish (colonies)', paths: [circle(50, 50, 40), circle(50, 50, 36), circle(36, 40, 5, 12), circle(60, 34, 4, 12), circle(62, 62, 6, 12), circle(40, 64, 3, 12)] },
    insect: { group: `${B} · Cells and organisms`, name: 'Insect', paths: [circle(50, 18, 8), ellipse(50, 38, 9, 12), ellipse(50, 68, 12, 20), [[46, 11], [36, 2]], [[54, 11], [64, 2]], ...[32, 38, 44].flatMap((y) => [[[41, y], [24, y - 8], [16, y + 4]], [[59, y], [76, y - 8], [84, y + 4]]])] },

    /* ---------------- Biology · Human body ---------------- */
    heart: { group: `${B} · Human body`, name: 'Heart outline', paths: [[[50, 88], [18, 56], ...arc(32, 34, 17, 150, 330, 12), ...arc(68, 34, 17, 210, 390, 12), [82, 56], [50, 88]]] },
    heartChambers: { group: `${B} · Human body`, name: 'Heart (four chambers)', paths: [ellipse(50, 56, 36, 38), [[50, 20], [50, 92]], [[16, 46], [84, 46]], [[30, 18], [30, 4]], [[40, 18], [40, 4]], [[60, 18], [62, 2]], [[70, 20], [80, 6]]], labels: [L(32, 34, 'RA', 9), L(68, 34, 'LA', 9), L(34, 66, 'RV', 9), L(66, 66, 'LV', 9)] },
    lungs: { group: `${B} · Human body`, name: 'Lungs and trachea', paths: [[[46, 4], [46, 30]], [[54, 4], [54, 30]], [[46, 30], [32, 44]], [[54, 30], [68, 44]], [[42, 34], [28, 22], [12, 40], [10, 74], [20, 90], [40, 86], [44, 50], [42, 34]], [[58, 34], [72, 22], [88, 40], [90, 74], [80, 90], [60, 86], [56, 50], [58, 34]], [[46, 10], [54, 10]], [[46, 18], [54, 18]]] },
    eye: { group: `${B} · Human body`, name: 'Eye', paths: [circle(50, 50, 34), arc(18, 50, 20, -60, 60, 12), ellipse(24, 50, 5, 14), [[22, 34], [30, 30]], [[22, 66], [30, 70]], [[84, 46], [98, 44]], [[84, 54], [98, 56]]], labels: [L(8, 22, 'cornea', 7), L(24, 74, 'lens', 7), L(66, 50, 'retina', 7), L(92, 36, 'nerve', 7)] },
    tooth: { group: `${B} · Human body`, name: 'Tooth', paths: [[[26, 30], [30, 12], [42, 18], [50, 10], [58, 18], [70, 12], [74, 30], [70, 52], [64, 92], [56, 92], [52, 60], [48, 60], [44, 92], [36, 92], [30, 52], [26, 30]], [[30, 40], [70, 40]], [[38, 46], [62, 46], [58, 70], [42, 70], [38, 46]]], labels: [L(86, 24, 'enamel', 7), L(88, 40, 'gum line', 7)] },
    kidney: { group: `${B} · Human body`, name: 'Kidney', paths: [[[48, 12], [30, 14], [18, 30], [16, 56], [24, 80], [44, 88], [58, 78], [56, 62], [50, 54], [56, 44], [58, 26], [48, 12]], [[52, 50], [68, 54], [72, 94]], [[56, 46], [78, 40]]], labels: [L(86, 96, 'ureter', 7)] },
    villus: { group: `${B} · Human body`, name: 'Villus', paths: [[[30, 92], [30, 30], ...arc(50, 30, 20, 180, 360, 16).slice(1), [70, 92]], [[40, 92], [40, 34], ...arc(50, 34, 10, 180, 360, 10).slice(1), [60, 92]], [[50, 92], [50, 30]]], labels: [L(86, 30, 'lacteal', 7)] },
    redCell: { group: `${B} · Human body`, name: 'Red and white blood cells', paths: [circle(30, 50, 20), circle(30, 50, 9), circle(72, 50, 22), [[62, 46], [68, 40], [74, 48], [80, 42], [84, 52], [76, 58], [66, 56], [62, 46]]], labels: [L(30, 82, 'red', 9), L(72, 82, 'white', 9)] },
    neuron: { group: `${B} · Human body`, name: 'Neuron (nerve cell)', paths: [circle(20, 50, 11), circle(20, 50, 4, 8), [[12, 42], [4, 30]], [[10, 52], [2, 56]], [[16, 61], [10, 74]], [[24, 39], [28, 28]], [[31, 50], [84, 50]], ...[38, 50, 62, 74].map((x) => rect(x - 4, 46, x + 4, 54)), [[84, 50], [96, 40]], [[84, 50], [98, 50]], [[84, 50], [96, 60]]] },

    /* ---------------- Biology · Plants and ecology ---------------- */
    leaf: { group: `${B} · Plants and ecology`, name: 'Leaf', paths: [[[50, 6], [70, 25], [74, 50], [62, 76], [50, 88], [38, 76], [26, 50], [30, 25], [50, 6]], [[50, 6], [50, 98]], [[50, 35], [64, 28]], [[50, 50], [68, 44]], [[50, 65], [62, 60]], [[50, 35], [36, 28]], [[50, 50], [32, 44]], [[50, 65], [38, 60]]] },
    leafSection: { group: `${B} · Plants and ecology`, name: 'Leaf cross-section', paths: [rect(4, 10, 96, 90), [[4, 18], [96, 18]], ...[10, 22, 34, 46, 58, 70, 82].map((x) => rect(x, 20, x + 9, 44)), ...[[14, 56], [32, 62], [52, 54], [72, 62], [88, 54], [24, 72], [62, 74]].map(([x, y]) => circle(x, y, 6, 12)), [[4, 82], [44, 82]], [[56, 82], [96, 82]], ellipse(44, 84, 3, 4, 8), ellipse(56, 84, 3, 4, 8)], labels: [L(50, 14, 'upper epidermis', 6), L(50, 96, 'stoma', 7)] },
    flower: { group: `${B} · Plants and ecology`, name: 'Flower (parts)', paths: [[[50, 98], [50, 70]], [[38, 70], [62, 70]], [[38, 70], [14, 50], [20, 26], [40, 60]], [[62, 70], [86, 50], [80, 26], [60, 60]], [[46, 68], [44, 48], [50, 30], [56, 48], [54, 68]], ellipse(50, 26, 4, 5, 8), [[42, 66], [32, 36]], ellipse(32, 32, 4, 5, 8), [[58, 66], [68, 36]], ellipse(68, 32, 4, 5, 8)], labels: [L(10, 58, 'petal', 7), L(50, 16, 'stigma', 7), L(80, 26, 'anther', 7)] },
    plant: { group: `${B} · Plants and ecology`, name: 'Whole plant', paths: [[[50, 66], [50, 10]], [[50, 40], [70, 28], [84, 34], [70, 42], [50, 40]], [[50, 26], [30, 14], [16, 20], [30, 30], [50, 26]], [[6, 66], [94, 66]], [[50, 66], [50, 94]], [[50, 74], [36, 88]], [[50, 78], [64, 92]], [[50, 84], [44, 96]]] },
    root: { group: `${B} · Plants and ecology`, name: 'Root hairs', paths: [[[50, 4], [50, 94]], [[46, 4], [46, 90], [50, 98], [54, 90], [54, 4]], ...[30, 40, 50, 60].flatMap((y) => [[[46, y], [30, y - 6]], [[54, y + 4], [70, y - 2]]])] },
    seed: { group: `${B} · Plants and ecology`, name: 'Bean seed', paths: [[[50, 14], [76, 22], [86, 50], [76, 80], [50, 86], [28, 80], [38, 62], [36, 40], [26, 22], [50, 14]], [[42, 46], [56, 40], [62, 52], [54, 60], [42, 56]]], labels: [L(14, 50, 'hilum', 7)] },
    sun: { group: `${B} · Plants and ecology`, name: 'Sun', paths: [circle(50, 50, 18), ...[0, 45, 90, 135, 180, 225, 270, 315].map((a) => { const r = a * Math.PI / 180; return [[50 + 26 * Math.cos(r), 50 + 26 * Math.sin(r)], [50 + 40 * Math.cos(r), 50 + 40 * Math.sin(r)]]; })] },
    cloud: { group: `${B} · Plants and ecology`, name: 'Cloud and rain', paths: [[[20, 50], ...arc(24, 40, 10, 150, 270, 8), ...arc(42, 30, 14, 200, 340, 10), ...arc(64, 34, 12, 240, 360, 8), ...arc(76, 44, 8, 270, 450, 8), [20, 52]], [[30, 62], [26, 72]], [[46, 62], [42, 72]], [[62, 62], [58, 72]], [[38, 78], [34, 88]], [[54, 78], [50, 88]]] },
    foodChain: { group: `${B} · Plants and ecology`, name: 'Food chain (boxes)', paths: [rect(2, 38, 26, 62), ...arrow(27, 50, 37, 50, 5), rect(38, 38, 62, 62), ...arrow(63, 50, 73, 50, 5), rect(74, 38, 98, 62)] },

    /* ---------------- Mathematics · Graphs and number ---------------- */
    axes: { group: `${M} · Graphs and number`, name: 'Axes (first quadrant)', paths: axes1, labels: [L(94, 97, 'x', 14), L(4, 8, 'y', 14)] },
    axes4: { group: `${M} · Graphs and number`, name: 'Axes (four quadrants)', paths: axes2, labels: [L(94, 58, 'x', 12), L(58, 6, 'y', 12), L(45, 56, 'O', 10)] },
    grid: { group: `${M} · Graphs and number`, name: 'Square grid', paths: [...[10, 26, 42, 58, 74, 90].map((x) => [[x, 10], [x, 90]]), ...[10, 26, 42, 58, 74, 90].map((y) => [[10, y], [90, y]])] },
    straightLine: { group: `${M} · Graphs and number`, name: 'Straight-line graph', paths: [...axes2, [[14, 86], [86, 22]]], labels: [L(80, 36, 'y = mx + c', 8)] },
    parabola: { group: `${M} · Graphs and number`, name: 'Parabola (quadratic)', paths: [...axes2, Array.from({ length: 33 }, (_, i) => { const x = -1 + i / 16; return [50 + x * 30, 80 - x * x * 60]; })] },
    sineCurve: { group: `${M} · Graphs and number`, name: 'Sine curve', paths: [[[6, 50], [94, 50]], head(94, 50, 0), [[10, 90], [10, 10]], head(10, 10, -90), wave(10, 90, 50, 30, 1)], labels: [L(30, 14, '1', 8), L(50, 58, '180°', 8), L(90, 58, '360°', 8)] },
    numberLine: { group: `${M} · Graphs and number`, name: 'Number line', paths: [[[4, 50], [96, 50]], head(96, 50, 0), head(4, 50, 180), ...[10, 26, 42, 58, 74, 90].map((x) => [[x, 44], [x, 56]])] },
    inequality: { group: `${M} · Graphs and number`, name: 'Inequality on a number line', paths: [[[4, 60], [96, 60]], ...[10, 26, 42, 58, 74, 90].map((x) => [[x, 55], [x, 65]]), circle(42, 40, 5, 12), [[47, 40], [92, 40]], head(92, 40, 0)] },
    fractionBar: { group: `${M} · Graphs and number`, name: 'Fraction bar (quarters)', paths: [rect(6, 38, 94, 62), [[28, 38], [28, 62]], [[50, 38], [50, 62]], [[72, 38], [72, 62]]] },
    fractionCircle: { group: `${M} · Graphs and number`, name: 'Fraction circle', paths: [circle(50, 50, 40), [[50, 10], [50, 90]], [[10, 50], [90, 50]]] },
    sqrt: { group: `${M} · Graphs and number`, name: 'Square-root sign', paths: [[[8, 60], [18, 54], [32, 90], [48, 14], [96, 14]]] },
    longDivision: { group: `${M} · Graphs and number`, name: 'Long division', paths: [[[30, 26], [96, 26]], [[30, 26], ...arc(10, 56, 36, -56, 56, 10).slice(1)]] },
    matrix: { group: `${M} · Graphs and number`, name: 'Matrix brackets (2 × 2)', paths: [[[26, 14], [16, 14], [16, 86], [26, 86]], [[74, 14], [84, 14], [84, 86], [74, 86]]] },

    /* ---------------- Mathematics · Shapes and angles ---------------- */
    triangle: { group: `${M} · Shapes and angles`, name: 'Triangle', paths: [[[50, 12], [88, 84], [12, 84], [50, 12]]] },
    rightTriangle: { group: `${M} · Shapes and angles`, name: 'Right-angled triangle', paths: [[[20, 82], [82, 82], [20, 18], [20, 82]], [[20, 72], [30, 72], [30, 82]]] },
    isosceles: { group: `${M} · Shapes and angles`, name: 'Isosceles triangle', paths: [[[50, 10], [78, 86], [22, 86], [50, 10]], [[32, 46], [40, 49]], [[60, 49], [68, 46]]] },
    equilateral: { group: `${M} · Shapes and angles`, name: 'Equilateral triangle', paths: [poly(50, 56, 40, 3), [[28, 40], [34, 44]], [[66, 44], [72, 40]], [[50, 72], [50, 80]]] },
    square: { group: `${M} · Shapes and angles`, name: 'Square', paths: [rect(16, 16, 84, 84), [[16, 26], [26, 26], [26, 16]]] },
    rectangle: { group: `${M} · Shapes and angles`, name: 'Rectangle', paths: [rect(6, 26, 94, 74)] },
    parallelogram: { group: `${M} · Shapes and angles`, name: 'Parallelogram', paths: [[[24, 26], [94, 26], [76, 74], [6, 74], [24, 26]]] },
    rhombus: { group: `${M} · Shapes and angles`, name: 'Rhombus', paths: [[[50, 8], [86, 50], [50, 92], [14, 50], [50, 8]]] },
    trapezium: { group: `${M} · Shapes and angles`, name: 'Trapezium', paths: [[[30, 26], [70, 26], [94, 74], [6, 74], [30, 26]]] },
    kite: { group: `${M} · Shapes and angles`, name: 'Kite', paths: [[[50, 6], [78, 34], [50, 94], [22, 34], [50, 6]]] },
    pentagon: { group: `${M} · Shapes and angles`, name: 'Pentagon', paths: [poly(50, 52, 42, 5)] },
    hexagon: { group: `${M} · Shapes and angles`, name: 'Hexagon', paths: [poly(50, 50, 42, 6, 0)] },
    octagon: { group: `${M} · Shapes and angles`, name: 'Octagon', paths: [poly(50, 50, 42, 8, 22.5)] },
    circleRadius: { group: `${M} · Shapes and angles`, name: 'Circle with radius', paths: [circle(50, 50, 38), [[50, 50], [88, 50]], circle(50, 50, 2, 8)], labels: [L(69, 43, 'r', 14)] },
    circleParts: { group: `${M} · Shapes and angles`, name: 'Chord, diameter, tangent', paths: [circle(50, 50, 34), [[16, 50], [84, 50]], [[24, 28], [76, 28]], [[2, 84], [98, 84]], circle(50, 50, 2, 8)], labels: [L(50, 22, 'chord', 8), L(50, 44, 'diameter', 8), L(84, 92, 'tangent', 8)] },
    sector: { group: `${M} · Shapes and angles`, name: 'Sector and arc', paths: [[[30, 70], [92, 70], ...arc(30, 70, 62, 0, -60, 12).slice(1), [30, 70]], arc(30, 70, 14, -60, 0, 6)], labels: [L(52, 62, 'θ', 10)] },
    semicircle: { group: `${M} · Shapes and angles`, name: 'Semicircle', paths: [[[8, 70], [92, 70], ...arc(50, 70, 42, 0, -180, 24).slice(1)]] },
    angleCentre: { group: `${M} · Shapes and angles`, name: 'Angle at the centre', paths: [circle(50, 50, 40), [[22, 79], [50, 50], [78, 79]], [[22, 79], [50, 10], [78, 79]], circle(50, 50, 2, 8)] },
    cyclicQuad: { group: `${M} · Shapes and angles`, name: 'Cyclic quadrilateral', paths: [circle(50, 50, 40), [[50, 10], [88, 62], [36, 88], [12, 38], [50, 10]]] },
    angle: { group: `${M} · Shapes and angles`, name: 'Angle', paths: [[[90, 80], [15, 80], [75, 25]], [...arc(15, 80, 22, -42, 0, 10)]], labels: [L(46, 72, 'θ', 14)] },
    parallel: { group: `${M} · Shapes and angles`, name: 'Parallel lines and transversal', paths: [[[4, 34], [96, 34]], [[4, 70], [96, 70]], [[26, 96], [74, 6]], head(80, 34, 0, 6), head(80, 70, 0, 6)], labels: [L(46, 28, 'a', 9), L(36, 78, 'b', 9)] },
    perpendicular: { group: `${M} · Shapes and angles`, name: 'Perpendicular lines', paths: [[[6, 70], [94, 70]], [[50, 70], [50, 8]], [[50, 60], [60, 60], [60, 70]]] },
    protractor: { group: `${M} · Shapes and angles`, name: 'Protractor', paths: [[[6, 80], [94, 80], ...arc(50, 80, 44, 0, -180, 36).slice(1)], arc(50, 80, 30, 0, -180, 24), ...Array.from({ length: 17 }, (_, i) => { const a = -(i + 1) * 10 * Math.PI / 180; return [[50 + 44 * Math.cos(a), 80 + 44 * Math.sin(a)], [50 + 38 * Math.cos(a), 80 + 38 * Math.sin(a)]]; }), circle(50, 80, 2, 8)] },
    bearing: { group: `${M} · Shapes and angles`, name: 'Bearing (from North)', paths: [...arrow(30, 80, 30, 8, 8), [[30, 80], [84, 40]], head(84, 40, -36.5), arc(30, 80, 22, -90, -36.5, 10)], labels: [L(30, 4, 'N', 12), L(46, 60, 'θ', 10)] },
    bisector: { group: `${M} · Shapes and angles`, name: 'Perpendicular bisector', paths: [[[14, 60], [86, 60]], arc(14, 60, 46, -50, -30, 6), arc(14, 60, 46, 30, 50, 6), arc(86, 60, 46, 230, 210, 6), arc(86, 60, 46, 150, 130, 6), [[50, 8], [50, 96]]], labels: [L(10, 70, 'A', 10), L(90, 70, 'B', 10)] },
    vector: { group: `${M} · Shapes and angles`, name: 'Vector', paths: [...arrow(12, 80, 86, 22, 10), head(49, 51, -38, 7)], labels: [L(42, 40, 'a', 14)] },

    /* ---------------- Mathematics · Solids ---------------- */
    cube: { group: `${M} · Solids`, name: 'Cube', paths: [rect(14, 34, 66, 86), [[14, 34], [34, 14], [86, 14], [66, 34]], [[86, 14], [86, 66], [66, 86]], ...dashed(34, 14, 34, 66, 5), ...dashed(34, 66, 86, 66, 5), ...dashed(34, 66, 14, 86, 3)] },
    cuboid: { group: `${M} · Solids`, name: 'Cuboid', paths: [rect(6, 40, 70, 86), [[6, 40], [26, 22], [94, 22], [70, 40]], [[94, 22], [94, 68], [70, 86]], ...dashed(26, 22, 26, 68, 5), ...dashed(26, 68, 94, 68, 6), ...dashed(26, 68, 6, 86, 3)] },
    cylinderSolid: { group: `${M} · Solids`, name: 'Cylinder', paths: [ellipse(50, 18, 30, 9), [[20, 18], [20, 82]], [[80, 18], [80, 82]], flat(arc(50, 82, 30, 0, 180, 16), 82), ...backDash(flat(arc(50, 82, 30, 180, 360, 12), 82))], labels: [L(66, 14, 'r', 9), L(88, 50, 'h', 9)] },
    cone: { group: `${M} · Solids`, name: 'Cone', paths: [[[50, 8], [16, 80]], [[50, 8], [84, 80]], flat(arc(50, 80, 34, 0, 180, 16), 80), ...dashed(50, 8, 50, 80, 6), ...dashed(50, 80, 84, 80, 3)], labels: [L(56, 46, 'h', 10), L(68, 74, 'r', 10)] },
    sphere: { group: `${M} · Solids`, name: 'Sphere', paths: [circle(50, 50, 40), flat(arc(50, 50, 40, 0, 180, 16), 50), ...backDash(flat(arc(50, 50, 40, 180, 360, 12), 50))] },
    pyramid: { group: `${M} · Solids`, name: 'Square pyramid', paths: [[[50, 8], [10, 78]], [[50, 8], [62, 90]], [[50, 8], [92, 70]], [[10, 78], [62, 90], [92, 70]], ...dashed(10, 78, 40, 58, 4), ...dashed(40, 58, 92, 70, 5), ...dashed(50, 8, 40, 58, 5)] },
    prismSolid: { group: `${M} · Solids`, name: 'Triangular prism', paths: [[[10, 82], [36, 36], [62, 82], [10, 82]], [[36, 36], [74, 18]], [[62, 82], [98, 64], [74, 18]], ...dashed(10, 82, 48, 64, 4), ...dashed(48, 64, 98, 64, 5), ...dashed(48, 64, 74, 18, 5)] },

    /* ---------------- Mathematics · Statistics and probability ---------------- */
    barChart: { group: `${M} · Statistics and probability`, name: 'Bar chart', paths: [...axes1, rect(20, 50, 32, 88), rect(40, 30, 52, 88), rect(60, 62, 72, 88), rect(80, 40, 92, 88)] },
    histogram: { group: `${M} · Statistics and probability`, name: 'Histogram', paths: [...axes1, rect(20, 60, 36, 88), rect(36, 34, 52, 88), rect(52, 20, 68, 88), rect(68, 48, 84, 88)] },
    pieChart: { group: `${M} · Statistics and probability`, name: 'Pie chart', paths: [circle(50, 50, 40), [[50, 50], [50, 10]], [[50, 50], [88, 62]], [[50, 50], [18, 74]]] },
    cumulative: { group: `${M} · Statistics and probability`, name: 'Cumulative frequency curve', paths: [...axes1, Array.from({ length: 25 }, (_, i) => { const t = i / 24; return [14 + t * 78, 86 - 72 / (1 + Math.exp(-10 * (t - 0.5)))]; })] },
    tree: { group: `${M} · Statistics and probability`, name: 'Tree diagram', paths: [[[6, 50], [40, 24]], [[6, 50], [40, 76]], [[48, 24], [86, 10]], [[48, 24], [86, 36]], [[48, 76], [86, 64]], [[48, 76], [86, 90]]] },
    venn2: { group: `${M} · Statistics and probability`, name: 'Venn diagram (2 sets)', paths: [rect(4, 14, 96, 86), circle(38, 50, 24), circle(62, 50, 24)], labels: [L(24, 24, 'A', 11), L(76, 24, 'B', 11), L(90, 20, 'ξ', 10)] },
    venn3: { group: `${M} · Statistics and probability`, name: 'Venn diagram (3 sets)', paths: [rect(4, 4, 96, 96), circle(38, 38, 22), circle(62, 38, 22), circle(50, 60, 22)], labels: [L(22, 18, 'A', 10), L(78, 18, 'B', 10), L(50, 90, 'C', 10)] },
    dice: { group: `${M} · Statistics and probability`, name: 'Die', paths: [rect(20, 20, 80, 80), ...[[34, 34], [66, 34], [50, 50], [34, 66], [66, 66]].map(([x, y]) => circle(x, y, 5, 12))] },
    coin: { group: `${M} · Statistics and probability`, name: 'Coin', paths: [circle(50, 50, 36), circle(50, 50, 30)], labels: [L(50, 51, 'H', 20)] },
    tally: { group: `${M} · Statistics and probability`, name: 'Tally (five)', paths: [[[26, 26], [26, 74]], [[38, 26], [38, 74]], [[50, 26], [50, 74]], [[62, 26], [62, 74]], [[16, 66], [74, 34]]] },

    /* ---------------- English grammar ---------------- */
    speech: { group: `${E} · Organisers`, name: 'Speech bubble', paths: [[[30, 66], ...arc(50, 40, 40, 110, 430, 32).slice(1), [24, 88], [30, 66]]] },
    thought: { group: `${E} · Organisers`, name: 'Thought bubble', paths: [ellipse(54, 38, 40, 26), circle(22, 74, 6, 12), circle(12, 88, 4, 10)] },
    labelBox: { group: `${E} · Organisers`, name: 'Label box', paths: [rect(6, 30, 94, 70)] },
    mindMap: { group: `${E} · Organisers`, name: 'Mind map (word web)', paths: [ellipse(50, 50, 18, 11), ellipse(14, 14, 12, 8), ellipse(86, 14, 12, 8), ellipse(14, 86, 12, 8), ellipse(86, 86, 12, 8), [[36, 42], [24, 20]], [[64, 42], [76, 20]], [[36, 58], [24, 80]], [[64, 58], [76, 80]]] },
    sequence: { group: `${E} · Organisers`, name: 'Sequence (first, next, last)', paths: [rect(2, 38, 26, 62), ...arrow(27, 50, 37, 50, 5), rect(38, 38, 62, 62), ...arrow(63, 50, 73, 50, 5), rect(74, 38, 98, 62)], labels: [L(14, 30, 'First', 8), L(50, 30, 'Next', 8), L(86, 30, 'Last', 8)] },
    table3: { group: `${E} · Organisers`, name: 'Table (3 × 3)', paths: [rect(4, 16, 96, 84), [[4, 38], [96, 38]], [[4, 61], [96, 61]], [[35, 16], [35, 84]], [[66, 16], [66, 84]]] },
    tenseLine: { group: `${E} · Tenses and sentences`, name: 'Tense timeline', paths: [[[4, 56], [96, 56]], head(96, 56, 0), [[16, 50], [16, 62]], [[50, 46], [50, 66]], [[84, 50], [84, 62]]], labels: [L(16, 76, 'past', 10), L(50, 78, 'now', 10), L(84, 76, 'future', 10)] },
    svo: { group: `${E} · Tenses and sentences`, name: 'Subject – verb – object', paths: [rect(2, 40, 30, 64), rect(36, 40, 64, 64), rect(70, 40, 98, 64)], labels: [L(16, 30, 'Subject', 8), L(50, 30, 'Verb', 8), L(84, 30, 'Object', 8)] },
    sentenceDiagram: { group: `${E} · Tenses and sentences`, name: 'Sentence diagram', paths: [[[4, 50], [96, 50]], [[46, 36], [46, 62]], [[72, 36], [72, 50]], [[16, 50], [30, 72]], [[58, 50], [72, 72]]], labels: [L(24, 42, 'subject', 8), L(58, 42, 'verb', 8), L(84, 42, 'object', 8)] },
    caret: { group: `${E} · Tenses and sentences`, name: 'Insert mark (^)', paths: [[[30, 70], [50, 30], [70, 70]]] },
    quotes: { group: `${E} · Tenses and sentences`, name: 'Quotation marks', paths: [], labels: [L(24, 44, '“', 48), L(76, 44, '”', 48)] },
    paragraph: { group: `${E} · Tenses and sentences`, name: 'New paragraph (¶)', paths: [], labels: [L(50, 50, '¶', 56)] },
    noun: { group: `${E} · Parts of speech`, name: 'Noun', ...tag('Noun') },
    pronoun: { group: `${E} · Parts of speech`, name: 'Pronoun', ...tag('Pronoun') },
    verb: { group: `${E} · Parts of speech`, name: 'Verb', ...tag('Verb') },
    adjective: { group: `${E} · Parts of speech`, name: 'Adjective', ...tag('Adjective') },
    adverb: { group: `${E} · Parts of speech`, name: 'Adverb', ...tag('Adverb') },
    preposition: { group: `${E} · Parts of speech`, name: 'Preposition', ...tag('Preposition') },
    conjunction: { group: `${E} · Parts of speech`, name: 'Conjunction', ...tag('Conjunction') },
    article: { group: `${E} · Parts of speech`, name: 'Article / determiner', ...tag('Determiner') },
    interjection: { group: `${E} · Parts of speech`, name: 'Interjection', ...tag('Interjection') },
};

export const GROUPS = [...new Set(Object.values(STAMPS).map((s) => s.group))];
export const SUBJECTS = [...new Set(GROUPS.map((g) => g.split(' · ')[0]))];

/** Builds the shape event for a stamp centred on (x, y) in page units, `box` units wide. */
export function buildShape(key, x, y, box, color, width, id, t) {
    const def = STAMPS[key];
    if (!def) return null;
    const k = box / 100;
    const px = (v) => Math.round((x + (v - 50) * k) * 10) / 10;
    const py = (v) => Math.round((y + (v - 50) * k) * 10) / 10;
    return {
        type: 'shape',
        id,
        t,
        key,
        color,
        width,
        paths: def.paths.filter((path) => path.length > 1).map((path) => path.map(([a, b]) => [px(a), py(b)])),
        labels: (def.labels || []).map((l) => ({ x: px(l.x), y: py(l.y), text: l.text, size: Math.max(8, Math.round(l.size * k)) })),
    };
}
