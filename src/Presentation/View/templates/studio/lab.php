<?php
/**
 * Board lab: record strokes + voice, save on this device, replay.
 * @var callable $asset
 */
?>
<header class="studio-bar">
    <a class="studio-bar__logo" href="/dashboard" aria-label="Back to dashboard">
        <img src="<?= e($asset('brand/logo-mark.svg')) ?>" alt="" width="34" height="34">
    </a>
    <div class="seg" role="tablist" aria-label="Mode">
        <button type="button" class="seg__btn is-active" data-mode="record" role="tab" aria-selected="true">Board</button>
        <button type="button" class="seg__btn" data-mode="replay" role="tab" aria-selected="false">Replay</button>
    </div>
    <div class="studio-bar__status" id="status" aria-live="polite">Ready</div>
</header>

<!-- ============ RECORD MODE ============ -->
<section class="mode" id="mode-record">
    <div class="toolbar" role="toolbar" aria-label="Drawing tools">
        <div class="tool-group" aria-label="Tool">
            <button type="button" class="tool is-active" data-tool="pen" title="Pen">✏️<span>Pen</span></button>
            <button type="button" class="tool" data-tool="highlighter" title="Highlighter">🖍️<span>Highlight</span></button>
            <button type="button" class="tool" data-tool="eraser" title="Eraser">🧽<span>Erase</span></button>
            <button type="button" class="tool" data-tool="laser" title="Laser pointer">🔴<span>Laser</span></button>
            <button type="button" class="tool" data-tool="text" title="Type text or an equation">🔤<span>Type</span></button>
            <button type="button" class="tool" id="shapes-open" title="Ready-made illustrations for every subject">🔌<span>Shapes</span></button>
        </div>
        <div class="tool-group" aria-label="Drag to draw">
            <button type="button" class="tool" data-tool="line" title="Straight line (wires): drag. Snaps level, upright or 45°">📏<span>Line</span></button>
            <button type="button" class="tool" data-tool="arrow" title="Arrow: drag">➜<span>Arrow</span></button>
            <button type="button" class="tool" data-tool="rect" title="Box: drag (nearly square becomes a square)">▭<span>Box</span></button>
            <button type="button" class="tool" data-tool="ellipse" title="Circle or oval: drag">◯<span>Circle</span></button>
        </div>
        <div class="tool-group" aria-label="Colour">
            <button type="button" class="swatch is-active" data-color="ink" aria-label="Black ink"></button>
            <button type="button" class="swatch" data-color="blue" aria-label="Blue"></button>
            <button type="button" class="swatch" data-color="green" aria-label="Green"></button>
            <button type="button" class="swatch" data-color="red" aria-label="Red"></button>
            <button type="button" class="swatch" data-color="amber" aria-label="Amber"></button>
            <button type="button" class="swatch" data-color="purple" aria-label="Purple"></button>
        </div>
        <div class="tool-group" aria-label="Pen size">
            <button type="button" class="size" data-size="4" aria-label="Thin">•</button>
            <button type="button" class="size is-active" data-size="7" aria-label="Medium">●</button>
            <button type="button" class="size" data-size="12" aria-label="Thick">⬤</button>
        </div>
        <div class="tool-group">
            <button type="button" class="tool" id="undo" title="Undo">↶<span>Undo</span></button>
            <button type="button" class="tool" id="redo" title="Redo">↷<span>Redo</span></button>
            <button type="button" class="tool" id="clear" title="Clear page">🗑️<span>Clear</span></button>
        </div>
        <div class="tool-group">
            <button type="button" class="tool" id="page-prev" title="Previous page">◀</button>
            <span class="page-label" id="page-label">Page 1</span>
            <button type="button" class="tool" id="page-next" title="Next / new page">▶</button>
        </div>
        <label class="toggle" title="Typed lines are centred on the page (best for equations and calculations)">
            <input type="checkbox" id="text-centred" checked> <span>Centre typed lines</span>
        </label>
        <div class="tool-group">
            <button type="button" class="tool" id="notes-open" title="Put your written notes on the board">📄<span>Notes</span></button>
            <button type="button" class="tool" id="snapshot" title="Save this page as a picture (for notes)">📷<span>Picture</span></button>
            <button type="button" class="tool" id="fullscreen" title="Make the board fill the screen">⛶<span>Full screen</span></button>
        </div>
        <label class="toggle" title="When off, only the Apple Pencil draws (palm rejection)">
            <input type="checkbox" id="fingers-draw" checked>
            <span>Fingers draw</span>
        </label>
    </div>

    <div class="board-wrap">
        <canvas id="board" class="board" aria-label="Drawing board"></canvas>
        <form class="text-entry" id="text-entry" hidden>
            <textarea id="text-input" rows="5" maxlength="2000" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Text or equations (one per line)"
                placeholder="Type one or more lines, e.g.&#10;v = u + at&#10;x^2 + 3x = 0&#10;2H_2 + O_2 -> 2H_2O"></textarea>
            <div class="text-entry__row">
                <label class="text-entry__size">Size
                    <select id="text-size">
                        <option value="0">Pen size</option>
                        <option value="30">Small</option>
                        <option value="42">Medium</option>
                        <option value="56">Large</option>
                        <option value="72">Huge</option>
                    </select></label>
                <button type="submit" class="btn btn--small">Add to the board</button>
                <button type="button" class="btn btn--small btn--ghost" id="text-cancel">Cancel</button>
            </div>
            <p class="text-entry__help">Each line goes under the last. x^2 superscript · H_2O subscript · -&gt; arrow · * times · sqrt √ · &lt;= ≤ · Ctrl/⌘ + Enter adds</p>
        </form>

        <div class="board-panel" id="shapes-panel" hidden>
            <div class="board-panel__head"><strong>Shapes</strong><span>Choose one, then tap the board where it should go.</span>
                <button type="button" class="btn btn--small btn--ghost" data-close="shapes-panel">Close</button></div>
            <div class="board-panel__row">
                <label>Subject <select id="shapes-subject"><option value="">All subjects</option></select></label>
                <input type="search" id="shapes-search" placeholder="Find a shape, e.g. ammeter" aria-label="Find a shape" autocomplete="off">
                <label>Size <select id="stamp-size"><option value="140">Small</option><option value="220" selected>Medium</option><option value="320">Large</option><option value="460">Very large</option></select></label>
                <span class="muted-light">Lines use the colour and pen size you have chosen. For wires, use the Line tool: it snaps straight.</span>
            </div>
            <div id="shapes-list" class="shapes-list"></div>
        </div>

        <div class="board-panel" id="notes-panel" hidden>
            <div class="board-panel__head"><strong>Put notes on the board</strong>
                <button type="button" class="btn btn--small btn--ghost" data-close="notes-panel">Close</button></div>
            <p class="muted-light">The text of your notes is laid out on new board pages, so you can explain and write over it while you record.
                Load them <em>before</em> pressing Record to start the lesson with them. Pictures are listed by caption.</p>
            <div class="board-panel__row">
                <select id="notes-select" aria-label="Notes"><option value="">Loading your notes…</option></select>
                <button type="button" class="btn btn--small" id="notes-put">Put on the board</button>
            </div>
        </div>
    </div>

    <div class="rec-bar">
        <button type="button" class="rec-btn" id="rec" aria-label="Start recording"><span class="rec-dot"></span><span id="rec-label">Record</span></button>
        <button type="button" class="btn btn--small btn--ghost" id="stop" disabled>Stop &amp; save</button>
        <span class="rec-time" id="rec-time">0:00</span>
        <span class="rec-size" id="rec-size"></span>
    </div>
</section>

<!-- ============ REPLAY MODE ============ -->
<section class="mode" id="mode-replay" hidden>
    <div class="replay-layout">
        <aside class="library">
            <h2>Saved on this device</h2>
            <ul class="library__list" id="library"></ul>
            <p class="library__empty" id="library-empty">No lessons yet. Record one on the Board tab.</p>
            <p class="library__upload" id="upload-status" aria-live="polite" hidden></p>
            <a class="btn btn--small btn--ghost" href="/studio/lessons">Lessons on the site</a>
            <label class="btn btn--small btn--ghost file-btn">
                Open a lesson file
                <input type="file" id="import" accept=".scl,application/octet-stream" hidden>
            </label>
        </aside>
        <div class="player">
            <div class="board-wrap">
                <canvas id="replay" class="board" aria-label="Lesson replay"></canvas>
            </div>
            <div class="player-bar">
                <button type="button" class="play-btn" id="play" disabled aria-label="Play">▶</button>
                <span class="rec-time" id="play-time">0:00 / 0:00</span>
                <input type="range" id="seek" min="0" max="1000" value="0" step="1" disabled aria-label="Position">
                <div class="seg seg--small" aria-label="Speed">
                    <button type="button" class="seg__btn is-active" data-rate="1">1×</button>
                    <button type="button" class="seg__btn" data-rate="1.25">1.25×</button>
                    <button type="button" class="seg__btn" data-rate="1.5">1.5×</button>
                </div>
                <a class="btn btn--small btn--ghost" id="export" hidden download>Download file</a>
            </div>
            <p class="player-info" id="player-info"></p>
        </div>
    </div>
</section>

<script type="module" src="<?= e($asset('js/board/lab.js')) ?>"></script>
