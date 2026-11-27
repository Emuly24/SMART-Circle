<?php
/**
 * @var array<string, mixed>|null $plan
 * @var array<string, string> $names
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>MANEB revision</h1>
        <?php if ($plan === null): ?>
            <div class="card">
                <p>Revision mode opens <strong>three weeks before</strong> your MANEB exam (JCE in Form 2, MSCE in Form 4). Then every topic of the exam syllabus opens to you, even ones your school has not reached yet, until the exam ends.</p>
                <p class="muted">Until then, topics open as your school teaches them. <a href="/topics">See your topics</a>.</p>
            </div>
        <?php else: $w = $plan['window']; ?>
            <div class="card section-card revision-hero">
                <p class="eyebrow"><?= e($w['title']) ?></p>
                <p class="revision-hero__days"><?php if ($w['days'] > 0): ?><strong><?= e($w['days']) ?></strong> day<?= $w['days'] === 1 ? '' : 's' ?> to the first paper<?php else: ?>The exam has started. Good luck!<?php endif; ?></p>
                <p>From <?= e(date('j M', (int) strtotime($w['starts_on']))) ?> to <?= e(date('j M Y', (int) strtotime($w['ends_on']))) ?>. Every Form <?= e(implode(' and Form ', $w['forms'])) ?> topic is open to you until then.</p>
                <?php if ($plan['left'] > 0 && $w['days'] > 0): ?>
                    <p class="note">You have <?= e($plan['left']) ?> lesson<?= $plan['left'] === 1 ? '' : 's' ?> you have not watched yet. About <strong><?= e($plan['per_day']) ?> a day</strong> covers them all before the exam.</p>
                <?php endif; ?>
            </div>
            <div class="card section-card">
                <h2 class="h3">What is open for revision</h2>
                <?php if ($plan['rows'] === []): ?><p class="muted">No topics yet.</p><?php else: ?>
                    <ul class="revision-list">
                        <?php foreach ($plan['rows'] as $r): ?>
                            <li class="revision-row">
                                <span><span class="badge badge--<?= e($r['subject']) ?>"><?= e($names[$r['subject']] ?? $r['subject']) ?></span> Form <?= e($r['form']) ?></span>
                                <span class="muted"><?= e($r['topics']) ?> topics · <?= e($r['watched']) ?>/<?= e($r['lessons']) ?> lessons watched<?= (int) $r['notes'] > 0 ? ' · ' . e($r['notes']) . ' notes' : '' ?></span>
                                <a class="btn btn--small btn--ghost" href="/topics?form=<?= e($r['form']) ?>&amp;subject=<?= e($r['subject']) ?>">Open</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card section-card">
                <h2 class="h3">Revising well</h2>
                <ul>
                    <li>Start with the topics you find hardest, while you are fresh.</li>
                    <li>After each lesson, close it and write the main idea from memory. Then check.</li>
                    <li>Do past-paper questions with a timer, then <a href="/office-hours">book office hours</a> for what you could not do.</li>
                    <li>Sleep well the night before each paper. Rest is part of revision.</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>
