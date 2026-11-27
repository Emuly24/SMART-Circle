<?php
/**
 * @var list<array<string, mixed>> $posts
 * @var list<array<string, mixed>> $mine
 * @var string $error
 * @var list<array<string, mixed>> $topicOptions
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var \SmartCircle\Presentation\Http\Context $ctx
 */
$status = ['pending' => 'Waiting for the tutor', 'visible' => 'On the wall', 'hidden' => 'Not shown'];
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/community">Community</a></p>
        <h1>Spotted in real life</h1>
        <p class="lead">Science and maths are everywhere: a lever at the market, a chemical reaction in the kitchen, a pattern in a chitenje. Photograph it and say what it shows.</p>
        <?php if ($ctx->role() === 'student'): ?>
            <form method="post" action="/community/wall" enctype="multipart/form-data" class="form card"><?= $csrf->field() ?>
                <?php if ($error !== ''): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>
                <label class="field"><span class="field__label">Photo</span><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    <span class="field__hint">No faces of other people, please, and nothing private. The tutor looks at every photo first.</span></label>
                <label class="field"><span class="field__label">What did you spot, and where?</span><input type="text" name="caption" maxlength="500" required placeholder="e.g. The wheelbarrow at Lizulu market is a second-class lever"></label>
                <label class="field"><span class="field__label">Topic <span class="muted">(optional)</span></span><select name="topic_id"><option value="0">—</option>
                    <?php foreach ($topicOptions as $o): ?><option value="<?= e($o['id']) ?>">Form <?= e($o['form']) ?> · <?= e(\SmartCircle\Support\Subjects::NAMES[$o['subject']] ?? '') ?> · <?= e($o['label']) ?></option><?php endforeach; ?></select></label>
                <div class="form-actions"><button type="submit" class="btn btn--green">Send to the wall</button></div>
            </form>
            <?php if ($mine !== []): ?><p class="muted">Your photos: <?php foreach ($mine as $m): ?><span class="chip"><?= e($status[$m['status']] ?? '') ?></span> <?php endforeach; ?></p><?php endif; ?>
        <?php endif; ?>
        <?php if ($posts === []): ?><div class="card"><p>No photos yet. Be the first!</p></div><?php else: ?>
            <ul class="wall-grid"><?php foreach ($posts as $p): ?>
                <li><figure class="wall-card<?= $p['featured'] ? ' wall-card--featured' : '' ?>"><img src="/community/wall/<?= e($p['id']) ?>/photo" alt="<?= e($p['caption']) ?>" loading="lazy">
                    <figcaption><?= $p['featured'] ? '⭐ ' : '' ?><?= e($p['caption']) ?><br><span class="muted"><?= e($p['author']) ?><?= $p['topic_title'] ? ' · ' . e($p['topic_title']) : '' ?></span>
                        <?php if ($ctx->role() === 'student'): ?><details class="comment__report"><summary>Report</summary><form method="post" action="/community/wall/<?= e($p['id']) ?>/report" class="form"><?= $csrf->field() ?>
                            <label class="field"><span class="field__label">What is wrong?</span><input type="text" name="reason" maxlength="255" required></label><button type="submit" class="btn btn--small btn--ghost">Send to the tutor</button></form></details><?php endif; ?>
                    </figcaption></figure></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>
