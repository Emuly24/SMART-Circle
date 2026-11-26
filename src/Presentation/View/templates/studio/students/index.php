<?php
/**
 * @var list<array<string, mixed>> $students
 * @var int $total
 * @var int $flagged
 * @var string $filter
 */
$modes = ['day' => 'Day scholar', 'boarder' => 'Boarder', 'self_boarder' => 'Self-boarder', 'evening' => 'Evening class'];
$ago = static function (?string $at): string {
    if ($at === null) {
        return 'never';
    }
    $d = (int) floor((time() - (int) strtotime($at)) / 86400);

    return $d <= 0 ? 'today' : ($d === 1 ? 'yesterday' : $d . ' days ago');
};
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Students</h1>
        <p class="filters">
            <a href="/studio/students" <?= $filter !== 'flagged' ? 'aria-current="page"' : '' ?>>All (<?= e($total) ?>)</a> ·
            <a href="/studio/students?show=flagged" <?= $filter === 'flagged' ? 'aria-current="page"' : '' ?>>Worth a check-in (<?= e($flagged) ?>)</a>
        </p>
        <?php if ($students === []): ?>
            <div class="card"><p class="muted"><?= $filter === 'flagged' ? 'Nobody needs a check-in right now.' : 'No active students yet.' ?></p></div>
        <?php else: ?>
            <ul class="student-list">
                <?php foreach ($students as $s): ?>
                    <li class="card student-row">
                        <div class="student-row__main">
                            <h2 class="h3"><a href="/studio/students/<?= e($s['id']) ?>"><?= e($s['full_name']) ?></a></h2>
                            <p class="muted">Form <?= e($s['form']) ?><?= $s['school_name'] !== null ? ' · ' . e($s['school_name']) : '' ?><?= isset($modes[$s['study_mode'] ?? '']) ? ' · ' . e($modes[$s['study_mode']]) : '' ?></p>
                        </div>
                        <dl class="stat-row">
                            <div><dt>Last seen</dt><dd><?= e($ago($s['last_login_at'])) ?></dd></div>
                            <div><dt>Lessons (14 days)</dt><dd><?= e($s['lessons_14d']) ?></dd></div>
                            <div><dt>Work (30 days)</dt><dd><?= e($s['work_30d']) ?></dd></div>
                            <div><dt>Term average</dt><dd><?= $s['overall'] !== null ? e($s['overall']) . '%' . ($s['overall_grade'] !== null ? ' · ' . e($s['overall_grade']['grade']) : '') : '—' ?></dd></div>
                        </dl>
                        <?php if ($s['flags'] !== []): ?>
                            <ul class="flags"><?php foreach ($s['flags'] as $f): ?><li class="flag flag--<?= e($f['key']) ?>"><?= e($f['label']) ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
