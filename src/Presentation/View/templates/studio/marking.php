<?php
/**
 * @var list<array<string, mixed>> $rows
 * @var int $openRechecks
 * @var array<string, string> $kinds
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <h1>Marking</h1>
        <p class="lead">Everything waiting for your marks, oldest first: exams, assignments and checkpoints in notes.</p>
        <?php if ($openRechecks > 0): ?>
            <div class="alert alert--info"><a href="/studio/rechecks"><?= e($openRechecks) ?> re-check request<?= $openRechecks === 1 ? '' : 's' ?></a> waiting for your reply.</div>
        <?php endif; ?>
        <?php if ($rows === []): ?>
            <div class="card"><p>Nothing to mark. 🎉</p></div>
        <?php else: ?>
            <ul class="check-list">
                <?php foreach ($rows as $s): ?>
                    <li class="card check">
                        <p><strong><?= e($s['full_name']) ?></strong> <span class="muted">· submitted <?= e(date('D j M, H:i', (int) strtotime((string) $s['submitted_at']))) ?></span></p>
                        <p><?= e($s['assessment_title']) ?> <span class="chip"><?= e($kinds[$s['kind']] ?? $s['kind']) ?></span></p>
                        <p><a class="btn btn--small" href="/studio/submissions/<?= e($s['id']) ?>">Mark now</a></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
