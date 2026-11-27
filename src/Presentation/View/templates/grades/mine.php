<?php
/**
 * @var list<array<string, mixed>> $terms
 * @var int|null $termId
 * @var array<string, mixed>|null $g
 * @var list<array<string, mixed>> $reports
 * @var array<string, string> $names
 * @var array<string, string> $categories
 * @var array<string, int> $weights
 */
?>
<section class="section section--tight">
    <div class="container">
        <h1>My grades</h1>
        <p class="lead">Every exercise, assignment, test and exam you complete counts towards your term grade in each subject.</p>
        <?= $view->partial('term_picker', ['terms' => $terms, 'termId' => $termId]) ?>
        <?php if ($g === null): ?>
            <div class="card"><p>Grades appear here once the term starts and your work is marked.</p></div>
        <?php else: ?>
            <?= $view->partial('grade_table', ['g' => $g, 'names' => $names, 'categories' => $categories]) ?>
            <p class="note">How your term grade is made: <?= e(implode(', ', array_map(static fn (string $c, int $w): string => $categories[$c] . ' ' . $w . '%', array_keys($weights), $weights))) ?>.
                Kinds of work you have not done yet are left out until you do. <?= $g['scale'] === 'jce' ? 'Forms 1–2 use the JCE letters A–F.' : 'Forms 3–4 use the MSCE points 1–9 (1 is the best).' ?></p>
        <?php endif; ?>
        <?php if ($reports !== []): ?>
            <div class="card section-card">
                <h2 class="h3">Term reports</h2>
                <ul class="letter-list">
                    <?php foreach ($reports as $r): ?>
                        <li><a href="/letters/<?= e($r['document_code']) ?>"><?= e($r['year_name']) ?> Term <?= e($r['number']) ?> report</a> <span class="muted">· issued <?= e(date('j M Y', (int) strtotime((string) $r['issued_at']))) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</section>
