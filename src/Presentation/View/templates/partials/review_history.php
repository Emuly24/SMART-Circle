<?php
/** @var list<array<string, mixed>> $history newest first */
?>
<ul class="review-history">
    <?php foreach ($history as $h): ?>
        <li class="review-history__item review-history__item--<?= e($h['decision']) ?>">
            <p><strong><?= $h['decision'] === 'approved' ? '✓ Approved' : 'Changes suggested' ?></strong> by <?= e($h['reviewer_name']) ?><?= $h['school_name'] ? ', ' . e($h['school_name']) : '' ?>
                <span class="muted">· <?= e(date('j M Y, H:i', (int) strtotime((string) $h['created_at']))) ?></span></p>
            <?php if ($h['comment'] !== null): ?><p class="review-history__comment"><?= nl2br(e($h['comment'])) ?></p><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
