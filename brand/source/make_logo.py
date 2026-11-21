"""Generates the SMART Circle logo SVGs into the given folder.

Usage: python3 make_logo.py brand/   (needs: pip install fonttools; Inter fonts)
"""
import math, pathlib, sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

OUT = pathlib.Path(sys.argv[1])
BLUE, GREEN, AMBER, INK, GREY, WHITE, MIST = "#1D4E89", "#1F9D6B", "#F2A900", "#14213D", "#5B6577", "#FFFFFF", "#F4F6FA"
FONTS = {
    "bold": TTFont("/usr/share/fonts/opentype/inter/InterDisplay-Bold.otf"),
    "medium": TTFont("/usr/share/fonts/opentype/inter/InterDisplay-Medium.otf"),
    "regular": TTFont("/usr/share/fonts/opentype/inter/Inter-Regular.otf"),
}

def text_path(text, font, size, x, y, tracking=0.0):
    """Return (svg path d, width) for text with baseline at (x, y)."""
    f = FONTS[font]; gs = f.getGlyphSet(); cmap = f.getBestCmap(); upm = f["head"].unitsPerEm
    s = size / upm; pen = SVGPathPen(gs); cx = 0.0
    for ch in text:
        g = cmap[ord(ch)]
        tp = TransformPen(pen, (s, 0, 0, -s, x + cx, y))
        gs[g].draw(tp)
        cx += gs[g].width * s + tracking * size
    return pen.getCommands(), cx - tracking * size

def arc(cx, cy, r, a0, a1):
    p = lambda a: (cx + r * math.cos(math.radians(a)), cy + r * math.sin(math.radians(a)))
    (x0, y0), (x1, y1) = p(a0), p(a1)
    return f"M{x0:.2f} {y0:.2f} A{r} {r} 0 0 1 {x1:.2f} {y1:.2f}"

def mark(cx, cy, scale=1.0, ring=(BLUE, GREEN, BLUE, GREEN), orbit=INK, nucleus=AMBER, electron=None, simple=False):
    """The symbol, drawn in a 100x100 box centred on (cx, cy) * scale."""
    r, w, gap = 42 * scale, (9 if simple else 8) * scale, 16
    parts = []
    for i, col in enumerate(ring):  # four segments = Forms 1-4, a circle of learners
        a0 = -90 + i * 90 + gap / 2; a1 = a0 + 90 - gap
        parts.append(f'<path d="{arc(cx, cy, r, a0, a1)}" fill="none" stroke="{col}" stroke-width="{w:.2f}" stroke-linecap="round"/>')
    ow = (4.2 if simple else 3.4) * scale
    for rot in (-32, 32):  # two orbits = an atom: science at the centre
        parts.append(f'<ellipse cx="{cx}" cy="{cy}" rx="{25*scale:.2f}" ry="{10*scale:.2f}" fill="none" stroke="{orbit}" stroke-width="{ow:.2f}" transform="rotate({rot} {cx} {cy})"/>')
    parts.append(f'<circle cx="{cx}" cy="{cy}" r="{(8 if simple else 7)*scale:.2f}" fill="{nucleus}"/>')  # the spark of understanding
    if not simple:
        e = electron or GREEN
        ex = cx + 25 * scale * math.cos(math.radians(-32 - 90 + 90)) ; 
        # electron sits on the right end of the first orbit
        ang = math.radians(-32)
        parts.append(f'<circle cx="{cx + 25*scale*math.cos(ang):.2f}" cy="{cy + 25*scale*math.sin(ang):.2f}" r="{3.2*scale:.2f}" fill="{e}"/>')
    return "\n  ".join(parts)

def svg(w, h, body, bg=None, title="SMART Circle"):
    rect = f'<rect width="{w}" height="{h}" fill="{bg}"/>\n  ' if bg else ""
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" role="img" aria-label="{title}">\n  <title>{title}</title>\n  {rect}{body}\n</svg>\n'

def wordmark(x, base, size, smart=INK, circle=BLUE, tag=GREY, tagline=True):
    d1, w1 = text_path("SMART", "bold", size, x, base, tracking=0.01)
    d2, w2 = text_path("Circle", "medium", size, x + w1 + size * 0.22, base)
    out = [f'<path d="{d1}" fill="{smart}"/>', f'<path d="{d2}" fill="{circle}"/>']
    width = w1 + size * 0.22 + w2
    if tagline:
        d3, w3 = text_path("UNDERSTAND IT. APPLY IT.", "medium", size * 0.30, x + 2, base + size * 0.62, tracking=0.12)
        out.append(f'<path d="{d3}" fill="{tag}"/>'); width = max(width, w3)
    return "\n  ".join(out), width

files = {}
# 1. Symbol only (square)
files["logo-mark.svg"] = svg(120, 120, mark(60, 60, 1.18))
# 2. Symbol reversed, for dark backgrounds / app icon
files["logo-mark-reversed.svg"] = svg(120, 120, mark(60, 60, 1.18, ring=(WHITE, "#7FD4B0", WHITE, "#7FD4B0"), orbit=WHITE, electron="#7FD4B0"), bg=BLUE)
# 3. Favicon / tiny sizes: ring + spark only (the atom blurs below 32 px)
_seg = "".join(f'<path d="{arc(50, 50, 38, -90 + i*90 + 10, -90 + i*90 + 80)}" fill="none" stroke="{c}" stroke-width="14" stroke-linecap="round"/>' for i, c in enumerate((BLUE, GREEN, BLUE, GREEN)))
files["favicon.svg"] = svg(100, 100, _seg + f'<circle cx="50" cy="50" r="15" fill="{AMBER}"/>')
# 4. Horizontal lockup
wm, ww = wordmark(140, 74, 52)
files["logo-horizontal.svg"] = svg(int(140 + ww + 24), 130, mark(64, 65, 1.25) + "\n  " + wm)
# 5. Horizontal reversed (white text) for dark headers
wm_r, _ = wordmark(140, 74, 52, smart=WHITE, circle="#BFE6D4", tag="#C9D3E3")
files["logo-horizontal-reversed.svg"] = svg(int(140 + ww + 24), 130, mark(64, 65, 1.25, ring=(WHITE, "#7FD4B0", WHITE, "#7FD4B0"), orbit=WHITE, electron="#7FD4B0") + "\n  " + wm_r, bg=BLUE)
# 6. Stacked lockup (posters, certificates)
size = 46
d1, w1 = text_path("SMART", "bold", size, 0, 0, tracking=0.01); d2, w2 = text_path("Circle", "medium", size, 0, 0)
total = w1 + size * 0.22 + w2; W = int(max(total, 230) + 40); x0 = (W - total) / 2
d1, _ = text_path("SMART", "bold", size, x0, 200, tracking=0.01); d2, _ = text_path("Circle", "medium", size, x0 + w1 + size * 0.22, 200)
d3, w3 = text_path("UNDERSTAND IT. APPLY IT.", "medium", 13.5, 0, 0, tracking=0.12)
d3, _ = text_path("UNDERSTAND IT. APPLY IT.", "medium", 13.5, (W - w3) / 2, 228, tracking=0.12)
files["logo-stacked.svg"] = svg(W, 248, mark(W / 2, 80, 1.3) + f'\n  <path d="{d1}" fill="{INK}"/>\n  <path d="{d2}" fill="{BLUE}"/>\n  <path d="{d3}" fill="{GREY}"/>')
for name, content in files.items():
    (OUT / name).write_text(content)
    print(name, len(content), "bytes")
