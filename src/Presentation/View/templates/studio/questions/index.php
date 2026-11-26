<?php
/**
 * @var int $form
 * @var string $subject
 * @var list<array<string, mixed>> $questions
 * @var array<string, string> $names
 * @var array<string, string> $types
 */
$q = static fn (int $f, string $s): string => '/studio/questions?form=' . $f . '&subject=' . $s;
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a> · <a href="/studio/assessments">Exams &amp; assignments</a></p>
        <div class="title-row">
            <h1>Question bank</h1>
            <div class="title-row__actions"><a class="btn btn--small" href="/studio/questions/new?form=<?= e($form) ?>&amp;subject=<?= e($subject) ?>">New question</a></div>
        </div>
        <p class="lead">Write each question once, then reuse it in exams, assignments and exercises.</p>
        <nav class="tabs tabs--small" aria-label="Form">
            <?php for ($f = 1; $f <= 4; $f++): ?><a href="<?= e($q($f, $subject)) ?>" <?= $f === $form ? 'aria-current="page"' : '' ?>>Form <?= $f ?></a><?php endfor; ?>
        </nav>
        <nav class="tabs" aria-label="Subject">
            <?php foreach ($names as $s => $n): ?>
                <a class="tabs__subject tabs__subject--<?= e($s) ?>" href="<?= e($q($form, $s)) ?>" <?= $s === $subject ? 'aria-current="page"' : '' ?>><?= e($n) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($questions === []): ?>
            <div class="card"><p>No questions yet for Form <?= e($form) ?> <?= e($names[$subject]) ?>.</p></div>
        <?php else: ?>
            <ol class="question-list">
                <?php foreach ($questions as $item): ?>
                    <li class="question-row">
                        <a href="/studio/questions/<?= e($item['id']) ?>">
                            <span class="question-row__meta">
                                <span class="chip"><?= e($types[$item['type']] ?? $item['type']) ?></span>
                                <span class="chip"><?= e($item['marks']) ?> mark<?= (int) $item['marks'] === 1 ? '' : 's' ?></span>
                                <?php if ($item['is_application']): ?><span class="chip chip--lesson">Real life</span><?php endif; ?>
                                <?php if ($item['topic_title']): ?><span class="muted"><?= e($item['topic_title']) ?></span><?php else: ?><span class="tag">No topic</span><?php endif; ?>
                                <?php if ((int) $item['used'] > 0): ?><span class="muted">· in <?= e($item['used']) ?></span><?php endif; ?>
                            </span>
                            <span class="question-row__prompt"><?= e(mb_strimwidth((string) $item['prompt'], 0, 160, '…')) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
