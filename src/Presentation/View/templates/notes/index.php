<?php
/**
 * @var list<array<string, mixed>> $items
 * @var array<string, string> $names
 */
?>
<section class="section section--tight">
    <div class="container">
        <h1>Notes &amp; slides</h1>
        <p class="lead">Read at your own pace. Each exercise opens the next part when you submit it.</p>
        <?php if ($items === []): ?>
            <div class="card"><p>No notes yet. You will get a notification when new notes are ready for you.</p></div>
        <?php else: ?>
            <div class="lesson-grid">
                <?php foreach ($items as $n): $locked = !$n['access']['open']; $total = (int) $n['checkpoints']; ?>
                    <a class="lesson-card<?= $locked ? ' lesson-card--locked' : '' ?>" href="/notes/<?= e($n['id']) ?>">
                        <span class="lesson-card__meta"><span class="badge badge--<?= e($n['subject']) ?>"><?= e($names[$n['subject']] ?? '') ?></span>
                            <span class="lesson-card__form"><?= $n['kind'] === 'slides' ? 'Slides' : 'Notes' ?></span></span>
                        <span class="lesson-card__title"><?= $locked ? '🔒 ' : '' ?><?= e($n['title']) ?></span>
                        <span class="lesson-card__info"><?= e($n['topic_title']) ?></span>
                        <?php if ($locked): ?>
                            <span class="chip">Opens when your school has taught this topic</span>
                        <?php elseif ($total > 0): ?>
                            <span class="progress-mini" aria-label="<?= e($n['done']) ?> of <?= e($total) ?> exercises done"><span class="progress-mini__bar progress-mini__bar--<?= e(min(100, (int) round(10 * $n['done'] / $total) * 10)) ?>"></span></span>
                            <span class="lesson-card__info"><?= e($n['done']) ?> of <?= e($total) ?> exercises done</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
