<?php
/**
 * Shared by the partner dashboard and the tutor's school page.
 * @var array{id: int, school: array<string, mixed>, subjects: list<string>, base: string, isTutor: bool} $scope
 * @var array<string, array{covered: int, total: int}> $progress
 * @var list<array<string, mixed>> $top
 * @var int $bookCount
 * @var array<string, string> $names
 * @var array{last: ?string, days: ?int, stale: bool} $fresh
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
?>
<?php if ($fresh['stale']): ?>
    <div class="alert alert--info update-banner">
        <p><?php if ($fresh['last'] === null): ?>
            <strong><?= $scope['isTutor'] ? 'This school has' : 'You have' ?> not reported any topics yet.</strong> Students can only open topics their school has taught.
        <?php else: ?>
            <strong>It has been <?= e(intdiv((int) $fresh['days'], 7)) ?> weeks since the last update.</strong> Has the class moved on?
        <?php endif; ?></p>
        <div class="title-row__actions">
            <a class="btn btn--small" href="<?= e($scope['base']) ?>/coverage">Update topics</a>
            <?php if ($fresh['last'] !== null): ?>
                <form method="post" action="<?= e($scope['base']) ?>/coverage/checked"><?= $csrf->field() ?><button type="submit" class="btn btn--small btn--ghost">Nothing has changed</button></form>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($fresh['last'] !== null): ?>
    <p class="note">Last update: <?= e(date('j M Y', strtotime((string) $fresh['last']) ?: time())) ?>.</p>
<?php endif; ?>
<div class="tile-grid">
    <a class="tile tile--link" href="<?= e($scope['base']) ?>/coverage"><h2>Topics taught</h2><p>Tick what the school has taught, is teaching now, or teaches next. Students can then ask for those topics.</p><span class="tag tag--ready">Update</span></a>
    <a class="tile tile--link" href="<?= e($scope['base']) ?>/textbooks"><h2>Textbooks</h2><p><?= e($bookCount) ?> book<?= $bookCount === 1 ? '' : 's' ?> listed. Add the chapter and pages for each topic so students read the right part.</p><span class="tag tag--ready">Manage books</span></a>
</div>

<div class="card section-card">
    <h2 class="h3">Progress through the syllabus</h2>
    <?= $view->partial('coverage_progress', ['progress' => $progress, 'names' => $names, 'subjects' => $scope['subjects']]) ?>
</div>

<div class="card section-card">
    <h2 class="h3">Where <?= $scope['isTutor'] ? 'this school’s' : 'your' ?> students are stuck</h2>
    <?php if ($top === []): ?>
        <p class="muted">No topic requests from <?= $scope['isTutor'] ? 'this school’s' : 'your' ?> students yet.</p>
    <?php else: ?>
        <p class="muted">Topics SMART Circle students from this school asked for help with. Names are not shown.</p>
        <ol class="stuck-list">
            <?php foreach ($top as $t): ?>
                <li><span class="badge badge--<?= e($t['subject']) ?>"><?= e($names[$t['subject']] ?? $t['subject']) ?></span>
                    <span>Form <?= e($t['form']) ?> · <?= $t['parent_title'] ? e($t['parent_title']) . ' › ' : '' ?><?= e($t['title']) ?></span>
                    <strong><?= e($t['students']) ?></strong></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
