<?php
/**
 * @var list<array<string, mixed>> $lessons
 * @var array<string, string> $subjects
 * @var bool $isTutor
 * @var array<string, string> $states
 */
?>
<section class="section">
    <div class="container">
        <h1>Lessons</h1>
        <p class="lead">Recorded lessons. Pause, rewind or speed up whenever you need.<?php if (!$isTutor): ?> A lesson opens once your school has taught its topic.<?php endif; ?></p>
        <?php if ($isTutor): ?>
            <p class="note">Students see only published lessons. <a href="/studio/lessons">Manage lessons</a></p>
        <?php endif; ?>

        <?php if ($lessons === []): ?>
            <div class="card"><p>No lessons have been published yet.</p></div>
        <?php else: ?>
            <div class="lesson-grid">
                <?php foreach ($lessons as $lesson): ?>
                    <?php $locked = isset($lesson['access']) && !$lesson['access']['open']; ?>
                    <a class="lesson-card<?= $locked ? ' lesson-card--locked' : '' ?>" href="/lessons/<?= e($lesson['id']) ?>">
                        <span class="lesson-card__meta">
                            <?php if ($lesson['subject']): ?>
                                <span class="badge badge--<?= e($lesson['subject']) ?>"><?= e($subjects[$lesson['subject']] ?? $lesson['subject']) ?></span>
                            <?php endif; ?>
                            <?php if ($lesson['form']): ?><span class="lesson-card__form">Form <?= e($lesson['form']) ?></span><?php endif; ?>
                        </span>
                        <span class="lesson-card__title"><?= e($lesson['title']) ?></span>
                        <?php if ($locked): ?><span class="chip">🔒 <?= e($states[$lesson['access']['state']] ?? 'Locked') ?></span><?php endif; ?>
                        <span class="lesson-card__info"><?= e(gmdate($lesson['duration_ms'] >= 3600000 ? 'G:i:s' : 'i:s', intdiv((int) $lesson['duration_ms'], 1000))) ?> · <?= e(number_format((int) $lesson['size_bytes'] / 1048576, 1)) ?> MB</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
