<?php
/**
 * @var array<string, mixed> $t
 * @var int $me
 * @var string $role
 * @var string $draft
 * @var string|null $error
 * @var string $action
 */
$c = $t['conversation'];
$tutorId = null;
foreach ($t['members'] as $p) {
    if ($p['role'] === 'tutor' && (int) $p['user_id'] !== $me) {
        $tutorId = (int) $p['user_id'];
    }
}
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/messages">Messages</a></p>
        <h1><?= e($c['subject']) ?></h1>
        <p class="muted"><?php if ($c['kind'] === 'announcement'): ?>Announcement<?= $role === 'tutor' ? ' · sent to ' . e(count($t['members']) - 1) . ' people' : '' ?><?php else: ?>Between <?= e(implode(', ', array_map(static fn (array $p): string => (int) $p['user_id'] === $me ? 'you' : (string) $p['full_name'], $t['members']))) ?><?php endif; ?></p>
        <?= $view->partial('thread', ['t' => $t, 'me' => $me]) ?>
        <?php if ($t['can_reply']): ?>
            <?= $view->partial('reply_form', ['action' => $action, 'draft' => $draft, 'error' => $error, 'label' => $c['kind'] === 'announcement' ? 'Add to this announcement' : 'Reply']) ?>
        <?php else: ?>
            <?php if ($error !== null): ?><div class="alert alert--error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?php if ($c['kind'] === 'announcement' && $tutorId !== null): ?>
            <p><a class="btn btn--small btn--ghost" href="/messages/new?to=<?= e($tutorId) ?>">Reply privately to the tutor</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
