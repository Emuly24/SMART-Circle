<?php
/**
 * A conversation's messages, oldest first, with "Seen" under my latest message.
 * @var array<string, mixed> $t
 * @var int $me
 */
$last = count($t['messages']) - 1;
?>
<ol class="thread">
    <?php foreach ($t['messages'] as $i => $m): $mine = (int) $m['user_id'] === $me; ?>
        <li class="msg<?= $mine ? ' msg--mine' : '' ?>"<?= $i === $last ? ' id="latest"' : '' ?>>
            <p class="msg__meta"><strong><?= e($mine ? 'You' : ($m['full_name'] ?? 'Former member')) ?></strong><?php if (!$mine && ($m['role'] ?? '') === 'tutor'): ?> <span class="muted">· tutor</span><?php endif; ?>
                <span class="muted">· <time datetime="<?= e(date('c', (int) strtotime((string) $m['created_at']))) ?>"><?= e(date('j M Y, H:i', (int) strtotime((string) $m['created_at']))) ?></time></span></p>
            <div class="msg__body"><?= nl2br(e($m['body'])) ?></div>
        </li>
    <?php endforeach; ?>
</ol>
<?php if ($t['seen'] !== null): ?>
    <p class="seen muted">Seen by <?= e(implode(', ', $t['seen']['names'])) ?> · <?= e(date('j M, H:i', (int) strtotime($t['seen']['at']))) ?></p>
<?php endif; ?>
