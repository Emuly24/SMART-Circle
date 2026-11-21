<?php
/**
 * @var int $form
 * @var string $subject
 * @var list<string> $subjects
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $tree
 * @var bool $isTutor
 * @var array<string, string> $statuses
 */
$chip = static function (array $t) use ($statuses): string {
    $html = '';
    if ((int) $t['lesson_count'] > 0) {
        $html .= '<span class="chip chip--lesson">' . e((int) $t['lesson_count'] === 1 ? '1 lesson' : $t['lesson_count'] . ' lessons') . '</span>';
    }
    if ($t['request_id'] !== null) {
        $label = $statuses[$t['request_status']] ?? $t['request_status'];
        if ($t['exception_status'] === 'pending') {
            $label = 'Waiting for approval';
        }
        $n = (int) $t['request_count'];
        $html .= '<span class="chip chip--' . e($t['request_status']) . '">' . e($label) . ' · ' . e($n) . ' ' . ($n === 1 ? 'voice' : 'voices') . '</span>';
    }

    return $html;
};
?>
<section class="section section--tight">
    <div class="container">
        <h1>Topics</h1>
        <p class="lead">The Form <?= e($form) ?> syllabus. Stuck on a topic your school has taught? Tap it and tell the tutor where you get lost.</p>

        <?php if ($isTutor): ?>
            <nav class="tabs tabs--small" aria-label="Form">
                <?php for ($f = 1; $f <= 4; $f++): ?>
                    <a href="/topics?form=<?= $f ?>&amp;subject=<?= e($subject) ?>" <?= $f === $form ? 'aria-current="page"' : '' ?>>Form <?= $f ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
        <nav class="tabs" aria-label="Subject">
            <?php foreach ($subjects as $s): ?>
                <a class="tabs__subject tabs__subject--<?= e($s) ?>" href="/topics?subject=<?= e($s) ?><?= $isTutor ? '&amp;form=' . e($form) : '' ?>" <?= $s === $subject ? 'aria-current="page"' : '' ?>><?= e($names[$s]) ?></a>
            <?php endforeach; ?>
        </nav>

        <?php if ($tree === []): ?>
            <div class="card"><p>No topics have been added for this subject yet.</p></div>
        <?php else: ?>
            <ol class="topic-list">
                <?php foreach ($tree as $t): ?>
                    <li class="topic-item topic-item--<?= e($subject) ?>">
                        <a class="topic-item__main" href="/topics/<?= e($t['id']) ?>">
                            <?php if ($t['unit']): ?><span class="topic-item__unit"><?= e($t['unit']) ?></span><?php endif; ?>
                            <span class="topic-item__title"><?= e($t['title']) ?></span>
                            <span class="topic-item__chips"><?= $chip($t) ?></span>
                        </a>
                        <?php if ($t['children'] !== []): ?>
                            <ul class="subtopics">
                                <?php foreach ($t['children'] as $c): ?>
                                    <li><a href="/topics/<?= e($c['id']) ?>"><span><?= e($c['title']) ?></span><?= $chip($c) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</section>
