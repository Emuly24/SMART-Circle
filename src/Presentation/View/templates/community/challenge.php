<?php
/**
 * @var array<string, mixed> $v challenge, closed, mine, answers, count
 * @var array<string, string> $names
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 * @var \SmartCircle\Presentation\Http\Context $ctx
 */
$c = $v['challenge'];
$isTutor = $ctx->role() === 'tutor';
?>
<section class="section section--tight">
    <div class="container reader">
        <p class="crumbs"><a href="<?= $isTutor ? '/studio/community' : '/community' ?>">Community</a></p>
        <p><span class="badge badge--<?= e($c['subject']) ?>"><?= e($names[$c['subject']] ?? '') ?></span> <span class="muted">Form <?= e($c['form']) ?><?= $c['topic_title'] ? ' · ' . e($c['topic_title']) : '' ?></span></p>
        <h1><?= e($c['title']) ?></h1>
        <div class="note-prose"><?= \SmartCircle\Presentation\View\NoteMarkup::render((string) $c['body']) ?></div>
        <p class="muted"><?= $v['closed'] ? 'Closed ' : 'Open until ' ?><?= e(date('l j F, H:i', (int) strtotime((string) $c['closes_at']))) ?> · <?= e($v['count']) ?> answer<?= $v['count'] === 1 ? '' : 's' ?></p>

        <?php if (!$isTutor && !$v['closed']): ?>
            <?php if ($v['mine'] === null): ?>
                <form method="post" action="/community/challenges/<?= e($c['id']) ?>" class="form card"><?= $csrf->field() ?>
                    <label class="field"><span class="field__label">Your answer <span class="muted">(only the tutor sees it until the challenge closes)</span></span><textarea name="body" rows="5" maxlength="2000" required></textarea></label>
                    <div class="form-actions"><button type="submit" class="btn btn--green">Send my answer</button></div></form>
            <?php else: ?>
                <div class="alert alert--success">Your answer is in. Come back after <?= e(date('l j F', (int) strtotime((string) $c['closes_at']))) ?> to see everyone's answers and the explanation.</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($v['closed'] && !empty($c['explanation'])): ?>
            <div class="card note-callout note-callout--key"><span class="note-callout__label">The explanation:</span> <?= nl2br(e($c['explanation'])) ?></div>
        <?php endif; ?>
        <?php if ($isTutor): ?>
            <form method="post" action="/studio/community/challenges/<?= e($c['id']) ?>/explain" class="form card"><?= $csrf->field() ?>
                <label class="field"><span class="field__label">Your explanation <span class="muted">(shown to everyone when it closes)</span></span><textarea name="explanation" rows="5"><?= e($c['explanation'] ?? '') ?></textarea></label>
                <div class="form-actions"><button type="submit" class="btn btn--small">Save explanation</button></div></form>
        <?php endif; ?>
        <?php if ($v['answers'] !== []): ?>
            <h2><?= $v['closed'] ? 'Everyone\'s answers' : 'Answers so far (only you see them)' ?></h2>
            <ul class="comments"><?php foreach ($v['answers'] as $a): ?>
                <li class="comment"><p class="comment__head"><strong><?= e($a['author']) ?></strong></p><p class="comment__body"><?= $a['html'] ?></p></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>
