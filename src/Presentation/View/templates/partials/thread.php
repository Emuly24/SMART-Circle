<?php
/**
 * A conversation's messages, oldest first, with "Seen" under my latest message.
 * @var array<string, mixed> $t
 * @var int $me
 * @var bool|null $canQuote show a "Quote" button on each message (when the viewer can reply)
 */
$canQuote ??= false;
$last = count($t['messages']) - 1;
?>
<ol class="thread">
    <?php foreach ($t['messages'] as $i => $m): $mine = (int) $m['user_id'] === $me; ?>
        <li class="msg<?= $mine ? ' msg--mine' : '' ?>"<?= $i === $last ? ' id="latest"' : '' ?>>
            <p class="msg__meta"><?= $view->partial('avatar', ['id' => (int) $m['user_id'], 'name' => (string) ($m['full_name'] ?? '?'), 'at' => $m['avatar_at'] ?? null, 'size' => 'xs']) ?> <strong><?= e($mine ? 'You' : ($m['full_name'] ?? 'Former member')) ?></strong><?php if (!$mine && ($m['role'] ?? '') === 'tutor'): ?> <span class="muted">· tutor</span><?php endif; ?>
                <span class="muted">· <time datetime="<?= e(date('c', (int) strtotime((string) $m['created_at']))) ?>"><?= e(date('j M Y, H:i', (int) strtotime((string) $m['created_at']))) ?></time></span></p>
            <?php if (!empty($m['quote_body'])): ?>
                <blockquote class="msg__quote"><span class="msg__quote-who"><?= e((int) $m['quote_user_id'] === $me ? 'You' : ($m['quote_name'] ?? 'Former member')) ?> wrote:</span> <?= e(mb_strimwidth((string) $m['quote_body'], 0, 220, '…')) ?></blockquote>
            <?php endif; ?>
            <div class="msg__body"><?= nl2br(e($m['body'])) ?></div>
            <?php if ($canQuote): ?>
                <a class="msg__quote-btn" href="?quote=<?= e($m['id']) ?>#reply" data-quote-id="<?= e($m['id']) ?>" data-quote-name="<?= e($mine ? 'You' : ($m['full_name'] ?? 'Former member')) ?>" data-quote-text="<?= e(mb_strimwidth((string) $m['body'], 0, 220, '…')) ?>">Quote</a>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ol>
<?php if ($t['seen'] !== null): ?>
    <p class="seen muted">Seen by <?= e(implode(', ', $t['seen']['names'])) ?> · <?= e(date('j M, H:i', (int) strtotime($t['seen']['at']))) ?></p>
<?php endif; ?>
