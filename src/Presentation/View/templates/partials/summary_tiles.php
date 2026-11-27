<?php
/** What needs the admin's or a team tutor's attention. @var array<string, mixed> $m unread, to_mark, rechecks, role */
?>
<div class="stat-tiles dashboard-summary">
    <a class="stat-tile" href="/messages"><span class="stat-tile__n"><?= e($m['unread']) ?></span><span>unread conversation<?= $m['unread'] === 1 ? '' : 's' ?></span></a>
    <a class="stat-tile" href="/studio/marking"><span class="stat-tile__n"><?= e($m['to_mark']) ?></span><span>to mark</span></a>
    <?php if ($m['role'] === 'tutor'): ?>
        <a class="stat-tile" href="/studio/rechecks"><span class="stat-tile__n"><?= e($m['rechecks']) ?></span><span>re-check request<?= $m['rechecks'] === 1 ? '' : 's' ?></span></a>
    <?php endif; ?>
</div>
