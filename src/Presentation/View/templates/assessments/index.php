<?php
/**
 * @var list<array<string, mixed>> $items
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 * @var array<string, string> $states
 */
$fmt = static fn ($n): string => \SmartCircle\Application\Assessments\AssessmentService::fmt((float) $n);
?>
<section class="section section--tight">
    <div class="container">
        <h1>Exams &amp; assignments</h1>
        <p class="lead">Each one opens once your school has taught all its topics.</p>
        <?php if ($items === []): ?>
            <div class="card"><p>Nothing yet. You will get a notification when the tutor sets something for you.</p></div>
        <?php else: ?>
            <div class="lesson-grid">
                <?php foreach ($items as $a): $locked = $a['state'] === 'locked'; ?>
                    <a class="lesson-card assess-card assess-card--<?= e($a['state']) ?><?= $locked ? ' lesson-card--locked' : '' ?>" href="/assessments/<?= e($a['id']) ?><?= $a['state'] === 'results' ? '/result' : '' ?>">
                        <span class="lesson-card__meta">
                            <span class="badge badge--<?= e($a['subject']) ?>"><?= e($names[$a['subject']] ?? '') ?></span>
                            <span class="lesson-card__form"><?= e($kinds[$a['kind']] ?? '') ?></span>
                        </span>
                        <span class="lesson-card__title"><?= $locked ? '🔒 ' : '' ?><?= e($a['title']) ?></span>
                        <span class="chip chip--<?= e(['open' => 'open', 'in_progress' => 'planned', 'results' => 'lesson'][$a['state']] ?? 'closed') ?>"><?= e($states[$a['state']] ?? '') ?></span>
                        <span class="lesson-card__info">
                            <?php if ($a['state'] === 'results'): ?>Score: <?= e($fmt($a['submission']['score'])) ?> / <?= e($fmt($a['submission']['max_score'])) ?>
                            <?php elseif ($a['closes_at'] && in_array($a['state'], ['open', 'in_progress', 'not_open'], true)): ?>Closes <?= e(date('D j M, H:i', (int) strtotime((string) $a['closes_at']))) ?>
                            <?php elseif ($a['duration_min']): ?><?= e($a['duration_min']) ?> minutes<?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
