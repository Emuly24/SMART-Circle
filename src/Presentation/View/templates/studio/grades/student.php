<?php
/**
 * @var array<string, mixed> $student
 * @var list<array<string, mixed>> $terms
 * @var int|null $termId
 * @var array<string, mixed>|null $g
 * @var list<array<string, mixed>> $reports
 * @var array<string, string> $names
 * @var array<string, string> $categories
 */
$pct = static fn ($p): string => rtrim(rtrim(number_format(100 * (float) $p, 1), '0'), '.') . '%';
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/studio/grades?form=<?= e($student['form']) ?>&amp;term=<?= e($termId) ?>">Grades · Form <?= e($student['form']) ?></a></p>
        <h1><?= e($student['full_name']) ?></h1>
        <?= $view->partial('term_picker', ['terms' => $terms, 'termId' => $termId]) ?>
        <?php if ($g !== null): ?>
            <?= $view->partial('grade_table', ['g' => $g, 'names' => $names, 'categories' => $categories]) ?>
            <div class="card section-card">
                <h2 class="h3">Every marked piece of work this term</h2>
                <?php $any = false; foreach ($g['subjects'] as $key => $s): foreach ($s['work'] as $w): $any = true; ?>
                    <p class="work-row"><span class="badge badge--<?= e($key) ?>"><?= e($names[$key] ?? $key) ?></span>
                        <a href="/studio/assessments/<?= e($w['assessment_id']) ?>/submissions"><?= e($w['title']) ?></a>
                        <span class="muted"><?= e(date('j M', (int) strtotime((string) $w['submitted_at']))) ?></span>
                        <strong><?= e($pct((float) $w['score'] / (float) $w['max_score'])) ?></strong></p>
                <?php endforeach; endforeach; ?>
                <?php if (!$any): ?><p class="muted">No marked work this term yet.</p><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($reports !== []): ?>
            <div class="card section-card"><h2 class="h3">Term reports</h2><ul class="letter-list">
                <?php foreach ($reports as $r): ?><li><a href="/letters/<?= e($r['document_code']) ?>"><?= e($r['year_name']) ?> Term <?= e($r['number']) ?></a></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
    </div>
</section>
