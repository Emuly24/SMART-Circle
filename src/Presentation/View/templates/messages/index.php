<?php
/**
 * @var list<array<string, mixed>> $items
 * @var int $me
 * @var string $role
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <div class="page-head">
            <h1>Messages</h1>
            <a class="btn btn--small" href="/messages/new"><?= in_array($role, ['tutor', 'team'], true) ? 'New message or announcement' : 'Write to the tutor' ?></a>
        </div>
        <?php if ($role === 'student'): ?>
            <p class="muted">You can write to the tutor about your studies. For something private, use the <a href="/support">private room</a>.</p>
        <?php endif; ?>
        <?php if ($items === []): ?>
            <div class="card"><p class="muted">No messages yet.</p></div>
        <?php else: ?>
            <ul class="inbox">
                <?php foreach ($items as $c): $unread = (int) $c['unread'] > 0; ?>
                    <li class="inbox-row<?= $unread ? ' inbox-row--unread' : '' ?>">
                        <a href="/messages/<?= e($c['id']) ?>#latest">
                            <span class="inbox-row__top">
                                <span class="inbox-row__subject"><?php if ($c['kind'] === 'announcement'): ?><span class="chip chip--planned">Announcement</span> <?php endif; ?><?= e($c['subject']) ?></span>
                                <span class="muted inbox-row__when"><?= e(date('j M, H:i', (int) strtotime((string) $c['last_message_at']))) ?></span>
                            </span>
                            <span class="inbox-row__who muted"><?= e($c['kind'] === 'announcement' && (int) $c['created_by'] === $me ? 'To: ' . ($c['audience'] === 'students' ? 'all students' : ($c['audience'] === 'partners' ? 'all partners' : ($c['audience'] === 'alumni' ? 'all alumni' : (str_starts_with((string) $c['audience'], 'subject:') ? 'students taking ' . substr((string) $c['audience'], 8) : 'Form ' . substr((string) $c['audience'], 5))))) : 'With: ' . ($c['others'] ?? '')) ?></span>
                            <span class="inbox-row__last"><?= e(((int) $c['last_author'] === $me ? 'You: ' : '') . mb_strimwidth(\SmartCircle\Support\NameTags::preview((string) $c['last_body'], (int) $c['last_author'] === $me ? null : ['full_name' => (string) ($ctx->user()['full_name'] ?? ''), 'title' => $ctx->user()['honorific'] ?? null]), 0, 110, '…')) ?></span>
                            <?php if ($unread): ?><span class="bell__count inbox-row__count" aria-label="<?= e($c['unread']) ?> unread"><?= e($c['unread']) ?></span><?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
