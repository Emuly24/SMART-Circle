<?php
/** @var list<array<string, mixed>> $rows */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/marking">Marking</a></p>
        <h1>Re-check requests</h1>
        <p class="lead">Students who believe an answer deserves another look. Every request and reply is kept.</p>
        <?php if ($rows === []): ?>
            <div class="card"><p>No requests yet.</p></div>
        <?php else: ?>
            <ul class="check-list">
                <?php foreach ($rows as $r): ?>
                    <li class="card check<?= $r['status'] === 'open' ? ' check--contradiction' : '' ?>">
                        <p><strong><?= e($r['full_name']) ?></strong> <span class="muted">· <?= e($r['assessment_title']) ?> · <?= e(date('j M', (int) strtotime((string) $r['created_at']))) ?></span>
                            <span class="chip <?= $r['status'] === 'open' ? 'chip--open' : ($r['status'] === 'changed' ? 'chip--lesson' : '') ?>"><?= e(['open' => 'Waiting', 'upheld' => 'Mark kept', 'changed' => 'Mark changed'][$r['status']] ?? $r['status']) ?></span></p>
                        <p class="muted"><?= e(mb_strimwidth((string) $r['prompt'], 0, 120, '…')) ?></p>
                        <blockquote><?= nl2br(e($r['reason'])) ?></blockquote>
                        <p><a class="btn btn--small<?= $r['status'] === 'open' ? '' : ' btn--ghost' ?>" href="/studio/rechecks/<?= e($r['id']) ?>"><?= $r['status'] === 'open' ? 'Reply' : 'View' ?></a></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
