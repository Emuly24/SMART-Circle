<?php
/**
 * @var list<array<string, mixed>> $notes
 * @var array<string, string> $names
 * @var array<string, string> $kinds
 */
?>
<section class="section section--tight">
    <div class="container">
        <p class="crumbs"><a href="/dashboard">Dashboard</a></p>
        <div class="title-row">
            <h1>Notes &amp; slides</h1>
            <div class="title-row__actions"><a class="btn btn--small" href="/studio/notes/new">New notes or slides</a></div>
        </div>
        <p class="lead">Students read up to each checkpoint exercise; the next part opens when they submit it.</p>
        <?php if ($notes === []): ?>
            <div class="card"><p>Nothing yet. Create your first notes or slides.</p></div>
        <?php else: ?>
            <div class="lesson-grid">
                <?php foreach ($notes as $n): ?>
                    <a class="lesson-card" href="/studio/notes/<?= e($n['id']) ?>">
                        <span class="lesson-card__meta"><span class="badge badge--<?= e($n['subject']) ?>"><?= e($names[$n['subject']] ?? '') ?></span>
                            <span class="lesson-card__form">Form <?= e($n['form']) ?> · <?= e($kinds[$n['kind']] ?? '') ?></span></span>
                        <span class="lesson-card__title"><?= e($n['title']) ?></span>
                        <span class="lesson-card__info"><?= e($n['topic_title']) ?> · <?= e($n['blocks']) ?> parts, <?= e($n['checkpoints']) ?> checkpoint<?= (int) $n['checkpoints'] === 1 ? '' : 's' ?></span>
                        <span class="chip <?= $n['status'] === 'published' ? 'chip--lesson' : '' ?>"><?= $n['status'] === 'published' ? 'Published' : 'Draft' ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
