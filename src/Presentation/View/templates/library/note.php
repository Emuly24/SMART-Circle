<?php
/**
 * A library handout: the note's text and pictures (never its exercises), in the brand style.
 * Shown in the site (any theme) or, with $print, as a light A4 page for printing or saving as PDF.
 * @var array<string, mixed> $note
 * @var array<string, string> $names
 * @var bool $print
 * @var string $applyAt
 * @var array<string, mixed>|null $approval
 */
use SmartCircle\Presentation\View\NoteMarkup;

$id = (int) $note['id'];
$subject = $names[$note['subject']] ?? '';
?>
<?php if ($print): ?>
<article class="letter handout">
    <header class="letter__head">
        <img class="letter__logo" src="<?= e($asset('brand/logo-horizontal.svg')) ?>" alt="SMART Circle">
        <div class="letter__meta"><p><strong><?= e($subject) ?></strong> · Form <?= e($note['form']) ?></p><p><?= e($note['topic_title']) ?></p></div>
    </header>
    <h1 class="handout__title"><?= e($note['title']) ?></h1>
<?php else: ?>
<section class="section section--tight">
    <div class="container reader">
        <p class="crumbs"><a href="/library">Free library</a></p>
        <p><span class="badge badge--<?= e($note['subject']) ?>"><?= e($subject) ?></span> <span class="muted">Form <?= e($note['form']) ?> · <?= e($note['topic_title']) ?></span></p>
        <h1><?= e($note['title']) ?></h1>
        <p><a class="btn btn--small btn--ghost" href="/library/notes/<?= e($id) ?>/print">Print or save as PDF</a></p>
<?php endif; ?>
        <?php if (($note['summary'] ?? '') !== ''): ?><p class="lead handout__summary"><?= e($note['summary']) ?></p><?php endif; ?>
        <?php foreach ($note['blocks'] as $b): $bid = (int) $b['id']; ?>
            <?php if ($b['type'] === 'text'): ?>
                <div class="note-prose"><?= NoteMarkup::render((string) $b['body']) ?></div>
            <?php else: ?>
                <figure class="note-figure note-figure--<?= e($b['align'] ?? 'center') ?>"><img src="/library/notes/<?= e($id) ?>/images/<?= e($bid) ?>" alt="<?= e($b['body'] ?? 'Picture') ?>"<?= $print ? '' : ' loading="lazy"' ?>>
                    <?php if ($b['body']): ?><figcaption><?= e($b['body']) ?></figcaption><?php endif; ?></figure>
            <?php endif; ?>
        <?php endforeach; ?>
        <?= $view->partial('review_badge', ['approval' => $approval]) ?>
<?php if ($print): ?>
    <footer class="letter__foot handout__foot">
        <p>SMART Circle · free lessons for Form 1 to 4 students in Malawi · Exercises for this topic are for admitted students. Apply free<?= $applyAt !== '' ? ' at ' . e($applyAt) : ' on our website' ?>.</p>
    </footer>
</article>
<?php else: ?>
        <?= $view->partial('library_invite') ?>
    </div>
</section>
<?php endif; ?>
