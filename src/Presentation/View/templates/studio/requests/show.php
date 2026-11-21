<?php
/**
 * @var array<string, mixed> $r
 * @var array<string, string> $names
 * @var array<string, string> $struggles
 * @var array<string, string> $short
 * @var array<string, string> $statuses
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$id = (int) $r['id'];
$statusButton = static function (string $status, string $label, string $class = 'btn btn--small') use ($id, $csrf): string {
    return '<form method="post" action="/studio/requests/' . e($id) . '/status">' . $csrf->field()
        . '<input type="hidden" name="status" value="' . e($status) . '"><button type="submit" class="' . e($class) . '">' . e($label) . '</button></form>';
};
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/requests">Topic requests</a></p>
        <p><span class="badge badge--<?= e($r['subject']) ?>"><?= e($names[$r['subject']] ?? $r['subject']) ?></span> <span class="muted">Form <?= e($r['form']) ?><?= $r['parent_title'] ? ' · ' . e($r['parent_title']) : '' ?></span></p>
        <h1><?= e($r['topic_title']) ?></h1>
        <p><span class="chip chip--<?= e($r['status']) ?>"><?= e($statuses[$r['status']] ?? $r['status']) ?></span>
            <span class="muted"><?= e(count($r['voices'])) ?> <?= count($r['voices']) === 1 ? 'student' : 'students' ?> · first asked <?= e(date('j M Y', strtotime((string) $r['created_at']) ?: time())) ?></span></p>

        <?php if ((bool) $r['is_exception']): ?>
            <p class="note">Asked for early by a student whose school has not taught it. Early-access decisions are made per student in <a href="/studio/checks">Checks</a>.</p>
        <?php endif; ?>

        <div class="card">
            <h2 class="h3">Where they are stuck</h2>
            <p class="struggle-bar">
                <?php foreach ($r['struggles'] as $type => $n): ?>
                    <span class="struggle struggle--<?= e($type) ?>"><?= e($short[$type] ?? $type) ?> <b><?= e($n) ?></b></span>
                <?php endforeach; ?>
            </p>
            <ul class="voices">
                <?php foreach ($r['voices'] as $v): ?>
                    <li>
                        <strong><?= e($v['full_name']) ?></strong> <span class="muted">· <?= e($struggles[$v['struggle_type']] ?? $v['struggle_type']) ?></span>
                        <?php if ($v['access'] !== null): ?>
                            <span class="chip <?= $v['access']['open'] ? ($v['access']['basis'] === 'exception' ? 'chip--planned' : 'chip--lesson') : 'chip--locked' ?>"><?= e(\SmartCircle\Application\Topics\AccessService::TUTOR_STATES[$v['access']['state']] ?? '') ?></span>
                        <?php endif; ?>
                        <?php if ($v['details']): ?><p><?= nl2br(e($v['details'])) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <h2 class="h3">What schools have taught</h2>
            <?php if ($r['schools'] === []): ?>
                <p class="muted">No partner school has reported on this topic yet.</p>
            <?php else: ?>
                <ul class="letter-list">
                    <?php foreach ($r['schools'] as $s): ?>
                        <li><?= e($s['school']) ?>, <?= e($s['district']) ?>: <strong><?= e(['covered' => 'taught', 'covering' => 'teaching now', 'next' => 'next'][$s['status']] ?? $s['status']) ?></strong></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="h3">What next</h2>
            <p class="muted">When you publish a lesson linked to this topic, the request becomes “Lesson ready” and every student here is told.</p>
            <div class="decision__buttons">
                <?php if ($r['status'] === 'open'): ?><?= $statusButton('planned', 'Mark as planned') ?><?php endif; ?>
                <a class="btn btn--small btn--ghost" href="/studio/lab">Record a lesson</a>
                <?php if ($r['lesson_id']): ?><a class="btn btn--small btn--ghost" href="/lessons/<?= e($r['lesson_id']) ?>">Watch the lesson</a><?php endif; ?>
                <?php if ($r['status'] !== 'closed'): ?><?= $statusButton('closed', 'Close', 'btn btn--small btn--ghost') ?><?php else: ?><?= $statusButton('open', 'Reopen', 'btn btn--small btn--ghost') ?><?php endif; ?>
            </div>
        </div>
    </div>
</section>
