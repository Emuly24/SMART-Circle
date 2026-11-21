<?php
/**
 * @var array{exceptions: list<array<string, mixed>>, reports: list<array<string, mixed>>, disagreements: list<array<string, mixed>>} $checks
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$topic = static fn (array $r, array $names): string => '<span class="badge badge--' . e($r['subject']) . '">' . e($names[$r['subject']] ?? $r['subject']) . '</span> '
    . ($r['parent_title'] ? '<span class="muted">' . e($r['parent_title']) . ' › </span>' : '') . '<strong>' . e($r['topic_title']) . '</strong>';
$button = static fn (string $action, array $r, string $decision, string $label, string $class) => '<form method="post" action="' . e($action) . '">' . $csrf->field()
    . '<input type="hidden" name="user_id" value="' . e($r['user_id']) . '"><input type="hidden" name="topic_id" value="' . e($r['topic_id']) . '">'
    . '<input type="hidden" name="decision" value="' . e($decision) . '"><button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
$total = count($checks['exceptions']) + count($checks['reports']);
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a> · <a href="/studio/honesty">Honesty report</a></p>
        <h1>Checks</h1>
        <p class="lead">Decisions that keep SMART Circle honest: lessons open only for topics a student’s own school has taught.</p>
        <?php if ($total === 0 && $checks['disagreements'] === []): ?>
            <div class="card"><p>Nothing to check. 🎉</p></div>
        <?php endif; ?>

        <?php if ($checks['exceptions'] !== []): ?>
            <h2 class="h3">Early access requests</h2>
            <p class="muted">Students asking for a topic before their school teaches it. Approve only for real reasons.</p>
            <ul class="check-list">
                <?php foreach ($checks['exceptions'] as $r): ?>
                    <li class="card check">
                        <p><?= $topic($r, $names) ?> <span class="muted">· Form <?= e($r['topic_form']) ?></span></p>
                        <p><strong><?= e($r['full_name']) ?></strong> <span class="muted">· <?= e($r['school_name'] ?? 'No school') ?></span></p>
                        <blockquote><?= nl2br(e($r['reason'])) ?></blockquote>
                        <div class="decision__buttons">
                            <?= $button('/studio/checks/exception', $r, 'approve', 'Open early for this student', 'btn btn--small btn--green') ?>
                            <?= $button('/studio/checks/exception', $r, 'decline', 'Decline', 'btn btn--small btn--ghost') ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($checks['reports'] !== []): ?>
            <h2 class="h3">“My class has covered it”</h2>
            <p class="muted">Students’ own reports. Where no teacher reports that subject, the topic is already open for the student; please check with the school. Where it contradicts the school’s teacher, it stays closed until you confirm.</p>
            <ul class="check-list">
                <?php foreach ($checks['reports'] as $r): ?>
                    <li class="card check<?= $r['contradicts'] ? ' check--contradiction' : '' ?>">
                        <p><?= $topic($r, $names) ?> <span class="muted">· Form <?= e($r['form']) ?></span></p>
                        <p><strong><?= e($r['full_name']) ?></strong> <span class="muted">· <?= e($r['school_name'] ?? 'No school') ?><?= $r['district'] ? ', ' . e($r['district']) : '' ?></span></p>
                        <p><?= $r['contradicts']
                            ? '<span class="tag">Contradicts the school’s report: closed until you confirm</span>'
                            : '<span class="chip chip--planned">No teacher reports this subject: open, please check</span>' ?></p>
                        <div class="decision__buttons">
                            <?= $button('/studio/checks/report', $r, 'confirm', 'Confirm for this student', 'btn btn--small btn--green') ?>
                            <?php if ($r['school_id']): ?><?= $button('/studio/checks/report', $r, 'confirm_school', 'Confirm for the whole school', 'btn btn--small') ?><?php endif; ?>
                            <?= $button('/studio/checks/report', $r, 'reject', 'Not taught yet', 'btn btn--small btn--ghost') ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($checks['disagreements'] !== []): ?>
            <h2 class="h3">Classmates who disagree</h2>
            <p class="muted">Students at the same school and form gave different answers. They may be in different classes; worth a call to the school.</p>
            <ul class="letter-list">
                <?php foreach ($checks['disagreements'] as $d): ?>
                    <li><strong><?= e($d['topic_title']) ?></strong> (<?= e($names[$d['subject']] ?? $d['subject']) ?>), <?= e($d['school_name']) ?> Form <?= e($d['form']) ?>: <?= e($d['voices']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
