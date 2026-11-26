<?php
/**
 * @var int $form
 * @var list<int> $forms forms this viewer may browse
 * @var array<string, mixed>|null $revision
 * @var string $subject
 * @var list<string> $subjects
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $tree
 * @var bool $isTutor
 * @var array<string, string> $statuses
 */
$chip = static function (array $t) use ($statuses): string {
    $html = '';
    if (isset($t['access']) && !$t['access']['open']) {
        $html .= '<span class="chip chip--locked">🔒 Not yet</span>';
    }
    if ((int) $t['lesson_count'] > 0) {
        $html .= '<span class="chip chip--lesson">' . e((int) $t['lesson_count'] === 1 ? '1 lesson' : $t['lesson_count'] . ' lessons') . '</span>';
    }
    if ($t['request_id'] !== null) {
        $label = $statuses[$t['request_status']] ?? $t['request_status'];
        $n = (int) $t['request_count'];
        $html .= '<span class="chip chip--' . e($t['request_status']) . '">' . e($label) . ' · ' . e($n) . ' ' . ($n === 1 ? 'voice' : 'voices') . '</span>';
    }

    return $html;
};
?>
<section class="section section--tight">
    <div class="container">
        <h1>Topics</h1>
        <p class="lead">The Form <?= e($form) ?> syllabus. Topics open once your school has taught them. Stuck on one? Tap it and tell the tutor where you get lost.</p>

        <?php if ($revision !== null): ?>
            <div class="alert alert--info revision-note"><strong>MANEB revision is open.</strong> Every Form <?= e(implode(' and Form ', $revision['forms'])) ?> topic is open to you until the exam ends. <a href="/revision">Your revision plan</a></div>
        <?php endif; ?>
        <?php if (count($forms) > 1): ?>
            <nav class="tabs tabs--small" aria-label="Form">
                <?php foreach ($forms as $f): ?>
                    <a href="/topics?form=<?= e($f) ?>&amp;subject=<?= e($subject) ?>" <?= $f === $form ? 'aria-current="page"' : '' ?>>Form <?= e($f) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <nav class="tabs" aria-label="Subject">
            <?php foreach ($subjects as $s): ?>
                <a class="tabs__subject tabs__subject--<?= e($s) ?>" href="/topics?subject=<?= e($s) ?><?= count($forms) > 1 ? '&amp;form=' . e($form) : '' ?>" <?= $s === $subject ? 'aria-current="page"' : '' ?>><?= e($names[$s]) ?></a>
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
