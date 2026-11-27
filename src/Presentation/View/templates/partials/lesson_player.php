<?php
/**
 * The board replay player.
 * @var string $src where the lesson file is served
 * @var int $size its size in bytes
 */
?>
        <div class="watch" id="watch" data-src="<?= e($src) ?>" data-size="<?= e($size) ?>">
            <div class="watch__stage">
                <canvas id="replay" class="watch__canvas" aria-label="Lesson board"></canvas>
                <div class="watch__overlay" id="overlay">
                    <p id="load-status">Loading lesson…</p>
                    <div class="progress"><div class="progress__bar" id="load-bar"></div></div>
                </div>
            </div>
            <div class="watch__bar">
                <button type="button" class="watch__play" id="play" disabled aria-label="Play">▶</button>
                <span class="watch__time" id="play-time">0:00 / 0:00</span>
                <input type="range" id="seek" min="0" max="1000" value="0" disabled aria-label="Position in lesson">
                <div class="watch__speeds" aria-label="Playback speed">
                    <button type="button" class="speed is-active" data-rate="1">1×</button>
                    <button type="button" class="speed" data-rate="1.25">1.25×</button>
                    <button type="button" class="speed" data-rate="1.5">1.5×</button>
                </div>
            </div>
        </div>

