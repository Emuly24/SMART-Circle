<?php
/**
 * The private room on a dashboard. Students: their one room with the tutor, or an invitation to open it.
 * Admin: the rooms with the newest activity.
 * @var list<array<string, mixed>> $rooms from MessageRepository::inbox(…, 'support')
 * @var string $role student | tutor
 */
$unread = array_sum(array_map(static fn (array $r): int => (int) $r['unread'], $rooms));
?>
<div class="card section-card room-card<?= $unread > 0 ? ' room-card--unread' : '' ?>" id="private-room">
    <div class="room-card__head">
        <svg class="room-card__icon" viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 5a3 3 0 0 1 6 0v3H9V7Zm3 7a2 2 0 0 1 1 3.7V19h-2v-1.3A2 2 0 0 1 12 14Z"/></svg>
        <h2 class="h3"><?= $role === 'student' ? 'Your private room' : 'Private rooms' ?></h2>
        <?php if ($unread > 0): ?><span class="nav-count" aria-label="<?= e($unread) ?> unread"><?= e($unread) ?></span><?php endif; ?>
    </div>
    <?php if ($role === 'student'): $r = $rooms[0] ?? null; ?>
        <?php if ($r === null): ?>
            <p>Something affecting your studies? Talk to the tutor privately. Only you and the tutor can read it.</p>
            <a class="btn btn--small" href="/support">Invite the tutor to talk</a>
        <?php else: ?>
            <p><?= (int) $r['unread'] > 0 ? '<strong>New message from the tutor.</strong> ' : '' ?><?= $r['opened_by'] === 'tutor' && (int) $r['message_count'] <= 1 ? 'The tutor would like to talk with you privately.' : 'Last message ' . e(date('j M, H:i', (int) strtotime((string) $r['last_message_at']))) . '.' ?></p>
            <a class="btn btn--small" href="/support#latest">Open your private room</a>
        <?php endif; ?>
        <p class="muted room-card__help">If you are in danger, call the Child Helpline on <a href="tel:116">116</a>.</p>
    <?php else: ?>
        <?php if ($rooms === []): ?>
            <p class="muted">No private rooms yet. To invite a student, open their page in <a href="/studio/students">Students</a>.</p>
        <?php else: ?>
            <ul class="timeline"><?php foreach (array_slice($rooms, 0, 4) as $r): ?>
                <li><a href="/studio/support/<?= e($r['id']) ?>#latest"><?= e($r['student_name'] ?? 'Student') ?></a>
                    <span class="muted">· <?= e(date('j M, H:i', (int) strtotime((string) $r['last_message_at']))) ?></span><?= (int) $r['unread'] > 0 ? ' <span class="chip chip--open">' . e($r['unread']) . ' new</span>' : '' ?></li>
            <?php endforeach; ?></ul>
            <p><a href="/studio/support">All private rooms</a> · <a href="/studio/students">Invite a student</a></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
