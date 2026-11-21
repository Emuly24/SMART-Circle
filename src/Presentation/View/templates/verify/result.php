<?php
/**
 * @var array<string, mixed>|null $doc
 * @var string $code
 */
?>
<section class="section">
    <div class="container narrow">
        <div class="card center">
            <?php if ($doc === null): ?>
                <p class="verify-icon verify-icon--bad" aria-hidden="true">✗</p>
                <h1>Not found</h1>
                <p>No SMART Circle document has the code <strong><?= e($code) ?></strong>. Check the code, or treat the document as not genuine.</p>
            <?php elseif ($doc['revoked_at'] !== null): ?>
                <p class="verify-icon verify-icon--bad" aria-hidden="true">!</p>
                <h1>Replaced</h1>
                <p>This <?= e(strtolower((string) $doc['title'])) ?> was issued, but later replaced by a newer document. It is no longer valid.</p>
            <?php else: ?>
                <p class="verify-icon" aria-hidden="true">✓</p>
                <h1>Genuine</h1>
                <p>
                    <strong><?= e($doc['title']) ?></strong> for <strong><?= e($doc['data']['full_name'] ?? '') ?></strong>
                    (Form <?= e($doc['data']['form'] ?? '') ?>), issued by SMART Circle on <?= e(date('j F Y', strtotime((string) $doc['issued_at']))) ?>.
                </p>
            <?php endif; ?>
            <p><a href="/verify">Check another code</a></p>
        </div>
    </div>
</section>
