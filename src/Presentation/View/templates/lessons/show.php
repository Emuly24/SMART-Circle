<?php
/**
 * Watch a lesson: replay player + the real-life sections.
 * @var array<string, mixed> $lesson
 * @var array<string, string> $subjects
 * @var array<string, string> $sections
 * @var bool $isTutor
 * @var array<string, mixed>|null $topic
 * @var callable $asset
 */
$icons = ['why_it_matters' => '💡', 'where_in_malawi' => '🌍', 'careers' => '👩🏾‍⚕️', 'try_at_home' => '🏠'];
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/lessons">Lessons</a><?php if ($isTutor): ?> · <a href="/studio/lessons/<?= e($lesson['id']) ?>">Edit</a><?php endif; ?></p>
        <h1 class="lesson-title"><?= e($lesson['title']) ?></h1>
        <button type="button" class="btn btn--small btn--ghost offline-save js-offline-save" hidden
            data-offline-title="<?= e($lesson['title']) ?>"
            data-offline-extra="/lessons/<?= e($lesson['id']) ?>/file /assets/js/board/lesson-file.js /assets/js/board/player.js /assets/js/board/board-core.js"><span class="js-offline-label">Save for offline</span></button>
        <p class="lesson-meta">
            <?php if ($lesson['subject']): ?><span class="badge badge--<?= e($lesson['subject']) ?>"><?= e($subjects[$lesson['subject']] ?? '') ?></span><?php endif; ?>
            <?php if ($lesson['form']): ?><span>Form <?= e($lesson['form']) ?></span><?php endif; ?>
            <?php if ($lesson['status'] !== 'published'): ?><span class="tag">Draft: only you can see this</span><?php endif; ?>
        </p>
        <?php if ($topic !== null): ?>
            <p class="note lesson-for">Topic: <a href="/topics/<?= e($topic['id']) ?>"><?= e($topic['title']) ?></a>. For students whose school has taught this topic.</p>
        <?php endif; ?>

        <div class="watch" id="watch" data-src="/lessons/<?= e($lesson['id']) ?>/file" data-size="<?= e($lesson['size_bytes']) ?>">
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

        <?php if (!empty($lesson['summary'])): ?>
            <p class="lead lesson-summary"><?= nl2br(e($lesson['summary'])) ?></p>
        <?php endif; ?>

        <div class="real-life">
            <?php foreach ($sections as $field => $label): ?>
                <?php if (!empty($lesson[$field])): ?>
                    <article class="real-life__card">
                        <h2><span aria-hidden="true"><?= $icons[$field] ?></span> <?= e($label) ?></h2>
                        <p><?= nl2br(e($lesson[$field])) ?></p>
                    </article>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<script type="module" src="<?= e($asset('js/board/watch.js')) ?>"></script>
<script src="<?= e($asset('js/offline.js')) ?>" defer></script>
