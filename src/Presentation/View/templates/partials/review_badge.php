<?php
/**
 * "Checked by a qualified teacher", with the teacher's signature.
 * @var array<string, mixed>|null $approval
 */
if ($approval === null) {
    return;
}
?>
<aside class="review-badge">
    <p class="review-badge__title">✓ Checked by a qualified teacher</p>
    <p><strong><?= e($approval['reviewer_name']) ?></strong><?= $approval['school_name'] ? ', ' . e($approval['school_name']) : '' ?>
        <span class="muted">· <?= e(date('j F Y', (int) strtotime((string) $approval['created_at']))) ?></span></p>
    <?php if ($approval['signature_path'] !== null): ?><img class="signature-img" src="/reviews/<?= e($approval['id']) ?>/signature" alt="Signature of <?= e($approval['reviewer_name']) ?>"><?php endif; ?>
</aside>
