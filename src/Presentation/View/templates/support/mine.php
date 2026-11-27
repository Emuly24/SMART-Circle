<?php
/**
 * @var array<string, mixed>|null $t
 * @var int $me
 * @var string $draft
 * @var string|null $error
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <h1>Private room</h1>
        <p class="lead">A private place to talk with the tutor about anything affecting your studies.</p>
        <?= $view->partial('room_rules', ['forTutor' => false]) ?>
        <?php if ($t !== null): ?>
            <?php if ($t['conversation']['opened_by'] === 'tutor'): ?><p class="muted">The tutor invited you to this room on <?= e(date('j M Y', (int) strtotime((string) $t['conversation']['created_at']))) ?>.</p><?php endif; ?>
            <p class="muted">In this room: <?= e(implode(', ', array_map(static fn (array $m): string => (int) $m['user_id'] === $me ? 'you' : (string) $m['full_name'], $t['members']))) ?></p>
            <?= $view->partial('thread', ['t' => $t, 'me' => $me]) ?>
        <?php endif; ?>
        <?= $view->partial('reply_form', ['action' => '/support', 'draft' => $draft, 'error' => $error, 'label' => $t === null ? 'What would you like to talk about?' : 'Your message']) ?>
    </div>
</section>
