<?php
/**
 * @var string $tag
 * @var list<array<string, mixed>> $comments
 */
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/community">Community</a></p>
        <h1>#<?= e($tag) ?></h1>
        <?php if ($comments === []): ?><div class="card"><p>Nothing with this tag that you can open yet.</p></div><?php else: ?>
            <ul class="comments"><?php foreach ($comments as $c): ?>
                <li class="comment"><p class="comment__head"><strong><?= e($c['author']) ?></strong> <span class="muted">on <a href="/lessons/<?= e($c['target_id']) ?>#comment-<?= e($c['id']) ?>"><?= e($c['lesson_title']) ?></a></span></p><p class="comment__body"><?= $c['html'] ?></p></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>
