/**
 * Ready-made science and maths illustrations for the board (circuit symbols, lab apparatus, ...).
 * Each is drawn in a 100 x 100 box as lines ("paths") plus optional labels, then placed on the page at the
 * size the teacher chooses. They are saved as one 'shape' event, so they replay, undo and erase like a stroke.
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
const head = (x, y, dir) => {               // small arrow head at (x, y) pointing in direction dir (degrees)
    const a = dir * Math.PI / 180;
    const p = (da) => [x - 10 * Math.cos(a + da), y - 10 * Math.sin(a + da)];
    return [p(0.5), [x, y], p(-0.5)];
};
const meter = (letter) => ({ paths: [[[0, 50], [32, 50]], circle(50, 50, 18), [[68, 50], [100, 50]]], labels: [{ x: 50, y: 51, text: letter, size: 20 }] });

export const STAMPS = {
    /* Electricity */
    cell: { group: 'Electricity', name: 'Cell', paths: [[[0, 50], [44, 50]], [[44, 30], [44, 70]], [[56, 40], [56, 60]], [[56, 50], [100, 50]]] },
    battery: { group: 'Electricity', name: 'Battery', paths: [[[0, 50], [28, 50]], [[28, 30], [28, 70]], [[38, 40], [38, 60]], [[38, 50], [62, 50]], [[62, 30], [62, 70]], [[72, 40], [72, 60]], [[72, 50], [100, 50]]] },
    resistor: { group: 'Electricity', name: 'Resistor', paths: [[[0, 50], [25, 50]], rect(25, 40, 75, 60), [[75, 50], [100, 50]]] },
    variable: { group: 'Electricity', name: 'Variable resistor', paths: [[[0, 50], [25, 50]], rect(25, 40, 75, 60), [[75, 50], [100, 50]], [[22, 76], [78, 24]], head(78, 24, -43)] },
    lamp: { group: 'Electricity', name: 'Bulb (lamp)', paths: [[[0, 50], [32, 50]], circle(50, 50, 18), [[37, 37], [63, 63]], [[63, 37], [37, 63]], [[68, 50], [100, 50]]] },
    switchOpen: { group: 'Electricity', name: 'Switch (open)', paths: [[[0, 50], [28, 50]], circle(31, 50, 3, 12), [[33, 48], [68, 30]], circle(71, 50, 3, 12), [[74, 50], [100, 50]]] },
    switchClosed: { group: 'Electricity', name: 'Switch (closed)', paths: [[[0, 50], [28, 50]], circle(31, 50, 3, 12), [[34, 50], [68, 50]], circle(71, 50, 3, 12), [[74, 50], [100, 50]]] },
    ammeter: { group: 'Electricity', name: 'Ammeter', ...meter('A') },
    voltmeter: { group: 'Electricity', name: 'Voltmeter', ...meter('V') },
    galvanometer: { group: 'Electricity', name: 'Galvanometer', ...meter('G') },
    motor: { group: 'Electricity', name: 'Motor', ...meter('M') },
    fuse: { group: 'Electricity', name: 'Fuse', paths: [rect(30, 43, 70, 57), [[0, 50], [100, 50]]] },
    diode: { group: 'Electricity', name: 'Diode', paths: [[[0, 50], [38, 50]], [[38, 34], [38, 66], [62, 50], [38, 34]], [[62, 34], [62, 66]], [[62, 50], [100, 50]]] },
    led: { group: 'Electricity', name: 'LED', paths: [[[0, 50], [38, 50]], [[38, 34], [38, 66], [62, 50], [38, 34]], [[62, 34], [62, 66]], [[62, 50], [100, 50]], [[56, 28], [68, 16]], head(68, 16, -45), [[66, 32], [78, 20]], head(78, 20, -45)] },
    capacitor: { group: 'Electricity', name: 'Capacitor', paths: [[[0, 50], [44, 50]], [[44, 30], [44, 70]], [[56, 30], [56, 70]], [[56, 50], [100, 50]]] },
    earth: { group: 'Electricity', name: 'Earth', paths: [[[50, 20], [50, 50]], [[30, 50], [70, 50]], [[37, 58], [63, 58]], [[44, 66], [56, 66]]] },
    junction: { group: 'Electricity', name: 'Junction', paths: [[[0, 50], [100, 50]], [[50, 50], [50, 100]], circle(50, 50, 4, 12), circle(50, 50, 2, 8)] },
    wire: { group: 'Electricity', name: 'Wire', paths: [[[0, 50], [100, 50]]] },

    /* Chemistry: lab apparatus */
    beaker: { group: 'Lab apparatus', name: 'Beaker', paths: [[[20, 12], [25, 15], [25, 85], [75, 85], [75, 15], [80, 12]], [[25, 45], [33, 45]], [[25, 65], [33, 65]]] },
    testTube: { group: 'Lab apparatus', name: 'Test tube', paths: [[[40, 8], [40, 74], ...arc(50, 74, 10, 180, 0).slice(1), [60, 8]]] },
    conical: { group: 'Lab apparatus', name: 'Conical flask', paths: [[[42, 8], [42, 36], [16, 86], [84, 86], [58, 36], [58, 8]]] },
    roundFlask: { group: 'Lab apparatus', name: 'Round-bottom flask', paths: [[[44, 8], [44, 41], ...arc(50, 64, 24, 256, -76, 32).slice(1), [56, 8]]] },
    cylinder: { group: 'Lab apparatus', name: 'Measuring cylinder', paths: [[[38, 8], [38, 86], [62, 86], [62, 8]], [[28, 90], [72, 90]], [[38, 30], [46, 30]], [[38, 48], [46, 48]], [[38, 66], [46, 66]]] },
    funnel: { group: 'Lab apparatus', name: 'Funnel', paths: [[[18, 14], [82, 14]], [[18, 14], [45, 52], [45, 90]], [[82, 14], [55, 52], [55, 90]]] },
    burner: { group: 'Lab apparatus', name: 'Bunsen burner', paths: [[[44, 30], [44, 80]], [[56, 30], [56, 80]], rect(28, 80, 72, 90), [[50, 4], [58, 16], [56, 26], [44, 26], [42, 16], [50, 4]]] },
    thermometer: { group: 'Lab apparatus', name: 'Thermometer', paths: [[[46, 70], [46, 10], ...arc(50, 10, 4, 180, 360, 8).slice(1), [54, 70]], circle(50, 78, 9), [[54, 25], [58, 25]], [[54, 40], [58, 40]], [[54, 55], [58, 55]]] },
    tripod: { group: 'Lab apparatus', name: 'Tripod and gauze', paths: [[[16, 30], [84, 30]], [[30, 30], [22, 90]], [[50, 30], [50, 90]], [[70, 30], [78, 90]]] },
    dropper: { group: 'Lab apparatus', name: 'Dropper', paths: [ellipse(50, 18, 9, 12), [[45, 30], [45, 70], [50, 84], [55, 70], [55, 30]]] },

    /* Physics */
    magnet: { group: 'Physics', name: 'Bar magnet', paths: [rect(10, 38, 90, 62), [[50, 38], [50, 62]]], labels: [{ x: 30, y: 50, text: 'N', size: 18 }, { x: 70, y: 50, text: 'S', size: 18 }] },
    convexLens: { group: 'Physics', name: 'Convex lens', paths: [[...arc(14, 50, 50, -53, 53)], [...arc(86, 50, 50, 127, 233)]] },
    concaveLens: { group: 'Physics', name: 'Concave lens', paths: [[[36, 10], [64, 10]], [[36, 90], [64, 90]], [...arc(-6, 50, 51, -52, 52)], [...arc(106, 50, 51, 128, 232)]] },
    mirror: { group: 'Physics', name: 'Plane mirror', paths: [[[50, 10], [50, 90]], [[50, 18], [60, 10]], [[50, 34], [60, 26]], [[50, 50], [60, 42]], [[50, 66], [60, 58]], [[50, 82], [60, 74]]] },
    arrow: { group: 'Physics', name: 'Force arrow', paths: [[[10, 50], [90, 50]], head(90, 50, 0)] },
    ray: { group: 'Physics', name: 'Light ray', paths: [[[5, 50], [95, 50]], head(56, 50, 0)] },
    spring: { group: 'Physics', name: 'Spring', paths: [[[5, 50], [15, 50], [20, 35], [30, 65], [40, 35], [50, 65], [60, 35], [70, 65], [80, 35], [85, 50], [95, 50]]] },
    mass: { group: 'Physics', name: 'Mass (weight)', paths: [[[50, 5], [50, 30]], rect(30, 30, 70, 80)], labels: [{ x: 50, y: 56, text: 'kg', size: 16 }] },
    pulley: { group: 'Physics', name: 'Pulley', paths: [circle(50, 35, 20), circle(50, 35, 3, 8), [[30, 35], [30, 95]], [[70, 35], [70, 80]], [[50, 15], [50, 2]]] },

    /* Mathematics */
    axes: { group: 'Mathematics', name: 'Axes', paths: [[[10, 88], [94, 88]], head(94, 88, 0), [[12, 90], [12, 6]], head(12, 6, -90)], labels: [{ x: 94, y: 97, text: 'x', size: 14 }, { x: 4, y: 8, text: 'y', size: 14 }] },
    rightTriangle: { group: 'Mathematics', name: 'Right-angled triangle', paths: [[[20, 82], [82, 82], [20, 18], [20, 82]], [[20, 72], [30, 72], [30, 82]]] },
    triangle: { group: 'Mathematics', name: 'Triangle', paths: [[[50, 12], [88, 84], [12, 84], [50, 12]]] },
    circleRadius: { group: 'Mathematics', name: 'Circle with radius', paths: [circle(50, 50, 38), [[50, 50], [88, 50]], circle(50, 50, 2, 8)], labels: [{ x: 69, y: 43, text: 'r', size: 14 }] },
    numberLine: { group: 'Mathematics', name: 'Number line', paths: [[[4, 50], [96, 50]], head(96, 50, 0), ...[10, 26, 42, 58, 74, 90].map((x) => [[x, 44], [x, 56]])] },
    angle: { group: 'Mathematics', name: 'Angle', paths: [[[90, 80], [15, 80], [75, 25]], [...arc(15, 80, 22, -42, 0, 10)]], labels: [{ x: 46, y: 72, text: 'θ', size: 14 }] },

    /* Biology */
    plantCell: { group: 'Biology', name: 'Plant cell', paths: [rect(12, 18, 88, 82), rect(16, 22, 84, 78), circle(32, 40, 9), ellipse(62, 54, 14, 17), ellipse(28, 66, 5, 3, 12), ellipse(76, 30, 5, 3, 12)] },
    animalCell: { group: 'Biology', name: 'Animal cell', paths: [ellipse(50, 50, 40, 30), circle(44, 46, 10), circle(44, 46, 3, 8), ellipse(70, 60, 5, 3, 12), ellipse(30, 64, 4, 2.5, 12)] },
    leaf: { group: 'Biology', name: 'Leaf', paths: [[[50, 6], [70, 25], [74, 50], [62, 76], [50, 88], [38, 76], [26, 50], [30, 25], [50, 6]], [[50, 6], [50, 98]], [[50, 35], [64, 28]], [[50, 50], [68, 44]], [[50, 65], [62, 60]], [[50, 35], [36, 28]], [[50, 50], [32, 44]], [[50, 65], [38, 60]]] },
    heart: { group: 'Biology', name: 'Heart outline', paths: [[[50, 88], [18, 56], ...arc(32, 34, 17, 150, 330, 12), ...arc(68, 34, 17, 210, 390, 12), [82, 56], [50, 88]]] },
};

export const GROUPS = [...new Set(Object.values(STAMPS).map((s) => s.group))];

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
        paths: def.paths.map((path) => path.map(([a, b]) => [px(a), py(b)])),
        labels: (def.labels || []).map((l) => ({ x: px(l.x), y: py(l.y), text: l.text, size: Math.round(l.size * k) })),
    };
}
