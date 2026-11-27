<?php
/**
 * @var list<array<string, mixed>> $pending
 * @var list<array<string, mixed>> $decided
 * @var array<string, string> $names
 * @var array<string, string> $types
 * @var \SmartCircle\Presentation\Http\Csrf $csrf
 */
use SmartCircle\Application\Learning\Quick5Service;
use SmartCircle\Presentation\View\NoteMarkup;
?>
<section class="section section--tight">
    <div class="container narrow-wide">
        <p class="crumbs"><a href="/studio/questions">Question bank</a> · <a href="/studio/partners">Partner teachers</a></p>
        <h1>Suggested questions</h1>
        <p class="lead">Questions partner teachers suggest. Accepted ones join the question bank with the teacher's name; you can edit them there like any other.</p>
        <?php if ($pending === []): ?><div class="card"><p>No suggestions waiting.</p></div><?php endif; ?>
        <?php foreach ($pending as $s): $answer = Quick5Service::rightAnswer((string) $s['type'], $s['data']); ?>
            <div class="card suggestion">
                <p><span class="badge badge--<?= e($s['subject']) ?>"><?= e($names[$s['subject']] ?? '') ?></span> <span class="muted">Form <?= e($s['form']) ?><?= $s['topic_title'] ? ' · ' . e($s['topic_title']) : ' · no topic' ?> · <?= e($types[$s['type']] ?? $s['type']) ?> · <?= e($s['marks']) ?> mark<?= (int) $s['marks'] === 1 ? '' : 's' ?></span></p>
                <p class="muted">From <strong><?= e($s['partner_name'] ?? 'a former partner') ?></strong><?= $s['school_name'] ? ', ' . e($s['school_name']) : '' ?></p>
                <div class="note-prose"><?= NoteMarkup::render((string) $s['prompt']) ?></div>
                <?php if (!empty($s['data']['options'])): ?><ul class="review-options"><?php foreach ($s['data']['options'] as $o): ?><li><?= NoteMarkup::inline((string) $o) ?></li><?php endforeach; ?></ul><?php endif; ?>
                <p class="review-answer"><?= $answer !== '' ? 'Answer: <strong>' . NoteMarkup::inline($answer) . '</strong>' : '<span class="muted">Written answer, marked by you</span>' ?></p>
                <div class="decision__buttons">
                    <form method="post" action="/studio/question-suggestions/<?= e($s['id']) ?>" class="inline-form"><?= $csrf->field() ?><input type="hidden" name="decision" value="accept"><button type="submit" class="btn btn--small btn--green">Add to the question bank</button></form>
                </div>
                <details><summary>Decline with a reason</summary>
                    <form method="post" action="/studio/question-suggestions/<?= e($s['id']) ?>" class="form"><?= $csrf->field() ?><input type="hidden" name="decision" value="decline">
                        <label class="field"><span class="field__label">Reason for the teacher</span><input type="text" name="note" maxlength="500" required placeholder="e.g. We already have this one; could you try one about…"></label>
                        <div class="form-actions"><button type="submit" class="btn btn--small btn--ghost">Decline</button></div></form>
                </details>
            </div>
        <?php endforeach; ?>
        <?php if ($decided !== []): ?>
            <h2>Decided</h2>
            <ul class="review-history"><?php foreach ($decided as $s): ?>
                <li class="review-history__item review-history__item--<?= $s['status'] === 'accepted' ? 'approved' : 'changes' ?>"><p><strong><?= $s['status'] === 'accepted' ? '✓ Added' : 'Declined' ?></strong> · <?= e($s['partner_name'] ?? '') ?> <span class="muted">· <?= e(mb_strimwidth((string) $s['prompt'], 0, 120, '…')) ?></span></p>
                    <?php if ($s['tutor_note']): ?><p class="review-history__comment"><?= e($s['tutor_note']) ?></p><?php endif; ?></li>
            <?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
</section>
