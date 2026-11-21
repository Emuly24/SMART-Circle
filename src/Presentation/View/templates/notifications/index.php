<?php
/**
 * @var list<array<string, mixed>> $items
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
$unread = count(array_filter($items, static fn (array $n): bool => $n['read_at'] === null));
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <div class="title-row">
            <h1>Notifications</h1>
            <?php if ($unread > 0): ?>
                <form method="post" action="/notifications/read">
                    <?= $csrf->field() ?>
                    <button type="submit" class="btn btn--small btn--ghost">Mark all as read</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($items === []): ?>
            <div class="card"><p>Nothing yet. You will be told here when a lesson you asked for is planned or ready.</p></div>
        <?php else: ?>
            <ul class="notice-list">
                <?php foreach ($items as $n): ?>
                    <li class="notice<?= $n['read_at'] === null ? ' notice--unread' : '' ?>">
                        <?php if ($n['link']): ?><a href="<?= e($n['link']) ?>"><?php endif; ?>
                        <strong><?= e($n['title']) ?></strong>
                        <?php if ($n['body']): ?><span><?= e($n['body']) ?></span><?php endif; ?>
                        <time class="muted" datetime="<?= e($n['created_at']) ?>"><?= e(date('j M, H:i', strtotime((string) $n['created_at']) ?: time())) ?></time>
                        <?php if ($n['link']): ?></a><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
