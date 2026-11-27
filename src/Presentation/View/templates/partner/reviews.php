<?php
/**
 * @var array<string, mixed>|null $me
 * @var list<array<string, mixed>> $waiting
 * @var list<array<string, mixed>> $done
 * @var array<string, string> $names
 * @var list<array<string, mixed>> $lessons lessons to check
 */
$card = static function (array $n, array $names): string {
    $state = $n['state']['state'];
    $chip = ['requested' => 'Waiting for a check', 'changes' => 'Changes suggested', 'stale' => 'Changed since it was approved: check again', 'approved' => '✓ Approved'][$state] ?? '';

    return '<a class="lesson-card" href="/partner/reviews/' . e($n['id']) . '"><span class="lesson-card__meta"><span class="badge badge--' . e($n['subject']) . '">'
        . e($names[$n['subject']] ?? '') . '</span><span class="lesson-card__form">Form ' . e($n['form']) . '</span></span>'
        . '<span class="lesson-card__title">' . e($n['title']) . '</span><span class="lesson-card__info">' . e($n['topic_title']) . '</span>'
        . '<span class="chip' . ($state === 'approved' ? ' chip--lesson' : '') . '">' . e($chip) . '</span></a>';
};
?>
<section class="section section--tight">
    <div class="container">
        <h1>Check notes and lessons</h1>
        <p class="lead">Your knowledge keeps SMART Circle accurate. Read each set of notes and its exercises, then approve it with your signature or suggest changes.</p>
        <?php if ($me === null): ?>
            <div class="card"><p>Your account is not a school partner account.</p></div>
        <?php else: ?>
            <?php if ($me['signature_path'] === null): ?>
                <div class="alert alert--info">Before you approve notes, <a href="/partner/signature">add your signature</a>. It is shown on the notes you approve.</div>
            <?php else: ?>
                <div class="card signature-card"><img class="signature-img" src="/partner/signature/image" alt="Your signature">
                    <p class="muted">Your signature · <a href="/partner/signature">change it</a></p></div>
            <?php endif; ?>
            <h2>Notes waiting for you</h2>
            <?php if ($waiting === []): ?>
                <div class="card"><p>Nothing to check right now. You will get a notification when the tutor sends notes in <?= e(implode(', ', array_map(static fn (string $s): string => $names[$s] ?? $s, $me['subject_list']))) ?>.</p></div>
            <?php else: ?>
                <div class="lesson-grid"><?php foreach ($waiting as $n) { echo $card($n, $names); } ?></div>
            <?php endif; ?>
            <?php if (($lessons ?? []) !== []): ?>
                <h2>Lessons to check</h2>
                <div class="lesson-grid">
                    <?php foreach ($lessons as $l): $st = $l['state']['state']; ?>
                        <a class="lesson-card" href="/partner/reviews/lessons/<?= e($l['id']) ?>"><span class="lesson-card__meta"><span class="badge badge--<?= e($l['subject']) ?>"><?= e($names[$l['subject']] ?? '') ?></span><span class="lesson-card__form">Form <?= e($l['form']) ?> · Lesson</span></span>
                            <span class="lesson-card__title"><?= e($l['title']) ?></span><?php if ($l['topic_title']): ?><span class="lesson-card__info"><?= e($l['topic_title']) ?></span><?php endif; ?>
                            <span class="chip<?= $st === 'approved' ? ' chip--lesson' : '' ?>"><?= e(['requested' => 'Waiting for a check', 'changes' => 'Changes suggested', 'stale' => 'Changed since it was approved: check again', 'approved' => '✓ Approved'][$st] ?? '') ?></span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($done !== []): ?>
                <h2>Approved</h2>
                <div class="lesson-grid"><?php foreach ($done as $n) { echo $card($n, $names); } ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
